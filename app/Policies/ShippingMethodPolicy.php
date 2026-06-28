<?php

declare(strict_types=1);

namespace App\Policies;

class ShippingMethodPolicy extends BasePolicy
{
    protected string $model = 'shipping_methods';
    protected ?string $permissionKey = 'shipping methods';
}
