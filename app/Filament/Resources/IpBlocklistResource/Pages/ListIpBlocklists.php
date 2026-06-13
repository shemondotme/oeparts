<?php

namespace App\Filament\Resources\IpBlocklistResource\Pages;

use App\Filament\Resources\IpBlocklistResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIpBlocklists extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = IpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getSavedViewHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
