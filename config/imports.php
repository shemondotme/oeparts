<?php

use App\Services\Imports\Stages\ImportRowsStage;
use App\Services\Imports\Stages\ValidateHeaderStage;

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
    // — public/.htaccess raises these under mod_php only; PHP-FPM hosts (most
    // nginx setups, and increasingly Apache too) need
    // deploy/php-fpm/oeparts-pool-overrides.conf instead, and nginx additionally
    // needs its own client_max_body_size raised (see deploy/nginx/oeparts.conf).
    'max_upload_kb' => (int) env('OE_IMPORT_MAX_UPLOAD_KB', 1024 * 1024), // 1GB

    // Disk + subpath where uploaded CSVs are staged for processing.
    'disk' => env('OE_IMPORT_DISK', 'local'),
    'path' => 'imports',

    // Redirect CSV import (ImportRedirectsFromCsv) is a DELIBERATELY separate,
    // much smaller cap than max_upload_kb above — unlike the chunked/
    // resumable product importer, it's a single non-resumable pass with a
    // fixed 300s job timeout and up to ~10 DB queries per row (existing-row
    // lookup, reverse-pair check, RedirectLoopDetector's chain walk), so a
    // multi-hundred-MB file risks a mid-timeout SIGKILL rather than a clean
    // failure. 2MB comfortably covers the "a few thousand rows" scale this
    // job is actually built for.
    'redirects_max_upload_kb' => (int) env('OE_IMPORT_REDIRECTS_MAX_UPLOAD_KB', 2048),

    // Ordered pipeline of ImportStage classes per profile — same seam as
    // config('backup.stages'). Only one profile today; kept as a map for
    // consistency and in case a distinct pipeline is ever needed.
    'stages' => [
        'product_import' => [
            ValidateHeaderStage::class,
            ImportRowsStage::class,
        ],
    ],
];
