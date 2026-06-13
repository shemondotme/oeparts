<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = CouponResource::class;

    protected ?string $heading = 'New Coupon';

    protected ?string $subheading = 'Create a discount code for promotions and campaigns.';
}
