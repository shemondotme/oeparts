<?php

declare(strict_types=1);

namespace App\Policies;

class EmailLogPolicy extends LogPolicy
{
    protected string $model = 'email_logs';
    protected ?string $permissionKey = 'email logs';
}
