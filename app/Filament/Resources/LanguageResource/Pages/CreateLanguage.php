<?php

namespace App\Filament\Resources\LanguageResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\LanguageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLanguage extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = LanguageResource::class;
}
