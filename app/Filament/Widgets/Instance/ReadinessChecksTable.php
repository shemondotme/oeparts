<?php

namespace App\Filament\Widgets\Instance;

use App\Filament\Pages\System\InstanceReadiness;
use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\ReadinessRunner;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Every launch check of the last run: what was looked at, what was found and what to do about it, worst first. The report is the
 * one the readiness runner keeps in storage/app/instance/readiness.json, the same one `oeparts:instance:readiness` writes, so the
 * page, the command and the release/go-live gate can never disagree about what "ready" means. The text comes from the checks
 * themselves; it names things and gives counts and never carries a setting value or an address of a person.
 */
class ReadinessChecksTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /** Worst first; the order inside one result is the order the checks run in. */
    private const SEVERITY = [
        ReadinessCheck::FAIL => 0,
        ReadinessCheck::PENDING => 1,
        ReadinessCheck::WARN => 2,
        ReadinessCheck::PASS => 3,
        ReadinessCheck::SKIP => 4,
    ];

    public static function canView(): bool
    {
        return InstanceReadiness::canAccess();
    }

    /** Re-render when the page starts, advances or finishes a run, or a release was refused (the page is another component). */
    #[On('instance-changed')]
    public function refreshChecks(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('instance.readiness.heading'))
            ->description(fn (): string => $this->summary())
            ->paginated(false)
            ->columns([
                TextColumn::make('status')->label(__('instance.readiness.col_status'))->badge()
                    ->state(fn (array $record): string => __('instance.readiness.status.'.$record['status']))
                    ->color(fn (array $record): string => self::color($record['status']))
                    ->icon(fn (array $record): string => self::icon($record['status'])),
                TextColumn::make('label')->label(__('instance.readiness.col_check'))->weight(FontWeight::Medium)->wrap()
                    ->state(fn (array $record): string => $record['label'])
                    ->description(fn (array $record): string => $record['message']),
                TextColumn::make('group')->label(__('instance.readiness.col_group'))->badge()->color('gray')
                    ->state(fn (array $record): string => $this->groupLabel($record['group'])),
                TextColumn::make('fix')->label(__('instance.readiness.col_fix'))->wrap()->placeholder('—')
                    ->state(fn (array $record): ?string => $this->fixText($record)),
            ])
            ->emptyStateHeading(__('instance.readiness.empty_heading'))
            ->emptyStateDescription(__('instance.readiness.empty_text'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function color(string $status): string
    {
        return match ($status) {
            ReadinessCheck::PASS => 'success',
            ReadinessCheck::WARN => 'warning',
            ReadinessCheck::FAIL => 'danger',
            ReadinessCheck::PENDING => 'info',
            default => 'gray',
        };
    }

    public static function icon(string $status): string
    {
        return match ($status) {
            ReadinessCheck::PASS => 'heroicon-o-check-circle',
            ReadinessCheck::WARN => 'heroicon-o-exclamation-triangle',
            ReadinessCheck::FAIL => 'heroicon-o-x-circle',
            ReadinessCheck::PENDING => 'heroicon-o-clock',
            default => 'heroicon-o-minus-circle',
        };
    }

    /** What to do about a check that is not fine; nothing for one that passed or was skipped. */
    private function fixText(array $record): ?string
    {
        if (! in_array($record['status'], [ReadinessCheck::FAIL, ReadinessCheck::WARN, ReadinessCheck::PENDING], true)) {
            return null;
        }

        $parts = array_filter([
            $record['fix'],
            $record['overridable'] && $record['status'] === ReadinessCheck::FAIL ? ucfirst(__('instance.readiness.waivable')).'.' : null,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function groupLabel(string $group): string
    {
        $key = 'instance.readiness.groups.'.$group;
        $label = __($key);

        return $label === $key ? ucfirst($group) : $label;
    }

    private function summary(): string
    {
        $report = $this->report();

        if ($report === null) {
            return __('instance.overview.never_checked');
        }

        $counts = $report->counts();
        $parts = [
            __('instance.overview.counts', [
                'pass' => $counts[ReadinessCheck::PASS], 'warn' => $counts[ReadinessCheck::WARN], 'fail' => $counts[ReadinessCheck::FAIL],
                'pending' => $counts[ReadinessCheck::PENDING], 'skip' => $counts[ReadinessCheck::SKIP],
            ]),
        ];

        if ($report->generatedAt !== '') {
            $parts[] = __('instance.overview.checked_when', ['when' => Carbon::parse($report->generatedAt)->diffForHumans()]).'.';
        }

        if (! $report->complete) {
            $parts[] = __('instance.readiness.unfinished', ['done' => count($report->checks)]);
        }

        return implode(' ', $parts);
    }

    private function report(): ?ReadinessReport
    {
        $report = app(ReadinessRunner::class)->last();

        return $report !== null && $report->checks !== [] ? $report : null;
    }

    /** @return array<string, array<string, mixed>> */
    private function records(): array
    {
        $report = $this->report();

        if ($report === null) {
            return [];
        }

        $rows = [];

        foreach ($report->checks as $position => $check) {
            $rows[] = ['position' => $position] + $check->toArray();
        }

        usort($rows, static fn (array $a, array $b): int => [self::SEVERITY[$a['status']] ?? 9, $a['position']] <=> [self::SEVERITY[$b['status']] ?? 9, $b['position']]);

        $records = [];

        foreach ($rows as $row) {
            $records[(string) ($row['key'] !== '' ? $row['key'] : 'check-'.$row['position'])] = $row;
        }

        return $records;
    }
}
