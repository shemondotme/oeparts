<?php

declare(strict_types=1);

namespace App\Policies;

class ActivityLogPolicy extends LogPolicy
{
    protected string $model = 'activity_logs';
    protected ?string $permissionKey = 'activity logs';
}
