<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\OrderResource;
use App\Services\SequenceService;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = app(SequenceService::class)->nextOrderNumber();
        $data['ip_address'] = request()->ip();

        return $data;
    }
}
