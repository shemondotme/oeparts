<?php

namespace App\Filament\Resources\NotFoundLogResource\Pages;

use App\Filament\Resources\NotFoundLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNotFoundLog extends ViewRecord
{
    protected static string $resource = NotFoundLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
