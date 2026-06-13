<?php

namespace App\Filament\Resources\CarrierResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\CarrierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCarrier extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CarrierResource::class;

    public function getHeading(): string
    {
        return 'Create Carrier';
    }

    public function getSubheading(): string
    {
        return 'Add a shipping carrier with tracking URL support.';
    }
}
