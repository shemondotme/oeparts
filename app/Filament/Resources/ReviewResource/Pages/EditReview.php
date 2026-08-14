<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReview extends EditRecord
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getHeading(): string
    {
        return 'Edit Review — ' . ($this->record?->reviewer_name ?? '');
    }

    public function getSubheading(): ?string
    {
        return 'Correct or moderate this customer review.';
    }
}
