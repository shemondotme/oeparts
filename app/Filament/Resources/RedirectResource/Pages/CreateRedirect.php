<?php

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = RedirectResource::class;
}
