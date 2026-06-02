<?php

namespace App\Filament\Resources\CarModelResource\Pages;

use App\Filament\Resources\CarModelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCarModel extends CreateRecord
{
    protected static string $resource = CarModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name']) && filled($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
