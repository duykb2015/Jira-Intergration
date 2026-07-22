<?php

namespace App\Filament\Resources\ClockifyConnections\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebhookEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookEvents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                TextColumn::make('event_type')
                    ->searchable(),
                TextColumn::make('external_object_id')->label('Entry ID')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts')->numeric(),
                TextColumn::make('received_at')->dateTime()->sortable(),
                TextColumn::make('processed_at')->dateTime()->sortable(),
                TextColumn::make('last_error')->label('Lỗi')->limit(60),
            ])
            ->filters([
                //
            ])
            ->defaultSort('received_at', 'desc')
            ->headerActions([])->recordActions([])->toolbarActions([]);
    }
}
