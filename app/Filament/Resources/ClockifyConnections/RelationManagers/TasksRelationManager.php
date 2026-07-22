<?php

namespace App\Filament\Resources\ClockifyConnections\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('clockify_task_id')
                    ->required()
                    ->maxLength(255),
                TextInput::make('clockify_task_name')->label('Task name')->maxLength(255),
                TextInput::make('clockify_project_id')->label('Project ID')->maxLength(255),
                TextInput::make('jira_issue_key')->label('Jira issue key')->required()->maxLength(255),
                TextInput::make('jira_issue_id')->label('Jira issue ID')->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('clockify_task_id')
            ->columns([
                TextColumn::make('clockify_task_id')
                    ->searchable(),
                TextColumn::make('clockify_task_name')->label('Task name')->searchable(),
                TextColumn::make('jira_issue_key')->label('Jira issue')->badge()->searchable(),
                TextColumn::make('mapping_source')->label('Nguồn')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
