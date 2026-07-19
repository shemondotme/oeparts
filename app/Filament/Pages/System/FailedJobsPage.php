<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Models\FailedJob;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class FailedJobsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Failed Jobs';

    protected string $view = 'filament.pages.system.failed-jobs';

    protected ?string $subheading = 'Queue jobs that threw an exception. Retry to re-dispatch, or delete to discard.';

    public static function getNavigationBadge(): ?string
    {
        $count = DB::table('failed_jobs')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-x-circle';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Job')
                    ->formatStateUsing(fn (?string $state, FailedJob $record): string => substr($state ?? (string) $record->id, 0, 8) . '…')
                    ->tooltip(fn (FailedJob $record): ?string => $record->uuid)
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('connection')
                    ->label('Connection')
                    ->badge()
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('queue')
                    ->label('Queue')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'critical' => 'danger',
                        'default' => 'primary',
                        default => 'gray',
                    })
                    ->size('sm'),

                Tables\Columns\TextColumn::make('exception')
                    ->label('Exception')
                    ->formatStateUsing(fn (?string $state): string => \Illuminate\Support\Str::of((string) $state)->before("\n")->limit(80))
                    ->tooltip(fn (?string $state): string => \Illuminate\Support\Str::limit((string) $state, 300))
                    ->wrap()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('failed_at')
                    ->label('Failed')
                    ->dateTime('M j, H:i')
                    ->since()
                    ->tooltip(fn ($state): ?string => $state ? \Carbon\Carbon::parse($state)->toDayDateTimeString() : null)
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('queue')
                    ->options(fn (): array => FailedJob::query()->distinct()->pluck('queue', 'queue')->all()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view')
                        ->label('View exception')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading('Exception detail')
                        ->modalContent(fn (FailedJob $record): HtmlString => new HtmlString(
                            '<pre style="white-space:pre-wrap;word-break:break-word;font-size:0.75rem;max-height:60vh;overflow:auto;padding:1rem;border-radius:0.5rem;background:var(--gray-950,#0a0a0a);color:var(--gray-100,#f5f5f5);">'
                            . e($record->exception)
                            . '</pre>'
                        ))
                        ->modalSubmitAction(false),

                    Tables\Actions\Action::make('retry')
                        ->label('Retry')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function (FailedJob $record): void {
                            Artisan::call('queue:retry', ['id' => [(string) $record->uuid ?: (string) $record->id]]);
                            Notification::make()->title('Job re-dispatched')->success()->send();
                        }),

                    Tables\Actions\Action::make('delete')
                        ->label('Delete')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (FailedJob $record): void {
                            Artisan::call('queue:forget', ['id' => (string) $record->uuid ?: (string) $record->id]);
                            Notification::make()->title('Job deleted')->success()->send();
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('flushAll')
                    ->label('Flush All')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Permanently delete every failed job. This cannot be undone.')
                    ->visible(fn (): bool => FailedJob::query()->exists())
                    ->action(function (): void {
                        Artisan::call('queue:flush');
                        Notification::make()->title('All failed jobs cleared')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('Every queued job has processed successfully.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
