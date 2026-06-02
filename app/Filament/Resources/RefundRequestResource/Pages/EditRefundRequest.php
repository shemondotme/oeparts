<?php

namespace App\Filament\Resources\RefundRequestResource\Pages;

use App\Filament\Resources\RefundRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRefundRequest extends EditRecord
{
    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
