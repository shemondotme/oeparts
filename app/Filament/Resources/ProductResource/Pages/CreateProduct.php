<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\OemNormalizerService;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['oem_number'])) {
            $data['normalized_oem'] = app(OemNormalizerService::class)->normalize($data['oem_number']);
        }

        return $data;
    }
}
