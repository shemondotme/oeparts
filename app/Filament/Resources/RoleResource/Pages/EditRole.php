<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make()
                    // Same guards as the table action: super_admin is the
                    // trust anchor; in-use roles would strip admins' access.
                    ->hidden(fn (): bool => $this->getRecord()->name === 'super_admin'
                        || $this->getRecord()->users()->exists()),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }
}
