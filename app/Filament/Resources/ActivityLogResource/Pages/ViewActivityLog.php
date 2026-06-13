<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Pages\System\SetupAssistant;
use App\Filament\Pages\System\HealthCheckDashboard;
use App\Filament\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\DeleteAction::make(),
        ];

        $sourceUrl = $this->getSourceUrl();
        if ($sourceUrl) {
            array_unshift($actions, Actions\Action::make('viewSource')
                ->label('View in Source Page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url($sourceUrl, shouldOpenInNewTab: true));
        }

        return $actions;
    }

    protected function getSourceUrl(): ?string
    {
        $record = $this->record;

        return match ($record->model_type) {
            SetupAssistant::class => SetupAssistant::getUrl(),
            HealthCheckDashboard::class => HealthCheckDashboard::getUrl(),
            default => null,
        };
    }
}
