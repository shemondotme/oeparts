<?php

namespace App\Filament\Resources\NewsletterCampaignResource\Pages;

use App\Filament\Resources\NewsletterCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterCampaign extends EditRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Keep the status honest for editable campaigns: scheduling toggles
        // draft <-> scheduled. Never touch sending/sent/failed states.
        if (in_array($this->getRecord()->status, ['draft', 'scheduled'], true)) {
            $data['status'] = filled($data['scheduled_at'] ?? null) ? 'scheduled' : 'draft';
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit ' . ($this->record?->subject ?? 'Campaign');
    }

    public function getSubheading(): ?string
    {
        return 'Update the content, scheduling, or settings for this campaign.';
    }
}
