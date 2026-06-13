<?php

declare(strict_types=1);

namespace App\Policies;

class LoginLogPolicy extends LogPolicy
{
    protected string $model = 'login_logs';
    protected ?string $permissionKey = 'login logs';
}
