<?php

namespace App\Filament\Resources\PartInquiryResource\Pages;

use App\Filament\Concerns\FillsFromQuery;
use App\Filament\Resources\PartInquiryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePartInquiry extends CreateRecord
{
    use FillsFromQuery;

    protected static string $resource = PartInquiryResource::class;

    protected function queryFillable(): array
    {
        return ['oem_number'];
    }

    protected ?string $heading = 'New Part Inquiry';

    protected ?string $subheading = 'Record a part inquiry received via phone, email, or in person.';

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'new';
        // ip_address is NOT NULL with no form field and no prior default —
        // submitting this form crashed with a raw NOT NULL constraint
        // violation. There's no real customer-originated IP for a manually
        // recorded inquiry (phone/in-person), so this records the acting
        // admin's own request IP, matching the same convention used for
        // NewsletterSubscriberResource's manual-add flow.
        $data['ip_address'] ??= request()->ip();

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Part inquiry created')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return PartInquiryResource::getUrl('index');
    }
}
