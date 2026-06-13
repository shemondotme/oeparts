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
}
