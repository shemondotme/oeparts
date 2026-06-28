<?php

declare(strict_types=1);

namespace App\Policies;

class SeoMetaPolicy extends BasePolicy
{
    protected string $model = 'seo_meta';
    protected ?string $permissionKey = 'seo meta';
}
