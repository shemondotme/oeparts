<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentPolicy extends BasePolicy
{
    protected string $model = 'payments';
}
