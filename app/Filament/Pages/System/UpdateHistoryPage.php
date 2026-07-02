<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Models\UpdateHistory;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Update History (Module 21, Chunk 3.6) — the audit trail of every in-app update
 * attempt (update_histories), including rolled-back ones and their verification
 * reports. Read-only; access = `view updates`.
 */
class UpdateHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Update History';

    protected string $view = 'filament.pages.system.update-history';

    protected ?string $subheading = 'Every in-app update attempt, its outcome, and the post-update verification report.';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 26;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('view updates');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(UpdateHistory::query())
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->fontMono()->size('sm')->sortable(),

                Tables\Columns\TextColumn::make('from_version')
                    ->label('From → To')->size('sm')->fontMono()
                    ->formatStateUsing(fn (?string $state, UpdateHistory $r): string => ($state ?? '—').' → '.$r->to_version),

                Tables\Columns\TextColumn::make('status')
                    ->badge()->size('sm')
                    ->color(fn (string $state): string => match ($state) {
                        UpdateHistory::STATUS_SUCCESS     => 'success',
                        UpdateHistory::STATUS_FAILED      => 'danger',
                        UpdateHistory::STATUS_ROLLED_BACK => 'warning',
                        default                           => 'gray',
                    }),

                Tables\Columns\TextColumn::make('step')->label('Step')->badge()->color('gray')->size('sm'),

                Tables\Columns\TextColumn::make('initiated_by')->label('By')->size('sm')->placeholder('system'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')->dateTime('M j, H:i')->since()->sortable()->fontMono()->size('sm'),

                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Finished')->dateTime('M j, H:i')->since()->placeholder('—')->fontMono()->size('sm'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    UpdateHistory::STATUS_SUCCESS     => 'Success',
                    UpdateHistory::STATUS_FAILED      => 'Failed',
                    UpdateHistory::STATUS_ROLLED_BACK => 'Rolled back',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (UpdateHistory $r): string => 'Update #'.$r->id.' — '.$r->status)
                    ->modalContent(fn (UpdateHistory $r): HtmlString => new HtmlString(
                        '<pre style="white-space:pre-wrap;word-break:break-word;font-size:0.72rem;max-height:60vh;overflow:auto;padding:1rem;border-radius:0.5rem;background:var(--gray-950,#0a0a0a);color:var(--gray-100,#f5f5f5);">'
                        .e(($r->error ? "ERROR:\n".$r->error."\n\n" : '')
                            .json_encode($r->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        .'</pre>'
                    ))
                    ->modalSubmitAction(false),
            ])
            ->emptyStateHeading('No updates yet')
            ->emptyStateDescription('In-app update attempts will be listed here.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
