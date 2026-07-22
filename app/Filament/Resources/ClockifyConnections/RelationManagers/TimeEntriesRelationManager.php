<?php

namespace App\Filament\Resources\ClockifyConnections\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('clockify_time_entry_id')
            ->columns([
                TextColumn::make('clockify_time_entry_id')
                    ->searchable(),
                TextColumn::make('jira_issue_key')->label('Jira issue')->badge()->searchable(),
                TextColumn::make('started_at')->label('Bắt đầu')->dateTime()->sortable(),
                TextColumn::make('duration_seconds')->label('Thời lượng (giây)')->numeric(),
                TextColumn::make('description')->limit(50)->searchable(),
                TextColumn::make('sync_status')->label('Trạng thái')->badge(),
                TextColumn::make('last_error')->label('Lỗi')->limit(50)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('started_at', 'desc')
            ->headerActions([])->recordActions([])->toolbarActions([]);
    }
}
