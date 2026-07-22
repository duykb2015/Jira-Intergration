<?php

namespace App\Filament\Resources\ClockifyConnections\Pages;

use App\Filament\Resources\ClockifyConnections\ClockifyConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClockifyConnections extends ListRecords
{
    protected static string $resource = ClockifyConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
