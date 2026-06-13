<?php

namespace App\Filament\Resources\FaqResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\FaqResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFaq extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = FaqResource::class;
}
