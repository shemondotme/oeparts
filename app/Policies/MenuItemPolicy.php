<?php

declare(strict_types=1);

namespace App\Policies;

class MenuItemPolicy extends BasePolicy
{
    protected string $model = 'menu_items';
    protected ?string $permissionKey = 'menus';
}
