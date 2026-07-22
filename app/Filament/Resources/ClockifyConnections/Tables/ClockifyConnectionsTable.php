<?php

namespace App\Filament\Resources\ClockifyConnections\Tables;

use App\Jobs\ReconcileClockifyConnection;
use App\Models\ClockifyConnection;
use App\Services\Clockify\ClockifyConnectionVerifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClockifyConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Thành viên')->searchable()->sortable(),
                TextColumn::make('clockify_email')->label('Clockify')->searchable(),
                TextColumn::make('workspace_name')->label('Workspace')->searchable(),
                TextColumn::make('integrationUser.mapping_status')->label('Mapping')->badge(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'connected' => 'success',
                    'disabled' => 'gray',
                    default => 'danger',
                }),
                TextColumn::make('last_checked_at')->label('Kiểm tra lần cuối')->dateTime()->sortable(),
                TextColumn::make('last_synced_at')->label('Sync lần cuối')->dateTime()->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('test')
                    ->label('Test connection')
                    ->icon('heroicon-o-signal')
                    ->action(function (ClockifyConnection $record): void {
                        try {
                            app(ClockifyConnectionVerifier::class)->verify($record->api_token, $record->clockify_workspace_id);
                            $record->update(['status' => 'connected', 'last_checked_at' => now()]);
                            Notification::make()->success()->title('Connection hợp lệ')->send();
                        } catch (ValidationException $exception) {
                            $record->update(['status' => 'invalid_credentials', 'last_checked_at' => now()]);
                            Notification::make()->danger()->title('Connection không hợp lệ')
                                ->body(collect($exception->errors())->flatten()->first())->send();
                        }
                    }),
                Action::make('toggle')
                    ->label(fn (ClockifyConnection $record): string => $record->status === 'disabled' ? 'Enable' : 'Disable')
                    ->icon(fn (ClockifyConnection $record): string => $record->status === 'disabled' ? 'heroicon-o-play' : 'heroicon-o-pause')
                    ->color(fn (ClockifyConnection $record): string => $record->status === 'disabled' ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->action(fn (ClockifyConnection $record) => $record->update([
                        'status' => $record->status === 'disabled' ? 'connected' : 'disabled',
                    ])),
                Action::make('reconcile')
                    ->label('Re-sync 7 ngày')
                    ->icon('heroicon-o-arrow-path')
                    ->disabled(fn (ClockifyConnection $record): bool => $record->status !== 'connected')
                    ->action(function (ClockifyConnection $record): void {
                        ReconcileClockifyConnection::dispatch($record->id, now()->subDays(7)->toIso8601String(), now()->toIso8601String());
                        Notification::make()->success()->title('Đã đưa reconcile vào queue')->send();
                    }),
                Action::make('regenerateSecret')
                    ->label('Đổi webhook secret')
                    ->icon('heroicon-o-key')
                    ->color(Color::Red)
                    ->requiresConfirmation()
                    ->action(function (ClockifyConnection $record): void {
                        $secret = Str::random(64);
                        $record->forceFill(['webhook_secret_hash' => Hash::make($secret)])->save();
                        $url = url("/api/webhooks/clockify/{$record->uuid}/{$secret}");
                        Notification::make()->warning()->persistent()
                            ->title('Webhook URL mới')
                            ->body("URL cũ đã hết hiệu lực. Sao chép URL này ngay: `{$url}`")
                            ->send();
                    }),
                DeleteAction::make()
                    ->before(fn (ClockifyConnection $record) => $record->update(['status' => 'disabled']))
                    ->successNotificationTitle('Connection đã xóa mềm; lịch sử được giữ lại'),
            ])
            ->toolbarActions([]);
    }
}
