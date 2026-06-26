<?php

declare(strict_types=1);

namespace App\Policies;

class FailedSearchLogPolicy extends LogPolicy
{
    protected string $model = 'failed_search_logs';
    protected ?string $permissionKey = 'failed search logs';
}
