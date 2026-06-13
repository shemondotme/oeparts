<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CustomerResource::class;

    public function getHeading(): string
    {
        return 'Create Customer';
    }

    public function getSubheading(): string
    {
        return 'Add a new customer account.';
    }
}
