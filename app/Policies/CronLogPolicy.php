<?php

declare(strict_types=1);

namespace App\Policies;

class CronLogPolicy extends LogPolicy
{
    protected string $model = 'cron_logs';
    protected ?string $permissionKey = 'cron logs';
}
