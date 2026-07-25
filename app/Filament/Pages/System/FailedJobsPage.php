<?php

namespace App\Filament\Pages\System;

use App\Filament\Support\FailedJobPayloadDecoder;
use App\Models\FailedJob;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class FailedJobsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'system/failed-jobs-page';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?string $title = 'Failed Jobs';

    protected string $view = 'filament.pages.system.failed-jobs';

    protected ?string $subheading = 'Queue jobs that threw an exception. Retry to re-dispatch, or delete to discard.';

    /**
     * Memoized per-request: job-class counts (for group titles) and the
     * oldest failure timestamp (for the stat strip) — both read from one
     * shared, lightweight query instead of two.
     *
     * @var array{counts: array<string, int>, oldest: ?\Illuminate\Support\Carbon, retryableCount: int}|null
     */
    private ?array $aggregateCache = null;

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
        $counts = $this->aggregate()['counts'];

        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->poll('30s')
            ->paginated([25, 50, 100])
            ->groups([
                Group::make('job_class')
                    ->label('Job')
                    ->collapsible()
                    // No "Job: " label prefix on the group title — the class
                    // name + count already says what it is.
                    ->titlePrefixedWithLabel(false)
                    // ->column() here is for the SQL-level orderQuery() only
                    // (a real JSON-path expression Laravel's grammar can
                    // translate into an ORDER BY, keeping same-group rows
                    // adjacent) — it is NOT what determines the group
                    // key/title. Group's default key/title resolution (no
                    // closures set) falls back to Arr::get($record, column),
                    // which only understands '.' dot-notation, not JSON-path
                    // '->', so without the two closures below every row would
                    // silently resolve to a null key and collapse into one
                    // blank group.
                    ->column('payload->displayName')
                    ->getKeyFromRecordUsing(fn (FailedJob $record): string => $record->jobClassFqcn())
                    ->getTitleFromRecordUsing(function (FailedJob $record) use ($counts): HtmlString {
                        $count = $counts[$record->jobClassFqcn()] ?? 1;
                        $badge = self::classificationBadgeHtml($record->exception ?? '');

                        return new HtmlString(e($record->jobClassName()) . " ({$count})" . $badge);
                    })
                    // Native Filament group-description slot (renders below
                    // the heading) — the queue this group's jobs run on.
                    ->getDescriptionFromRecordUsing(fn (FailedJob $record): string => $record->queue ?? 'default'),
            ])
            ->defaultGroup('job_class')
            ->groupingSettingsHidden()
            ->recordClasses(fn (FailedJob $record): ?string => ($record->queue ?? 'default') === 'critical' ? 'op-row-critical' : null)
            ->columns([
                // No separate "Job" column — the class name is already shown
                // once in the group header (matches the approved mockup:
                // rows only carry queue / exception+path / time / actions).
                // Search still covers job name via the payload LIKE below.
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
                    ->formatStateUsing(fn (?string $state): string => Str::of((string) $state)->before("\n")->limit(80))
                    ->tooltip(fn (?string $state): string => Str::limit((string) $state, 300))
                    ->description(fn (FailedJob $record): string => $record->jobClassFqcn())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('payload', 'like', "%{$search}%")
                        ->orWhere('exception', 'like', "%{$search}%"))
                    ->wrap()
                    ->copyable()
                    ->copyMessage('Exception copied')
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
            ->recordActions([
                // Retry exposed as a standalone, always-visible icon — it's
                // the overwhelmingly common action on this page; View/Delete
                // stay tucked behind the "more" dropdown.
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [(string) $record->uuid ?: (string) $record->id]]);
                        Notification::make()->title('Job re-dispatched')->success()->send();
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view')
                        ->label('View exception')
                        ->icon('heroicon-o-eye')
                        ->color('gray')
                        ->modalHeading('Exception detail')
                        ->modalContent(fn (FailedJob $record): HtmlString => self::renderViewModal($record))
                        ->modalSubmitAction(false),

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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('retrySelected')
                        ->label('Retry selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $records->each(fn (FailedJob $record) => Artisan::call('queue:retry', [
                                'id' => [(string) $record->uuid ?: (string) $record->id],
                            ]));
                            Notification::make()->title($records->count() . ' job(s) retried')->success()->send();
                        }),

                    Tables\Actions\BulkAction::make('deleteSelected')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $records->each(fn (FailedJob $record) => Artisan::call('queue:forget', [
                                'id' => (string) $record->uuid ?: (string) $record->id,
                            ]));
                            Notification::make()->title($records->count() . ' job(s) deleted')->success()->send();
                        }),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('retryAll')
                    ->label('Retry All')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => 'This will push all ' . FailedJob::query()->count()
                        . " failed jobs back onto their original queues. If the underlying bug hasn't been fixed, they'll likely fail again and reappear here.")
                    ->visible(fn (): bool => FailedJob::query()->exists())
                    ->action(function (): void {
                        Artisan::call('queue:retry', ['id' => ['all']]);
                        Notification::make()->title('All failed jobs re-dispatched')->success()->send();
                    }),

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

    /**
     * Best-effort classification of an exception message: is retrying likely
     * to help (a transient/infra failure), or does it need a real code fix?
     * Deliberately returns null rather than forcing a guess when neither
     * keyword list matches — a forced badge is worse than no badge when the
     * heuristic genuinely doesn't know. Public so other places (e.g. the
     * Dashboard's FailedQueueJobsMonitor widget) can reuse it later without
     * a refactor, though there is exactly one caller today.
     *
     * @return array{label: string, color: string}|null
     */
    public static function classifyException(string $exception): ?array
    {
        $firstLine = Str::of($exception)->before("\n")->lower()->toString();

        $retryLikely = [
            'connection refused', 'could not resolve host', 'timed out', 'timeout',
            'temporarily unavailable', 'deadlock found', 'lock wait timeout',
            'server has gone away', 'too many connections', 'connection reset', 'broken pipe',
        ];

        foreach ($retryLikely as $needle) {
            if (str_contains($firstLine, $needle)) {
                return ['label' => 'Retry likely', 'color' => 'warning'];
            }
        }

        $needsFix = [
            'typeerror', 'argumentcounterror', 'undefined array key', 'undefined variable',
            'undefined method', 'call to a member function', 'undefined property',
            'division by zero', 'parseerror',
        ];

        foreach ($needsFix as $needle) {
            if (str_contains($firstLine, $needle)) {
                return ['label' => 'Needs code fix', 'color' => 'danger'];
            }
        }

        if (preg_match('/class .*? not found/i', $exception)) {
            return ['label' => 'Needs code fix', 'color' => 'danger'];
        }

        return null;
    }

    /** Rendered classification pill for a group header title, or '' when unclassified. */
    private static function classificationBadgeHtml(string $exception): string
    {
        $classification = self::classifyException($exception);

        if (! $classification) {
            return '';
        }

        $hex = $classification['color'] === 'warning' ? '#f59e0b' : '#ef4444';

        return ' <span title="Best-effort guess based on the exception message — not a guarantee." '
            . 'style="display:inline-block;font-size:0.65rem;font-weight:700;padding:0.05rem 0.45rem;'
            . 'border-radius:999px;background:' . $hex . '29;color:' . $hex . ';">'
            . e($classification['label']) . '</span>';
    }

    /**
     * @return array{total: int, distinctJobClasses: int, oldestAgo: ?string, retryableCount: int}
     */
    public function getFailedJobsSummary(): array
    {
        $aggregate = $this->aggregate();
        $counts = $aggregate['counts'];

        return [
            'total' => array_sum($counts),
            'distinctJobClasses' => count($counts),
            'oldestAgo' => $aggregate['oldest']?->diffForHumans(null, true),
            'retryableCount' => $aggregate['retryableCount'],
        ];
    }

    /**
     * @return array{counts: array<string, int>, oldest: ?\Illuminate\Support\Carbon, retryableCount: int}
     */
    private function aggregate(): array
    {
        if ($this->aggregateCache !== null) {
            return $this->aggregateCache;
        }

        $counts = [];
        $oldest = null;
        $retryableCount = 0;

        // Projected columns only, capped at 2000 rows as a safety valve —
        // this table is operationally small (a healthy app has dozens, not
        // thousands, of un-triaged failures sitting in it).
        FailedJob::query()
            ->select(['id', 'payload', 'failed_at', 'exception'])
            ->limit(2000)
            ->get()
            ->each(function (FailedJob $record) use (&$counts, &$oldest, &$retryableCount): void {
                $fqcn = $record->jobClassFqcn();
                $counts[$fqcn] = ($counts[$fqcn] ?? 0) + 1;

                if ($record->failed_at && (! $oldest || $record->failed_at->lt($oldest))) {
                    $oldest = $record->failed_at;
                }

                $classification = self::classifyException($record->exception ?? '');
                if ($classification && $classification['label'] === 'Retry likely') {
                    $retryableCount++;
                }
            });

        return $this->aggregateCache = ['counts' => $counts, 'oldest' => $oldest, 'retryableCount' => $retryableCount];
    }

    private static function renderViewModal(FailedJob $record): HtmlString
    {
        $decoded = FailedJobPayloadDecoder::decode($record);

        $section = fn (string $title, string $innerHtml): string => '<div style="margin-bottom:1rem;">'
            . '<div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#71717a;margin-bottom:0.4rem;">'
            . e($title) . '</div>' . $innerHtml . '</div>';

        $row = fn (string $label, string $value): string => '<div style="display:flex;gap:0.6rem;font-size:0.8rem;padding:0.15rem 0;">'
            . '<span style="color:#71717a;min-width:130px;flex-shrink:0;">' . e($label) . '</span>'
            . '<span style="font-family:ui-monospace,monospace;word-break:break-word;">' . e($value) . '</span></div>';

        $metaHtml = collect($decoded['meta'])->map(fn ($value, $label) => $row($label, (string) $value))->implode('');

        if ($decoded['arguments'] !== null) {
            $argsHtml = collect($decoded['arguments'])->map(fn ($value, $key) => $row($key, $value))->implode('');
        } else {
            $argsHtml = '<div style="font-size:0.8rem;color:#71717a;font-style:italic;">' . e($decoded['argumentsError']) . '</div>';
        }

        $exceptionHtml = '<pre style="white-space:pre-wrap;word-break:break-word;font-size:0.75rem;max-height:40vh;'
            . 'overflow:auto;padding:1rem;border-radius:0.5rem;background:#0a0a0a;color:#f5f5f5;">'
            . e($record->exception) . '</pre>';

        return new HtmlString(
            '<div>'
            . $section('Job', $metaHtml)
            . $section('Arguments (best effort)', $argsHtml)
            . $section('Exception', $exceptionHtml)
            . '</div>'
        );
    }
}
