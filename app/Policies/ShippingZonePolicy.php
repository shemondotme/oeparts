<?php

declare(strict_types=1);

namespace App\Policies;

class ShippingZonePolicy extends BasePolicy
{
    protected string $model = 'shipping_zones';
    protected ?string $permissionKey = 'shipping zones';
}
