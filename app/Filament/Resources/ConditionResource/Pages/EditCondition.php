<?php

namespace App\Filament\Resources\ConditionResource\Pages;

use App\Filament\Resources\ConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCondition extends EditRecord
{
    protected static string $resource = ConditionResource::class;

    public function getHeading(): string
    {
        return "Edit {$this->getRecord()->name}";
    }

    public function getSubheading(): string
    {
        return "Last updated {$this->getRecord()->updated_at->diffForHumans()}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Condition')
                ->modalDescription('Are you sure? Conditions still in use by products cannot be deleted.')
                ->action(function () {
                    if ($this->record->products()->count() > 0) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title("Cannot delete \"{$this->record->name}\"")
                            ->body("{$this->record->products()->count()} product(s) still use this condition.")
                            ->send();
                        return;
                    }
                    $this->record->delete();
                    $this->redirect(ConditionResource::getUrl('index'));
                }),
        ];
    }
}
