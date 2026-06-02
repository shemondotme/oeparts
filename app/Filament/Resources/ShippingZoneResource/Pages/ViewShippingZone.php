<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingZone extends ViewRecord
{
    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
