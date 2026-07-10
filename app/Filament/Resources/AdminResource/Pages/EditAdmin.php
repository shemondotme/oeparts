<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Models\Admin;
use App\Policies\AdminPolicy;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Admin $record */
        $record = $this->getRecord();
        $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'] && $record->is_active;

        if ($deactivating && $record->is(auth('admin')->user())) {
            throw ValidationException::withMessages([
                'data.is_active' => 'You cannot deactivate your own account — you would be locked out mid-session.',
            ]);
        }

        if ($deactivating && AdminPolicy::isLastActiveSuperAdmin($record)) {
            throw ValidationException::withMessages([
                'data.is_active' => 'This is the last active super admin — deactivating it would orphan the panel.',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make()
                    ->hidden(fn (): bool => $this->getRecord()->is(auth('admin')->user())
                        || AdminPolicy::isLastActiveSuperAdmin($this->getRecord())),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }
}
