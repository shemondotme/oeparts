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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // coupons.created_by is a NOT NULL foreign key (migration
        // 2026_03_26_100019) with no form field for it anywhere — every
        // single coupon creation crashed with a raw SQLSTATE NOT NULL
        // constraint failure instead of saving, confirmed live. Same
        // auth('admin')->id() pattern already used by CreateNewsletterCampaign.
        $data['created_by'] = auth('admin')->id();

        return $data;
    }
}
