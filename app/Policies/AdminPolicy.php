<?php

declare(strict_types=1);

namespace App\Policies;

class AdminPolicy extends BasePolicy
{
    protected string $model = 'admins';
}
