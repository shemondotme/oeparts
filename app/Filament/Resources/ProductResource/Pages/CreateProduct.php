<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Concerns\FillsFromQuery;
use App\Filament\Resources\ProductResource;
use App\Services\OemNormalizerService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use DisablesCreateAnother;
    use FillsFromQuery;

    protected static string $resource = ProductResource::class;

    protected function queryFillable(): array
    {
        return ['oem_number'];
    }

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
