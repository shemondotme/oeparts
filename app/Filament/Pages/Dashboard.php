<?php

namespace App\Filament\Pages;

use App\Services\WidgetPreferenceService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        $widgets = parent::getWidgets();
        $service = app(WidgetPreferenceService::class);
        $sorted = $service->getSortedEnabledClasses();
        $sortedMap = array_flip($sorted);

        $filtered = array_filter($widgets, function ($widget) use ($service) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : (is_string($widget) ? $widget : null);
            return $class && $service->isEnabled($class);
        });

        usort($filtered, function ($a, $b) use ($sortedMap) {
            $classA = $a instanceof WidgetConfiguration ? $a->widget : $a;
            $classB = $b instanceof WidgetConfiguration ? $b->widget : $b;
            return ($sortedMap[$classA] ?? 999) <=> ($sortedMap[$classB] ?? 999);
        });

        return $filtered;
    }

    public function getCachedHeaderActions(): array
    {
        $actions = $this->getHeaderActions();

        foreach ($actions as $action) {
            $this->cacheAction($action);
        }

        return $actions;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageWidgets')
                ->label('Manage Widgets')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->modalHeading('Dashboard Widgets')
                ->modalDescription('Toggle visibility and set display order for dashboard widgets.')
                ->modalWidth(Width::ExtraLarge)
                ->modalSubmitActionLabel('Save')
                ->form(function () {
                    $service = app(WidgetPreferenceService::class);
                    $widgets = $service->getSortedWidgets();

                    return collect($widgets)->flatMap(function ($w) {
                        $id = $w['id'];
                        return [
                            Toggle::make("prefs.{$id}.hidden")
                                ->label($w['label'])
                                ->default(!$w['hidden'])
                                ->inline(false)
                                ->columnSpan(2),
                            TextInput::make("prefs.{$id}.sort")
                                ->label('Position')
                                ->numeric()
                                ->default($w['sort'])
                                ->minValue(1)
                                ->maxValue(99)
                                ->extraAttributes(['class' => 'w-20']),
                        ];
                    })->toArray();
                })
                ->action(function (array $data) {
                    $service = app(WidgetPreferenceService::class);
                    $prefs = [];

                    foreach ($data['prefs'] ?? [] as $id => $settings) {
                        $prefs[$id] = [
                            'hidden' => !($settings['hidden'] ?? true),
                            'sort' => (int) ($settings['sort'] ?? 99),
                        ];
                    }

                    $service->savePreferences($prefs);

                    Notification::make()
                        ->title('Widget preferences updated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
