<?php

namespace App\Filament\Resources\FailedSearchLogResource\Pages;

use App\Filament\Resources\FailedSearchLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFailedSearchLog extends ViewRecord
{
    protected static string $resource = FailedSearchLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
