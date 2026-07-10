<?php

namespace App\Filament\Resources\NewsletterCampaignResource\Pages;

use App\Filament\Resources\NewsletterCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterCampaign extends CreateRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected ?string $heading = 'New Campaign';

    protected ?string $subheading = 'Create a newsletter campaign to send to your subscribers.';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth('admin')->id();

        // A future send date means the scheduler will pick it up — reflect
        // that in the status so the list tells the truth.
        $data['status'] = filled($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft';

        return $data;
    }
}
