<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Models\RefundRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-time (idempotent) migration of uploads that predate the Y/m date-
 * partitioning scheme into their dated folders. Safe to re-run — anything
 * already under a Y/m path is left alone.
 *
 * - media/*        (public disk, MediaFile-backed) — moved by MediaFile::created_at,
 *                   file_path/file_url updated. Verified zero content (blog/page)
 *                   embeds a raw /storage/media/... URL — every consumer goes
 *                   through the MediaFile relationship, so this is a safe rename.
 * - editor/*       (public disk) — moved by file mtime. Verified zero blog posts
 *                   or pages reference /storage/editor/... (the TinyMCE editor
 *                   component these were uploaded through is not wired into any
 *                   view), so there is no content to rewrite.
 * - refund-images/* (local/private disk) — moved by the RefundRequest's stored
 *                   upload metadata (or file mtime for legacy flat-string
 *                   entries), return_images updated in place.
 * - invoices/*     (local/private disk) — moved by file mtime. Nothing reads
 *                   these back (see GenerateInvoicePdf), so no reference to update.
 */
class MigrateLegacyUploads extends Command
{
    protected $signature = 'oeparts:storage:migrate-legacy-uploads {--dry-run : Report what would move without changing anything}';

    protected $description = 'Move pre-existing flat uploads (media, editor, refund-images, invoices) into their Y/m date-partitioned folders';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY RUN — no files or database rows will be changed.');
        }

        $this->migrateMedia();
        $this->migrateEditor();
        $this->migrateRefundImages();
        $this->migrateInvoices();

        return self::SUCCESS;
    }

    private function isDatePartitioned(string $path): bool
    {
        return (bool) preg_match('#/\d{4}/\d{2}/#', $path);
    }

    private function move(string $disk, string $from, string $to): bool
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($from)) {
            $this->line("  skip (source missing): {$from}");

            return false;
        }

        if ($storage->exists($to)) {
            $to = pathinfo($to, PATHINFO_DIRNAME) . '/' . pathinfo($to, PATHINFO_FILENAME)
                . '-' . substr(md5($from), 0, 8) . '.' . pathinfo($to, PATHINFO_EXTENSION);
        }

        $this->line("  {$from} -> {$to}");

        if (! $this->dryRun) {
            $storage->move($from, $to);
        }

        return true;
    }

    private function migrateMedia(): void
    {
        $this->info('Migrating media/ (MediaFile-backed, public disk)...');
        $moved = 0;

        foreach (MediaFile::query()->cursor() as $media) {
            if (! $media->file_path || $this->isDatePartitioned($media->file_path)) {
                continue;
            }

            $date = ($media->created_at ?? now())->format('Y/m');
            $target = "media/{$date}/" . basename($media->file_path);

            $finalTarget = $target;
            if (Storage::disk('public')->exists($target)) {
                $finalTarget = 'media/' . $date . '/' . pathinfo($target, PATHINFO_FILENAME)
                    . '-' . $media->id . '.' . pathinfo($target, PATHINFO_EXTENSION);
            }

            if (! $this->move('public', $media->file_path, $finalTarget)) {
                continue;
            }

            $moved++;

            if (! $this->dryRun) {
                $media->update([
                    'file_path' => $finalTarget,
                    'file_url' => Storage::disk('public')->url($finalTarget),
                ]);
            }
        }

        $this->info("media/: {$moved} file(s) migrated.");
    }

    private function migrateFlatDirectoryByMtime(string $disk, string $directory): int
    {
        $moved = 0;

        foreach (Storage::disk($disk)->allFiles($directory) as $path) {
            if ($this->isDatePartitioned($path)) {
                continue;
            }

            $mtime = Storage::disk($disk)->lastModified($path);
            $date = date('Y/m', $mtime ?: time());
            $target = "{$directory}/{$date}/" . basename($path);

            $this->move($disk, $path, $target);
            $moved++;
        }

        return $moved;
    }

    private function migrateEditor(): void
    {
        $this->info('Migrating editor/ (unreferenced by any content, public disk)...');
        $moved = $this->migrateFlatDirectoryByMtime('public', 'editor');
        $this->info("editor/: {$moved} file(s) migrated.");
    }

    private function migrateInvoices(): void
    {
        $this->info('Migrating invoices/ (disposable cache, private disk)...');
        $moved = $this->migrateFlatDirectoryByMtime('local', 'invoices');
        $this->info("invoices/: {$moved} file(s) migrated.");
    }

    private function migrateRefundImages(): void
    {
        $this->info('Migrating refund-images/ (RefundRequest-backed, private disk)...');
        $moved = 0;

        foreach (RefundRequest::query()->whereNotNull('return_images')->cursor() as $refund) {
            $images = $refund->return_images;
            $changed = false;

            foreach ($images as $index => $item) {
                $isObject = is_array($item);
                $path = $isObject ? ($item['path'] ?? null) : $item;

                if (! $path || $this->isDatePartitioned($path)) {
                    continue;
                }

                $uploadedAt = $isObject ? ($item['uploaded_at'] ?? null) : null;
                $date = $uploadedAt
                    ? \Illuminate\Support\Carbon::parse($uploadedAt)->format('Y/m')
                    : date('Y/m', Storage::disk('local')->lastModified($path) ?: time());

                $target = 'refund-images/' . $date . '/' . basename($path);

                if (! $this->move('local', $path, $target)) {
                    continue;
                }

                if ($isObject) {
                    $images[$index]['path'] = $target;
                } else {
                    $images[$index] = $target;
                }

                $changed = true;
                $moved++;
            }

            if ($changed && ! $this->dryRun) {
                $refund->update(['return_images' => $images]);
            }
        }

        $this->info("refund-images/: {$moved} file(s) migrated.");
    }
}
