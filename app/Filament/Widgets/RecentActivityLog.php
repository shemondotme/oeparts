<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivityLog extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Latest audit trail entries';
    }

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?int $sort = -23;

    protected static ?string $heading = 'Recent Admin Activity';

    protected static ?string $maxWidth = 'full';

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('view_all')
                ->label('View all')
                ->icon('heroicon-o-arrow-right')
                ->link()
                ->url(ActivityLogResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityLog::query()
                    ->with('admin')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('admin.name')
                    ->label('Admin')
                    ->getStateUsing(fn (ActivityLog $record): string => $record->admin?->name ?? 'System')
                    ->weight(FontWeight::Bold)
                    ->description(function (ActivityLog $record): string {
                        $label = ucfirst($record->action);

                        if ($record->model_type) {
                            $label .= ' ' . class_basename($record->model_type);
                            if ($record->model_id) {
                                $label .= ' #' . $record->model_id;
                            }
                        }

                        $old = is_array($record->old_values) ? $record->old_values : [];
                        $new = is_array($record->new_values) ? $record->new_values : [];
                        $changedFields = array_keys(array_filter(
                            $new,
                            fn ($value, $key) => ! array_key_exists($key, $old) || $old[$key] !== $value,
                            ARRAY_FILTER_USE_BOTH
                        ));

                        if ($changedFields !== []) {
                            $label .= ' (' . implode(', ', $changedFields) . ')';
                        }

                        return $label;
                    })
                    ->searchable(query: fn ($query, $search) => $query->where('action', 'like', "%{$search}%")),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->color('gray')
                    ->description(fn (ActivityLog $record): string => $record->created_at?->format('M j, H:i') ?? '—')
                    ->alignEnd(),
            ])
            ->actions([
                Tables\Actions\Action::make('audit_detail')
                    ->label('Diff')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->size('sm')
                    ->color('primary')
                    ->modalHeading(fn (ActivityLog $record): string => 'Audit Trail — ' . $record->action)
                    ->modalContent(fn (ActivityLog $record): \Illuminate\Contracts\View\View => view(
                        'filament.modals.audit-trail-detail',
                        ['record' => $record]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('view_details')
                    ->label('Full Log')
                    ->icon('heroicon-o-chevron-right')
                    ->size('sm')
                    ->color('gray')
                    ->url(fn (ActivityLog $record): string => ActivityLogResource::getUrl('view', ['record' => $record])),
            ])
            ->striped()
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Admin actions will be logged here.')
            ->searchable(false)
            ->paginated(false);
    }
}
