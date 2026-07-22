<?php

namespace App\Filament\Resources\ClockifyConnections\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClockifyConnectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection')->schema([
                    TextEntry::make('user.name')->label('Thành viên'),
                    TextEntry::make('clockify_email')->label('Clockify email'),
                    TextEntry::make('workspace_name')->label('Workspace'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('last_checked_at')->dateTime(),
                    TextEntry::make('last_synced_at')->dateTime(),
                ])->columns(3),
                Section::make('Mapping')->schema([
                    TextEntry::make('integrationUser.jira_account_id')->label('Jira account ID')->placeholder('Chưa mapping'),
                    TextEntry::make('integrationUser.jira_email')->label('Jira email')->placeholder('Chưa mapping'),
                    TextEntry::make('integrationUser.teamboard_user_id')->label('Teamboard user ID')->placeholder('Chưa mapping'),
                    TextEntry::make('integrationUser.teamboard_email')->label('Teamboard email')->placeholder('Chưa mapping'),
                ])->columns(2),
            ]);
    }
}
