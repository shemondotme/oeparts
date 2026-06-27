<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasWidgetExport;
use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivityLog extends TableWidget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use HasWidgetExport;

    public function getDescription(): ?string
    {
        return 'Latest audit trail entries';
    }

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -23;

    protected static ?string $heading = 'Recent Admin Activity';

    protected static ?string $maxWidth = 'full';

    protected function getExportHeaders(): array
    {
        return ['Admin', 'Action', 'IP Address', 'Timestamp'];
    }

    protected function getExportRows(): iterable
    {
        return ActivityLog::query()
            ->with('admin')
            ->latest()
            ->get()
            ->map(fn (ActivityLog $log) => [
                $log->admin?->name ?? 'System',
                $log->action,
                $log->ip_address ?? '—',
                $log->created_at?->format('d M Y H:i') ?? '—',
            ]);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            $this->getExportActions(),
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
                    ->limit(6)
            )
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label('Admin')
                    ->searchable()
                    ->getStateUsing(fn (ActivityLog $record): string => $record->admin?->name ?? 'System')
                    ->limit(18),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (ActivityLog $record): string {
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
                // Model + IP live in the full Activity Log resource (via the row
                // action) — omitted here so 5 columns don't overflow the half-width cell.
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->copyMessage('IP address copied')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => 'oem-number']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M j, H:i')
                    ->sortable(),
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
            ->emptyState(
                view('filament.widgets.empty-state', [
                    'icon' => 'heroicon-o-moon',
                    'heading' => 'No recent activity',
                    'description' => 'System is quiet.',
                    'ctaLabel' => '',
                    'ctaUrl' => '',
                ])
            )
            ->searchable(false)
            ->paginated(false);
    }
}
