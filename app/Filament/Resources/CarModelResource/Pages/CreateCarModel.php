<?php

namespace App\Filament\Resources\CarModelResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\CarModelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCarModel extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CarModelResource::class;

    public function getHeading(): string
    {
        return 'Create Car Model';
    }

    public function getSubheading(): string
    {
        return 'Add a new vehicle model to the catalog.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name']) && filled($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }
}
