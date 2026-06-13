<?php

declare(strict_types=1);

namespace App\Policies;

class BlogPostPolicy extends BasePolicy
{
    protected string $model = 'blog_posts';
    protected ?string $permissionKey = 'blog';
}
