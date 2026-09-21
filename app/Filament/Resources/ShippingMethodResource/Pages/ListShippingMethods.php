<?php

namespace App\Filament\Resources\ShippingMethodResource\Pages;

use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Filament\Resources\ShippingMethodResource;
use App\Filament\Support\AdminUi;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShippingMethods extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminUi::settingsLinkAction(StoreOperationsSettings::class),
            ...$this->getSavedViewHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
