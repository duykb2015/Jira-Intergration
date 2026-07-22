<?php

namespace App\Filament\Resources\ClockifyConnections\Schemas;

use App\Contracts\ClockifyClient;
use App\Models\ClockifyConnection;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Http\Client\RequestException;

class ClockifyConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clockify')
                    ->description('Nhập token, sau đó mở danh sách workspace để tải dữ liệu từ Clockify.')
                    ->schema([
                        Select::make('internal_user_id')
                            ->label('Thành viên nội bộ')
                            ->options(fn (?ClockifyConnection $record): array => User::query()
                                ->when($record, fn ($query) => $query->where(fn ($query) => $query
                                    ->whereDoesntHave('clockifyConnection')
                                    ->orWhereKey($record->internal_user_id)))
                                ->when(! $record, fn ($query) => $query->whereDoesntHave('clockifyConnection'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->disabledOn('edit'),
                        TextInput::make('api_token')
                            ->label('Clockify API token')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): string => $operation === 'edit'
                                ? 'Để trống nếu không thay token. Token mới phải thuộc đúng user và workspace cũ.'
                                : 'Workspace sẽ được tải khi bạn mở trường bên dưới.')
                            ->live(onBlur: true),
                        Select::make('clockify_workspace_id')
                            ->label('Clockify workspace')
                            ->options(function (Get $get, ?ClockifyConnection $record): array {
                                if ($record) {
                                    return [$record->clockify_workspace_id => $record->workspace_name ?: $record->clockify_workspace_id];
                                }

                                $token = $get('api_token');
                                if (blank($token)) {
                                    return [];
                                }

                                try {
                                    return collect(app(ClockifyClient::class)->workspaces($token))
                                        ->filter(fn ($workspace) => is_array($workspace) && isset($workspace['id']))
                                        ->mapWithKeys(fn ($workspace) => [(string) $workspace['id'] => (string) ($workspace['name'] ?? $workspace['id'])])
                                        ->all();
                                } catch (RequestException) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required()
                            ->disabledOn('edit'),
                    ])->columns(2),
                Section::make('Mapping người dùng')
                    ->schema([
                        TextInput::make('jira_account_id')->label('Jira account ID'),
                        TextInput::make('jira_email')->label('Jira email')->email(),
                        TextInput::make('teamboard_user_id')->label('Teamboard user ID'),
                        TextInput::make('teamboard_email')->label('Teamboard email')->email(),
                    ])->columns(2),
            ]);
    }
}
