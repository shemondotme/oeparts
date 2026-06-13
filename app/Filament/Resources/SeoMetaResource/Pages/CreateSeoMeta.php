<?php

namespace App\Filament\Resources\SeoMetaResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\SeoMetaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSeoMeta extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SeoMetaResource::class;
}
