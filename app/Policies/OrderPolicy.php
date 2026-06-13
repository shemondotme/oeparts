<?php

declare(strict_types=1);

namespace App\Policies;

class OrderPolicy extends BasePolicy
{
    protected string $model = 'orders';
}
