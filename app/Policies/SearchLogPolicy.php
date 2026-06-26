<?php

declare(strict_types=1);

namespace App\Policies;

class SearchLogPolicy extends LogPolicy
{
    protected string $model = 'search_logs';
    protected ?string $permissionKey = 'search logs';
}
