<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Transport\RangeFileStreamer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * An administrator's own download of a package they exported. The page that offers it hands out a short-lived SIGNED address
 * (the same pattern as the refund images); the controller still checks the permission itself, and finds the file only by name
 * inside the exports folder. Unlike the zip-in-a-temp-file way of the backup page it streams the file as it is and honours
 * Range, so a browser or download manager can resume a multi-gigabyte package where it stopped.
 */
class MigrationPackageDownloadController extends Controller
{
    public const ROUTE = 'admin.migration.package-download';

    /** The signed, five-minute address of one package. */
    public static function url(string $packageName): string
    {
        return URL::temporarySignedRoute(self::ROUTE, now()->addMinutes(5), ['name' => $packageName]);
    }

    public function show(Request $request, string $name, PackageOutbox $outbox, RangeFileStreamer $streamer, PackageAudit $audit): Response
    {
        abort_unless((bool) auth('admin')->user()?->can('export migration'), 403);

        $path = $outbox->find($name);
        abort_if($path === null, 404);

        try {
            $audit->record('package.downloaded', null, ['package' => basename($path), 'by_admin' => auth('admin')->id(), 'range' => (string) $request->header('Range', '')]);
        } catch (\Throwable $e) {
            Log::warning('migration: a package download could not be written to the audit history', ['error' => $e->getMessage()]);
        }

        return $streamer->respond($path, basename($path), (int) filesize($path), $request->header('Range'));
    }
}
