<?php

namespace App\Filament\Resources\ClockifyConnections\Pages;

use App\Filament\Resources\ClockifyConnections\ClockifyConnectionResource;
use App\Services\Clockify\ClockifyConnectionVerifier;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditClockifyConnection extends EditRecord
{
    protected static string $resource = ClockifyConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $mapping = $this->record->integrationUser;

        return [
            ...$data,
            'api_token' => null,
            'jira_account_id' => $mapping?->jira_account_id,
            'jira_email' => $mapping?->jira_email,
            'teamboard_user_id' => $mapping?->teamboard_user_id,
            'teamboard_email' => $mapping?->teamboard_email,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $mapping = collect($data)->only([
            'jira_account_id', 'jira_email', 'teamboard_user_id', 'teamboard_email',
        ])->all();
        $data = collect($data)->except(array_keys($mapping))->all();

        if (filled($data['api_token'] ?? null)) {
            [$user, $workspace] = app(ClockifyConnectionVerifier::class)
                ->verify($data['api_token'], $record->clockify_workspace_id);

            if ((string) $user['id'] !== $record->clockify_user_id || (string) $workspace['id'] !== $record->clockify_workspace_id) {
                throw ValidationException::withMessages([
                    'data.api_token' => 'Token mới không thuộc Clockify user và workspace hiện tại.',
                ]);
            }

            $data['status'] = 'connected';
            $data['last_checked_at'] = now();
        }

        $record->update($data);
        $record->integrationUser()->updateOrCreate(
            ['user_id' => $record->internal_user_id],
            [
                ...$mapping,
                'mapping_status' => filled($mapping['jira_account_id'] ?? null) && filled($mapping['teamboard_user_id'] ?? null)
                    ? 'mapped'
                    : 'pending',
            ],
        );

        return $record;
    }
}
