<?php

declare(strict_types=1);

namespace App\Policies;

class NewsletterCampaignPolicy extends BasePolicy
{
    protected string $model = 'newsletter_campaigns';
}
