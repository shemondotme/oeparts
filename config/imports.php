<?php

/*
|--------------------------------------------------------------------------
| Bulk Product Import
|--------------------------------------------------------------------------
| Chunked, resumable CSV import — mirrors the Backup Engine's FSM shape
| (config/backup.php) so a 100-500MB / multi-million-row file is processed
| as many small steps (one per browser poll) instead of one long request.
*/

return [

    // Rows read + processed per FSM step (ImportRowsStage). Tune down on very
    // slow hosting, up if steps are finishing well under max_execution_time.
    'batch_size' => (int) env('OE_IMPORT_BATCH_SIZE', 500),

    // Upload cap in KB, fed into Filament's FileUpload::maxSize(). Actual
    // uploads are also bounded by the host's upload_max_filesize/post_max_size
    // (see public/.htaccess) — raise both together.
    'max_upload_kb' => (int) env('OE_IMPORT_MAX_UPLOAD_KB', 1024 * 1024), // 1GB

    // Disk + subpath where uploaded CSVs are staged for processing.
    'disk' => env('OE_IMPORT_DISK', 'local'),
    'path' => 'imports',

    // Ordered pipeline of ImportStage classes per profile — same seam as
    // config('backup.stages'). Only one profile today; kept as a map for
    // consistency and in case a distinct pipeline is ever needed.
    'stages' => [
        'product_import' => [
            \App\Services\Imports\Stages\ValidateHeaderStage::class,
            \App\Services\Imports\Stages\ImportRowsStage::class,
        ],
    ],
];
