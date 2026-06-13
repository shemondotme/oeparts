<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\MenuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenu extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = MenuResource::class;
}
