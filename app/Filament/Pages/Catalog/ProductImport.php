<?php

namespace App\Filament\Pages\Catalog;

use App\Models\ProductImportRun;
use App\Services\Imports\ImportManager;
use App\Services\Imports\ProductImportTemplateService;
use App\Services\ProductImportService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

/**
 * Bulk Product Import — chunked, resumable (Bulk Import redesign).
 *
 * No Filament Schema/Form here on purpose: mirrors SystemUpdates.php, the
 * project's own precedent for an interactive poll-driven page (raw Livewire
 * properties + wire:click/wire:poll, native Livewire file upload). advance()
 * runs one FSM step per poll tick — see App\Services\Imports\ImportManager.
 */
class ProductImport extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Import Products';

    protected string $view = 'filament.pages.catalog.product-import';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $csvFile = null;

    public bool $updateExisting = false;

    public bool $running = false;

    public ?int $runId = null;

    /** @var array<string,mixed>|null */
    public ?array $progress = null;

    public function mount(): void
    {
        // Resume a running import if the admin reloaded / reopened the tab —
        // the FSM state lives in the DB row, not the browser.
        $running = ProductImportRun::where('status', ProductImportRun::STATUS_RUNNING)->latest('id')->first();

        if ($running) {
            $this->runId    = $running->id;
            $this->running  = true;
            $this->progress = $this->snapshot($running);
        }
    }

    public function requiredColumns(): array
    {
        return ProductImportService::REQUIRED_COLUMNS;
    }

    public function downloadQuickTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(
            fn () => print app(ProductImportTemplateService::class)->quickCsv(),
            'product-import-quick-template.csv',
        );
    }

    public function downloadFullTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(
            fn () => print app(ProductImportTemplateService::class)->fullCsv(),
            'product-import-full-template.csv',
        );
    }

    public function startImport(): void
    {
        abort_unless(auth('admin')->user()?->can('import products'), 403);

        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:'.((int) config('imports.max_upload_kb', 1024 * 1024)),
        ]);

        $disk      = (string) config('imports.disk', 'local');
        $directory = (string) config('imports.path', 'imports');
        $original  = $this->csvFile->getClientOriginalName();
        $diskPath  = $this->csvFile->storeAs($directory, Str::uuid().'.csv', $disk);

        try {
            $run = app(ImportManager::class)->start($diskPath, $disk, $original, (int) auth('admin')->id(), $this->updateExisting);
        } catch (\Throwable $e) {
            Notification::make()->title('Cannot start import')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->csvFile  = null;
        $this->runId    = $run->id;
        $this->running  = true;
        $this->progress = $this->snapshot($run);

        Notification::make()->title('Import started')->body('Do not close this window.')->success()->send();
    }

    /** Advance the FSM one step per poll. */
    public function pollImport(): void
    {
        if (! $this->running || ! $this->runId) {
            return;
        }
        abort_unless(auth('admin')->user()?->can('import products'), 403);

        $run = ProductImportRun::find($this->runId);
        if (! $run) {
            $this->running = false;

            return;
        }

        if (! $run->isTerminal()) {
            app(ImportManager::class)->advance($run);
            $run->refresh();
        }

        $this->progress = $this->snapshot($run);

        if ($run->isTerminal()) {
            $this->running = false;

            if ($run->status === ProductImportRun::STATUS_SUCCESS) {
                Notification::make()
                    ->title('Import complete')
                    ->body("{$run->created_count} created, {$run->updated_count} updated, {$run->skipped_count} skipped, {$run->error_count} errors.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import did not complete')
                    ->body($run->error ?? 'See the error details below.')
                    ->danger()
                    ->send();
            }
        }
    }

    /** @return array<string,mixed> */
    private function snapshot(ProductImportRun $run): array
    {
        return [
            'status'         => $run->status,
            'total_rows'     => $run->total_rows,
            'processed_rows' => $run->processed_rows,
            'created_count'  => $run->created_count,
            'updated_count'  => $run->updated_count,
            'skipped_count'  => $run->skipped_count,
            'error_count'    => $run->error_count,
            'errors'         => $run->errors ?? [],
            'error'          => $run->error,
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 50; // just above BulkUpdateLogPage (51)
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'Import Products';
    }

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('import products');
    }
}
