<?php

namespace App\Filament\Resources\RefundRequestResource\Pages;

use App\Filament\Resources\RefundRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRefundRequest extends EditRecord
{
    protected static string $resource = RefundRequestResource::class;

    public function getHeading(): string
    {
        return "Edit Refund Request #{$this->getRecord()->id}";
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $created = $record->created_at?->format('d M Y H:i');
        $updated = $record->updated_at?->diffForHumans() ?? 'recently';
        return "Submitted {$created} | Last updated {$updated}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->color('gray'),
            Actions\DeleteAction::make()
                ->modalHeading('Delete Refund Request')
                ->modalDescription('Are you sure you want to delete this refund request? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, delete'),
        ];
    }
}
