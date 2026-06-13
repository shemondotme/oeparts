<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CategoryResource::class;

    public function getHeading(): string
    {
        return 'Create Category';
    }

    public function getSubheading(): string
    {
        return 'Add a new category for blog content.';
    }
}
