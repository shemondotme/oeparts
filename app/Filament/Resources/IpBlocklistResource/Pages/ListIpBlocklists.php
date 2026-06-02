<?php

namespace App\Filament\Resources\IpBlocklistResource\Pages;

use App\Filament\Resources\IpBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIpBlocklists extends ListRecords
{
    protected static string $resource = IpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
