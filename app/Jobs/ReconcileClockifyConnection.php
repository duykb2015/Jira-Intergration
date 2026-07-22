<?php

namespace App\Jobs;

use App\Contracts\ClockifyClient;
use App\Models\ClockifyConnection;
use App\Services\Clockify\ClockifyEntryProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;

class ReconcileClockifyConnection implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $connectionId, public string $from, public string $to) {}

    public function handle(ClockifyClient $client, ClockifyEntryProcessor $processor): void
    {
        $connection = ClockifyConnection::query()
            ->with(['integrationUser', 'tasks'])
            ->findOrFail($this->connectionId);

        if ($connection->status !== 'connected') {
            return;
        }

        try {
            $entries = $client->timeEntries($connection->api_token, $connection->clockify_workspace_id, $connection->clockify_user_id, $this->from, $this->to);

            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    $processor->processPayload($connection, $entry);
                }
            }

            $connection->update(['last_synced_at' => now()]);
        } catch (RequestException $e) {
            if (in_array($e->response->status(), [401, 403], true)) {
                $connection->update(['status' => 'authentication_failed', 'last_checked_at' => now()]);

                return;
            }
            throw $e;
        }
    }
}
