<?php

declare(strict_types=1);

namespace App\Policies;

class NewsletterCampaignPolicy extends BasePolicy
{
    protected string $model = 'newsletter_campaigns';
    // Seeder's permission set is 'view/create/edit/delete newsletters'
    // (shared with the Newsletters feature area) — not '...newsletter_campaigns'.
    protected ?string $permissionKey = 'newsletters';
}
