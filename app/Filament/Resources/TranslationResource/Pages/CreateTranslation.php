<?php

namespace App\Filament\Resources\TranslationResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\TranslationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTranslation extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = TranslationResource::class;
}
