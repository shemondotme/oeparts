<?php

namespace App\Filament\Resources\AbandonedCartResource\Pages;

use App\Filament\Resources\AbandonedCartResource;
use Filament\Resources\Pages\ListRecords;

class ListAbandonedCarts extends ListRecords
{
    protected static string $resource = AbandonedCartResource::class;
}
