<?php

declare(strict_types=1);

namespace App\Policies;

class UserPolicy extends BasePolicy
{
    protected string $model = 'customers';
}
