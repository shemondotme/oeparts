<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = PageResource::class;
}
