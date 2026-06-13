<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
