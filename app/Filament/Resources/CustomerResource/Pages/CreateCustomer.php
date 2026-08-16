<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // The form has no password field — an admin-created customer signs
        // in via the existing "Send Password Reset" table action, the same
        // as a social-login signup (SocialAuthController does the same
        // Hash::make(Str::random(32)) for the same reason). Without this,
        // users.password (NOT NULL, no default) makes every save fail.
        $data['password'] = Hash::make(Str::random(32));

        return $data;
    }
}
