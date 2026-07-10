<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }

    public function getHeading(): string
    {
        return "Edit {$this->getRecord()->name}";
    }

    public function getSubheading(): string
    {
        return "Last updated {$this->getRecord()->updated_at->diffForHumans()}";
    }
}
