<?php

declare(strict_types=1);

namespace App\Policies;

class NewsletterSubscriberPolicy extends BasePolicy
{
    protected string $model = 'newsletter_subscribers';
    protected ?string $permissionKey = 'newsletter';
}
