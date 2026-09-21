<?php

namespace App\Filament\Resources\NewsletterCampaignResource\Pages;

use App\Filament\Pages\Settings\MarketingSettings;
use App\Filament\Resources\NewsletterCampaignResource;
use App\Filament\Support\AdminUi;
use App\Filament\Support\HasDrilldownFilters;
use App\Filament\Support\HasSavedViews;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterCampaigns extends ListRecords
{
    use HasDrilldownFilters, HasSavedViews;

    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AdminUi::settingsLinkAction(MarketingSettings::class),
            ...$this->getSavedViewHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
