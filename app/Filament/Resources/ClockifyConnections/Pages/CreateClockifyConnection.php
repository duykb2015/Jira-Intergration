<?php

namespace App\Filament\Resources\ClockifyConnections\Pages;

use App\Filament\Resources\ClockifyConnections\ClockifyConnectionResource;
use App\Models\IntegrationUser;
use App\Services\Clockify\ClockifyConnectionVerifier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateClockifyConnection extends CreateRecord
{
    protected static string $resource = ClockifyConnectionResource::class;

    private ?string $plainWebhookSecret = null;

    protected function handleRecordCreation(array $data): Model
    {
        [$clockifyUser, $workspace] = app(ClockifyConnectionVerifier::class)
            ->verify($data['api_token'], $data['clockify_workspace_id']);

        $mapping = collect($data)->only([
            'jira_account_id', 'jira_email', 'teamboard_user_id', 'teamboard_email',
        ])->all();
        $data = collect($data)->except(array_keys($mapping))->all();
        $this->plainWebhookSecret = Str::random(64);

        return DB::transaction(function () use ($data, $mapping, $clockifyUser, $workspace): Model {
            $connection = static::getModel()::query()->create([
                ...$data,
                'clockify_user_id' => (string) $clockifyUser['id'],
                'clockify_email' => $clockifyUser['email'] ?? null,
                'workspace_name' => $workspace['name'] ?? null,
                'webhook_secret_hash' => Hash::make($this->plainWebhookSecret),
                'status' => 'connected',
                'last_checked_at' => now(),
            ]);

            IntegrationUser::query()->create([
                ...$mapping,
                'user_id' => $connection->internal_user_id,
                'clockify_connection_id' => $connection->id,
                'mapping_status' => filled($mapping['jira_account_id'] ?? null) && filled($mapping['teamboard_user_id'] ?? null)
                    ? 'mapped'
                    : 'pending',
            ]);

            return $connection;
        });
    }

    protected function afterCreate(): void
    {
        $url = url("/api/webhooks/clockify/{$this->record->uuid}/{$this->plainWebhookSecret}");

        Notification::make()
            ->success()
            ->title('Connection đã tạo')
            ->body("Webhook URL (chỉ hiển thị lần này): `{$url}`")
            ->persistent()
            ->send();
    }
}
