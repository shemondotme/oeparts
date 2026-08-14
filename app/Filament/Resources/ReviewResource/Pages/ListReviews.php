<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
