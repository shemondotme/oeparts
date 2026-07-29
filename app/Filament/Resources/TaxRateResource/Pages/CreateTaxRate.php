<?php

namespace App\Filament\Resources\TaxRateResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\TaxRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxRate extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = TaxRateResource::class;

    public function getHeading(): string
    {
        return 'Add Tax Rate';
    }

    public function getSubheading(): string
    {
        return 'Set the standard VAT/tax rate for a country.';
    }
}
