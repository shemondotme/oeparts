<?php

namespace App\Filament\Resources\PartInquiryResource\Pages;

use App\Filament\Resources\PartInquiryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartInquiries extends ListRecords
{
    protected static string $resource = PartInquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
