<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit ' . \App\Filament\Support\AdminUi::localizedName($this->getRecord()->name);
    }

    public function getSubheading(): string
    {
        return "Last updated {$this->getRecord()->updated_at->diffForHumans()}";
    }
}
