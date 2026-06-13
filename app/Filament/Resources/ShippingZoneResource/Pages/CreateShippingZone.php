<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingZone extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ShippingZoneResource::class;

    public function getHeading(): string
    {
        return 'Create Shipping Zone';
    }

    public function getSubheading(): string
    {
        return 'Define a new shipping region and configure its settings.';
    }
}
