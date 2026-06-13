<?php

declare(strict_types=1);

namespace App\Policies;

class RefundRequestPolicy extends BasePolicy
{
    protected string $model = 'refund_requests';
    protected ?string $permissionKey = 'refunds';
}
