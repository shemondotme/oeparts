<?php

namespace App\Filament\Resources\TaxRateResource\Pages;

use App\Filament\Resources\TaxRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;

    public function getHeading(): string
    {
        return "Edit {$this->getRecord()->country_name}";
    }

    public function getSubheading(): string
    {
        return "Last updated {$this->getRecord()->updated_at->diffForHumans()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
