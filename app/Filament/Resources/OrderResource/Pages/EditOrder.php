<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    public function getHeading(): string
    {
        return "Edit Order #{$this->getRecord()->order_number}";
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $updated = $record->updated_at?->diffForHumans() ?? 'recently';
        $created = $record->created_at?->format('d M Y H:i') ?? '—';
        return "Placed {$created} | Last updated {$updated}";
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::makeChangeStatusAction(),
            Actions\ViewAction::make()
                ->label('View Order')
                ->color('gray'),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Order')
                    ->modalDescription('Are you sure you want to delete this order? Prefer cancelling via "Change Status" — deletion is for test/junk orders only.')
                    ->modalSubmitActionLabel('Yes, delete'),
            ])
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray'),
        ];
    }

    /**
     * Relation managers that mutate order contents live on the edit page;
     * the view page shows the same data read-only in its infolist + timeline.
     */
    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager::class,
            \App\Filament\Resources\OrderResource\RelationManagers\OrderNotesRelationManager::class,
        ];
    }
}
