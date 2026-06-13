<?php

namespace App\Filament\Resources\IpBlocklistResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\IpBlocklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIpBlocklist extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = IpBlocklistResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['blocked_by'] = auth('admin')->id();

        return $data;
    }
}
