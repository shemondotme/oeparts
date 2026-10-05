<?php

namespace App\Filament\Pages\System;

use App\Models\ActivityLog;
use App\Services\Cleanup\SchemaAuditor;
use App\Services\Updates\GitUpdater;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin Cleanup dashboard (Module 21 family) — built after a real production
 * incident: a git-managed install's update was killed mid-git_checkout by the
 * host's own request-time limit, leaving dev-only files (tests/, phpstan.neon,
 * …) sitting on the live site with no way for an operator to see or fix that
 * short of SSH and a hand-written script (see InterruptedUpdateResumer /
 * GitUpdater::stripDevFilesFromWorkingTree() / oeparts:update:strip-dev-files
 * for the automated side of that same fix).
 *
 * Two genuinely different risk profiles, handled differently on purpose:
 *   - Dev-only files: a closed, EXACT, already-tested set (config('updates.
 *     build.exclude'), the same list the release pipeline itself uses) — safe
 *     to actually delete, behind a preview + confirmation.
 *   - Database tables: there is no reliable way to tell "a table nobody
 *     needs" from "a table a human created on purpose." SchemaAuditor only
 *     ever REPORTS what it finds; this page has no button anywhere that
 *     drops a table or a row. The user explicitly chose this scope over
 *     an automatic one.
 *
 * Already-running scheduled maintenance/purge tasks (OTP, cart, log, invoice-
 * cache, refund-image cleanup, stale backup/update reclaim) are NOT
 * duplicated here — ScheduledTasksPage already lists every one of them with
 * its own "Run Now", this page just points to it.
 */
class CleanupDashboard extends Page
{
    protected static ?string $slug = 'system/cleanup-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    protected static ?string $title = 'Cleanup';

    protected string $view = 'filament.pages.system.cleanup-dashboard';

    protected ?string $subheading = 'Dev-only files an interrupted update may have left behind, and a read-only audit of the database schema. Nothing here deletes a database table or row automatically.';

    public static function getNavigationSort(): ?int
    {
        return 41;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('manage cleanup');
    }

    /** @var list<string>|null null = not scanned yet this page load */
    public ?array $devFilesPreview = null;

    public ?string $devFilesScannedAt = null;

    /** @var array{expected_missing: list<string>, unexpected: list<string>}|null */
    public ?array $schemaAudit = null;

    public ?string $schemaAuditedAt = null;

    public function mount(): void
    {
        // Eager, cheap (a handful of is_dir()/is_file() calls) — an operator
        // shouldn't have to click "Scan" just to see whether anything is wrong.
        if (app(GitUpdater::class)->isGitManaged()) {
            $this->scanDevFiles();
        }
    }

    public function isGitManaged(): bool
    {
        return app(GitUpdater::class)->isGitManaged();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runSchemaAudit')
                ->label('Run Database Audit')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->action('runSchemaAudit'),
        ];
    }

    public function scanDevFiles(): void
    {
        $this->devFilesPreview = app(GitUpdater::class)->previewDevFilesInWorkingTree();
        $this->devFilesScannedAt = now()->toIso8601String();
    }

    public function removeDevFiles(): void
    {
        abort_unless((bool) auth('admin')->user()?->can('manage cleanup'), 403);

        $removed = app(GitUpdater::class)->stripDevFilesFromWorkingTree();

        if ($removed !== []) {
            $this->logAction('cleanup_dev_files', 'Removed '.count($removed).' dev-only path(s): '.implode(', ', $removed));
        }

        $this->scanDevFiles(); // refresh — should now report empty

        Notification::make()
            ->title($removed === [] ? 'Nothing to remove' : count($removed).' path(s) removed')
            ->success()
            ->send();
    }

    public function runSchemaAudit(): void
    {
        $this->schemaAudit = app(SchemaAuditor::class)->audit();
        $this->schemaAuditedAt = now()->toIso8601String();

        $findings = count($this->schemaAudit['unexpected']) + count($this->schemaAudit['expected_missing']);

        $this->logAction('schema_audit_run', $findings === 0
            ? 'Database schema audit run: no findings.'
            : 'Database schema audit run: '.$findings.' finding(s) — see the Cleanup dashboard.');

        Notification::make()
            ->title($findings === 0 ? 'No findings' : $findings.' finding(s) — see below')
            ->{$findings === 0 ? 'success' : 'warning'}()
            ->send();
    }

    protected function logAction(string $action, string $description): void
    {
        ActivityLog::create([
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'model_type' => self::class,
            'model_id' => null,
            'old_values' => [],
            'new_values' => ['description' => $description],
            'ip_address' => request()->ip(),
        ]);
    }
}
