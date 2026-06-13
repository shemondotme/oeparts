<?php

namespace App\Filament\Resources\SectionResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\SectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSection extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = SectionResource::class;
}
