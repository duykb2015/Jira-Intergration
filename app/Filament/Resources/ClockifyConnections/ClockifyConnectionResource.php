<?php

namespace App\Filament\Resources\ClockifyConnections;

use App\Filament\Resources\ClockifyConnections\Pages\CreateClockifyConnection;
use App\Filament\Resources\ClockifyConnections\Pages\EditClockifyConnection;
use App\Filament\Resources\ClockifyConnections\Pages\ListClockifyConnections;
use App\Filament\Resources\ClockifyConnections\Pages\ViewClockifyConnection;
use App\Filament\Resources\ClockifyConnections\RelationManagers\TasksRelationManager;
use App\Filament\Resources\ClockifyConnections\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\ClockifyConnections\RelationManagers\WebhookEventsRelationManager;
use App\Filament\Resources\ClockifyConnections\Schemas\ClockifyConnectionForm;
use App\Filament\Resources\ClockifyConnections\Schemas\ClockifyConnectionInfolist;
use App\Filament\Resources\ClockifyConnections\Tables\ClockifyConnectionsTable;
use App\Models\ClockifyConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClockifyConnectionResource extends Resource
{
    protected static ?string $model = ClockifyConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Clockify connection';

    protected static ?string $pluralModelLabel = 'Clockify connections';

    protected static ?string $recordTitleAttribute = 'clockify_email';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ClockifyConnectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ClockifyConnectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClockifyConnectionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'integrationUser']);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            TimeEntriesRelationManager::class,
            WebhookEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClockifyConnections::route('/'),
            'create' => CreateClockifyConnection::route('/create'),
            'view' => ViewClockifyConnection::route('/{record}'),
            'edit' => EditClockifyConnection::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
