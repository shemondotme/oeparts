<?php

declare(strict_types=1);

namespace App\Policies;

class NewsletterSubscriberPolicy extends BasePolicy
{
    protected string $model = 'newsletter_subscribers';
    // Seeder's permission set is 'view/create/edit/delete newsletters'
    // (shared with the Newsletters feature area) — not '...newsletter'.
    protected ?string $permissionKey = 'newsletters';
}
