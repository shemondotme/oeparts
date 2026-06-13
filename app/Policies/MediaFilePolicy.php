<?php

declare(strict_types=1);

namespace App\Policies;

class MediaFilePolicy extends BasePolicy
{
    protected string $model = 'media_files';
    protected ?string $permissionKey = 'media';
}
