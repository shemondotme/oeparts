<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\AdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdmin extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = AdminResource::class;
}
