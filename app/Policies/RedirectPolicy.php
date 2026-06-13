<?php

declare(strict_types=1);

namespace App\Policies;

class RedirectPolicy extends BasePolicy
{
    protected string $model = 'redirects';
}
