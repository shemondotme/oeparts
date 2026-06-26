<?php

declare(strict_types=1);

namespace App\Policies;

class BlogTagPolicy extends BasePolicy
{
    protected string $model = 'blog_tags';
    protected ?string $permissionKey = 'blog';
}
