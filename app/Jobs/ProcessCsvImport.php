<?php

namespace App\Jobs;

use App\Services\ProductImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 1 hour for very large files (millions of rows).
     */
    public int $timeout = 3600;

    /**
     * Retry once if the job fails (e.g. DB timeout on large import).
     */
    public int $tries = 2;

    public function __construct(
        private readonly string $storagePath,
        private readonly int    $adminId,
        private readonly bool   $updateExisting,
    ) {
        $this->onQueue('default');
    }

    public function handle(ProductImportService $service): void
    {
        $absolutePath = Storage::path($this->storagePath);

        try {
            $result = $service->process($absolutePath, $this->adminId, $this->updateExisting);

            Log::info('CSV product import completed', [
                'admin_id'       => $this->adminId,
                'created'        => $result['created'],
                'updated'        => $result['updated'],
                'skipped'        => $result['skipped'],
                'error_count'    => count($result['rowErrors']),
            ]);
        } catch (\Throwable $e) {
            Log::error('CSV product import failed', [
                'admin_id' => $this->adminId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            Storage::delete($this->storagePath);
        }
    }
}
