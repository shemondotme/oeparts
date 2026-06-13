<?php

namespace App\Filament\Resources\ManufacturerResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\ManufacturerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateManufacturer extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ManufacturerResource::class;

    public function getHeading(): string
    {
        return 'Create Manufacturer';
    }

    public function getSubheading(): string
    {
        return 'Add a new OEM brand to the catalog.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            $name = $data['name'] ?? null;
            if (is_array($name) && filled($name['en'] ?? null)) {
                $data['slug'] = Str::slug($name['en']);
            } elseif (is_string($name) && filled($name)) {
                $data['slug'] = Str::slug($name);
            }
        }

        return $data;
    }
}
