<?php

namespace App\Filament\Resources\RefundRequestResource\Pages;

use App\Filament\Resources\RefundRequestResource;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRefundRequests extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction: RefundRequestResource has no 'create' route
        // registered (getPages() only defines index/view/edit) — refund
        // requests originate from customers via the storefront, not manual
        // admin authorship. This button used to render (and, per
        // RefundRequestPolicy, be visible to any admin with the
        // never-actually-seeded 'create refunds' permission — practically,
        // super_admins) but throw an uncaught RouteNotFoundException the
        // moment Filament tried to resolve its target URL, crashing this
        // list page's header entirely for anyone who could see it.
        return [
            ...$this->getSavedViewHeaderActions(),
        ];
    }
}
