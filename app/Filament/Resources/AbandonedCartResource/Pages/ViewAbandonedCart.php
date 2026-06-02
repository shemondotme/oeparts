<?php

namespace App\Filament\Resources\AbandonedCartResource\Pages;

use App\Filament\Resources\AbandonedCartResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAbandonedCart extends ViewRecord
{
    protected static string $resource = AbandonedCartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
