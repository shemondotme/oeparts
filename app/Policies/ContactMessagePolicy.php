<?php

declare(strict_types=1);

namespace App\Policies;

class ContactMessagePolicy extends BasePolicy
{
    protected string $model = 'contact_messages';
    protected ?string $permissionKey = 'contact messages';
}
