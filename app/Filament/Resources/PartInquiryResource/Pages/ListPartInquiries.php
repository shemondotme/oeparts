<?php

namespace App\Filament\Resources\PartInquiryResource\Pages;

use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Filament\Resources\PartInquiryResource;
use App\Filament\Support\AdminUi;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListPartInquiries extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = PartInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminUi::settingsLinkAction(StoreOperationsSettings::class),
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
