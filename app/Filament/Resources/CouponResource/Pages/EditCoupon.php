<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit ' . ($this->record?->code ?? 'Coupon');
    }

    public function getSubheading(): ?string
    {
        return 'Modify discount rules, limits, and availability for this coupon.';
    }
}
