<?php

namespace App\Filament\Resources\ShippingMethodResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\ShippingMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingMethod extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ShippingMethodResource::class;

    public function getHeading(): string
    {
        return 'Create Shipping Method';
    }

    public function getSubheading(): string
    {
        return 'Configure a delivery option for a shipping zone.';
    }
}

