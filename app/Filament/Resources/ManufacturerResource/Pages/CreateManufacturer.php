<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Resources\ManufacturerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateManufacturer extends CreateRecord
{
    protected static string $resource = ManufacturerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name']) && is_string($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
