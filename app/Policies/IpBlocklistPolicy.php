<?php

declare(strict_types=1);

namespace App\Policies;

class IpBlocklistPolicy extends BasePolicy
{
    protected string $model = 'ip_blocklists';
    protected ?string $permissionKey = 'ip blocklist';
}
