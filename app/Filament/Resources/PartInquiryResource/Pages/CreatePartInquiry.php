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
