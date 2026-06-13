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
            Actions\ViewAction::make()
                ->label('View Order')
                ->color('gray'),
            Actions\DeleteAction::make()
                ->modalHeading('Delete Order')
                ->modalDescription('Are you sure you want to delete this order? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete'),
        ];
    }
}
