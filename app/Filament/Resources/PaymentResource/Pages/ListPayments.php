<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \App\Filament\Support\AdminUi::settingsLinkAction(\App\Filament\Pages\Settings\PaymentSettings::class),
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
