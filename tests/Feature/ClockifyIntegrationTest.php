<?php

namespace Tests\Feature;

use App\Contracts\TeamboardClient;
use App\Data\TeamboardTimelogData;
use App\Jobs\ProcessClockifyWebhook;
use App\Jobs\ReconcileClockifyConnection;
use App\Models\ClockifyConnection;
use App\Models\ClockifyTask;
use App\Models\IntegrationUser;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Clockify\ClockifyEntryProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClockifyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_requires_configured_basic_authentication(): void
    {
        config(['services.internal_admin.username' => 'admin', 'services.internal_admin.password' => 'secret']);
        $this->get('/admin/clockify-connections')->assertUnauthorized();
        $this->withBasicAuth('admin', 'secret')->get('/admin/clockify-connections')->assertOk();
        $this->withBasicAuth('admin', 'secret')->get('/admin/clockify-connections/create')->assertOk();
    }

    public function test_api_token_is_encrypted_at_rest(): void
    {
        $connection = $this->connection(User::factory()->create());
        $this->assertNotNull($connection->uuid);
        $this->assertTrue($connection->matchesWebhookSecret('webhook-secret'));
        $this->assertSame('private-token', $connection->api_token);
        $this->assertStringNotContainsString('private-token', $connection->getRawOriginal('api_token'));
    }

    public function test_webhook_stores_one_event_and_dispatches_processing_once(): void
    {
        Queue::fake();
        $connection = $this->connection(User::factory()->create());
        $this->assertNotNull($connection->uuid);
        $this->assertTrue($connection->matchesWebhookSecret('webhook-secret'));
        $payload = ['type' => 'TIMER_STOPPED', 'timeEntry' => ['id' => 'entry-1', 'timeInterval' => ['start' => '2026-07-15T01:00:00Z', 'end' => '2026-07-15T02:00:00Z']]];
        $url = "/api/webhooks/clockify/{$connection->uuid}/webhook-secret";

        $this->postJson($url, $payload)->assertAccepted()->assertJson(['status' => 'accepted']);
        $this->postJson($url, $payload)->assertAccepted()->assertJson(['status' => 'duplicate']);
        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessClockifyWebhook::class, 1);
    }

    public function test_webhook_rejects_invalid_secret_and_disabled_connection(): void
    {
        $connection = $this->connection(User::factory()->create());
        $this->postJson("/api/webhooks/clockify/{$connection->uuid}/wrong", [])->assertNotFound();
        $connection->update(['status' => 'disabled']);
        $this->postJson("/api/webhooks/clockify/{$connection->uuid}/webhook-secret", [])->assertNotFound();
    }

    public function test_processor_resolves_mapped_task_first_and_stops_at_pending_teamboard(): void
    {
        $connection = $this->connection(User::factory()->create());
        ClockifyTask::query()->create(['clockify_connection_id' => $connection->id, 'clockify_task_id' => 'task-1', 'clockify_task_name' => 'WRONG-1', 'jira_issue_key' => 'RIGHT-2']);
        $payload = ['id' => 'entry-1', 'taskId' => 'task-1', 'description' => 'WRONG-3', 'timeInterval' => ['start' => '2026-07-15T01:00:00Z', 'end' => '2026-07-15T02:00:00Z']];

        $entry = app(ClockifyEntryProcessor::class)->processPayload($connection, $payload);
        $this->assertSame('RIGHT-2', $entry->jira_issue_key);
        $this->assertSame('pending_teamboard', $entry->sync_status);
        $this->assertSame($entry->id, app(ClockifyEntryProcessor::class)->processPayload($connection, $payload)->id);
    }

    public function test_unchanged_pending_entry_syncs_after_teamboard_is_configured(): void
    {
        $connection = $this->connection(User::factory()->create());
        IntegrationUser::query()->create([
            'user_id' => $connection->internal_user_id,
            'clockify_connection_id' => $connection->id,
            'teamboard_user_id' => 'teamboard-user',
        ]);
        ClockifyTask::query()->create([
            'clockify_connection_id' => $connection->id,
            'clockify_task_id' => 'task-1',
            'jira_issue_key' => 'TEST-1',
        ]);
        $payload = [
            'id' => 'entry-1',
            'taskId' => 'task-1',
            'timeInterval' => [
                'start' => '2026-07-15T01:00:00Z',
                'end' => '2026-07-15T02:00:00Z',
            ],
        ];

        $entry = app(ClockifyEntryProcessor::class)->processPayload($connection, $payload);
        $this->assertSame('pending_teamboard', $entry->sync_status);

        $client = new class implements TeamboardClient
        {
            public int $created = 0;

            public function isConfigured(): bool
            {
                return true;
            }

            public function find(TeamboardTimelogData $data): ?array
            {
                return null;
            }

            public function create(TeamboardTimelogData $data): array
            {
                $this->created++;

                return ['id' => 'timelog-1'];
            }

            public function update(string $timelogId, TeamboardTimelogData $data): array
            {
                return ['id' => $timelogId];
            }
        };
        $this->app->instance(TeamboardClient::class, $client);

        $entry = app(ClockifyEntryProcessor::class)->processPayload($connection, $payload);

        $this->assertSame('synced', $entry->sync_status);
        $this->assertSame(1, $client->created);
    }

    public function test_unparseable_webhook_is_retained_for_review(): void
    {
        $connection = $this->connection(User::factory()->create());
        $event = WebhookEvent::query()->create(['clockify_connection_id' => $connection->id, 'payload_hash' => hash('sha256', 'x'), 'payload' => ['unknown' => true], 'status' => 'pending', 'received_at' => now()]);
        (new ProcessClockifyWebhook($event->id))->handle(app(ClockifyEntryProcessor::class));
        $this->assertSame('requires_review', $event->fresh()->status);
    }

    public function test_reconcile_command_dispatches_one_job_per_connected_connection(): void
    {
        Queue::fake();
        $this->connection(User::factory()->create());
        $disabled = $this->connection(User::factory()->create());
        $disabled->update(['status' => 'disabled']);
        $this->artisan('clockify:reconcile')->expectsOutput('Queued 1 Clockify connection(s).')->assertSuccessful();
        Queue::assertPushed(ReconcileClockifyConnection::class, 1);
    }

    public function test_soft_delete_retains_connection_history(): void
    {
        config(['services.internal_admin.username' => 'admin', 'services.internal_admin.password' => 'secret']);
        $connection = $this->connection(User::factory()->create());
        $connection->update(['status' => 'disabled']);
        $connection->delete();
        $this->assertSame('disabled', ClockifyConnection::withTrashed()->findOrFail($connection->id)->status);
    }

    private function connection(User $user, string $secret = 'webhook-secret'): ClockifyConnection
    {
        return ClockifyConnection::query()->create([
            'internal_user_id' => $user->id, 'api_token' => 'private-token',
            'clockify_user_id' => 'clockify-user-'.$user->id, 'clockify_workspace_id' => 'workspace-'.$user->id,
            'webhook_secret_hash' => Hash::make($secret), 'status' => 'connected',
        ]);
    }
}
