<?php

namespace App\Filament\Resources\ClockifyConnections\Pages;

use App\Filament\Resources\ClockifyConnections\ClockifyConnectionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClockifyConnection extends ViewRecord
{
    protected static string $resource = ClockifyConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
