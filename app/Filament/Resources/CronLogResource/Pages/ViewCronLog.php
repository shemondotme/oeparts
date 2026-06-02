<?php

namespace App\Filament\Resources\CronLogResource\Pages;

use App\Filament\Resources\CronLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCronLog extends ViewRecord
{
    protected static string $resource = CronLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
