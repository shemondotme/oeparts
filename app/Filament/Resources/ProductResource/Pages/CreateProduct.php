<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\ProductResource;
use App\Services\OemNormalizerService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ProductResource::class;

    public function getHeading(): string
    {
        return 'Create Product';
    }

    public function getSubheading(): string
    {
        return 'Add a new part to the catalog.';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['oem_number'])) {
            $data['normalized_oem'] = app(OemNormalizerService::class)->normalize($data['oem_number']);
        }

        return $data;
    }
}
