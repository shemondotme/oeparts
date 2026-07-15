<?php

namespace App\Filament\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\NewsletterSubscriberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterSubscriber extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = NewsletterSubscriberResource::class;

    protected ?string $heading = 'New Subscriber';

    protected ?string $subheading = 'Manually add a newsletter subscriber to the mailing list.';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // newsletter_subscribers.subscribed_at AND .ip_address are both NOT
        // NULL (migration 2026_03_26_100032) with no form field for either
        // — every manually-added subscriber crashed with a raw SQLSTATE NOT
        // NULL constraint failure instead of saving, confirmed live. Same
        // bug class as CreateCoupon/CreatePage. ip_address is the admin's
        // own request IP (there's no real subscriber-originated IP for a
        // manually-added row — this mirrors the "who/where did this record
        // originate from" intent as closely as an admin-initiated create can).
        $data['subscribed_at'] = now();
        $data['ip_address'] = request()->ip();

        return $data;
    }
}
