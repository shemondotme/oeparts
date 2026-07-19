<?php

declare(strict_types=1);

namespace App\Policies;

class NotFoundLogPolicy extends LogPolicy
{
    protected string $model = 'not_found_logs';
    protected ?string $permissionKey = 'not found logs';
}
