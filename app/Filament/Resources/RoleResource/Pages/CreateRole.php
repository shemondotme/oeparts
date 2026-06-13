<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = RoleResource::class;
}
