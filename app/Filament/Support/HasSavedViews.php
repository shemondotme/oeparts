<?php

namespace App\Filament\Support;

use App\Models\SavedView;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

trait HasSavedViews
{
    protected function getSavedViewHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('saveView')
                    ->label('Save Current View')
                    ->icon('heroicon-o-bookmark')
                    ->color('gray')
                    ->form([
                        TextInput::make('name')
                            ->label('View Name')
                            ->required()
                            ->maxLength(200),
                    ])
                    ->action(function (array $data) {
                        $this->saveCurrentView($data['name']);
                    }),

                Action::make('loadView')
                    ->label('Load View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->form([
                        Select::make('saved_view_id')
                            ->label('Saved View')
                            ->options(fn () => $this->getSavedViewOptions())
                            ->required()
                            ->placeholder('Select a view...'),
                    ])
                    ->action(function (array $data) {
                        $this->applySavedView((int) $data['saved_view_id']);
                    }),

                Action::make('deleteView')
                    ->label('Delete View')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->form([
                        Select::make('saved_view_id')
                            ->label('View to Delete')
                            ->options(fn () => $this->getSavedViewOptions())
                            ->required()
                            ->placeholder('Select a view...'),
                    ])
                    ->action(function (array $data) {
                        $this->deleteSavedView((int) $data['saved_view_id']);
                    }),
            ])
                ->label('Views')
                ->icon('heroicon-o-bookmark-square')
                ->color('gray')
                ->button(),
        ];
    }

    public function saveCurrentView(string $name): void
    {
        SavedView::create([
            'admin_id' => auth('admin')->id(),
            'resource' => $this->getResourceName(),
            'name' => $name,
            'filters' => $this->tableFilters ?? [],
            'sort_column' => $this->extractSortColumn(),
            'sort_direction' => $this->extractSortDirection(),
            'search' => $this->tableSearch ?? '',
        ]);

        Notification::make()
            ->title('View saved as "' . $name . '"')
            ->success()
            ->send();
    }

    public function applySavedView(int $id): void
    {
        $view = SavedView::where('id', $id)
            ->where('admin_id', auth('admin')->id())
            ->firstOrFail();

        $this->tableFilters = $view->filters ?? [];
        $this->tableSearch = $view->search ?? '';

        if ($view->sort_column) {
            $this->tableSort = $view->sort_column . ($view->sort_direction ? ':' . $view->sort_direction : '');
        } else {
            $this->tableSort = null;
        }

        if ($this->getTable()->persistsFiltersInSession()) {
            session()->put($this->getTableFiltersSessionKey(), $this->tableFilters);
        }
        $this->updatedTableSort();

        Notification::make()
            ->title('View "' . $view->name . '" applied')
            ->success()
            ->send();
    }

    public function deleteSavedView(int $id): void
    {
        $view = SavedView::where('id', $id)
            ->where('admin_id', auth('admin')->id())
            ->firstOrFail();

        $view->delete();

        Notification::make()
            ->title('View "' . $view->name . '" deleted')
            ->success()
            ->send();
    }

    public function getSavedViewOptions(): array
    {
        return SavedView::where('admin_id', auth('admin')->id())
            ->where('resource', $this->getResourceName())
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getResourceName(): string
    {
        if (defined('static::$resource') && static::$resource) {
            $parts = explode('\\', static::$resource);
            $class = end($parts);

            return str_replace('Resource', '', $class);
        }

        return class_basename(static::class);
    }

    protected function extractSortColumn(): ?string
    {
        if (blank($this->tableSort)) {
            return null;
        }

        return (string) str($this->tableSort)->before(':');
    }

    protected function extractSortDirection(): ?string
    {
        if (blank($this->tableSort)) {
            return null;
        }

        if (! str($this->tableSort)->contains(':')) {
            return 'asc';
        }

        return match ((string) str($this->tableSort)->after(':')) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => null,
        };
    }
}
