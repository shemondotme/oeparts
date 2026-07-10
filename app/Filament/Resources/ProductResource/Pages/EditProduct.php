<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\OemNormalizerService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

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
        return "Edit {$this->getRecord()->oem_number}";
    }

    public function getSubheading(): string
    {
        return "Last updated {$this->getRecord()->updated_at->diffForHumans()}";
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['oem_number'])) {
            $data['normalized_oem'] = app(OemNormalizerService::class)->normalize($data['oem_number']);
        }

        return $data;
    }
}
