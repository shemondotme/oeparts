<?php

namespace App\Filament\Resources\PartInquiryResource\Pages;

use App\Filament\Resources\PartInquiryResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartInquiries extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;
    protected static string $resource = PartInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
