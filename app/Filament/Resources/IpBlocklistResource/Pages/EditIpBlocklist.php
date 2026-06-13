<?php

namespace App\Filament\Resources\IpBlocklistResource\Pages;

use App\Filament\Resources\IpBlocklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIpBlocklist extends EditRecord
{
    protected static string $resource = IpBlocklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
