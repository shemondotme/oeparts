<?php

namespace App\Services\Cleanup;

use FilesystemIterator;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Total size of a directory tree, bounded by a time budget — the overview must show "how much space is the
 * product-image folder using" without ever walking millions of files inside a web request.
 *
 * Symlinks are not followed (a link out of the tree must not make its target count, nor loop). When the budget
 * runs out the result is flagged INCOMPLETE and callers show "at least X".
 */
final class DirectorySizer
{
    /**
     * @return array{bytes:int, files:int, complete:bool}
     */
    public function size(string $dir, float $budgetSeconds): array
    {
        if (! is_dir($dir) || is_link($dir)) {
            return ['bytes' => 0, 'files' => 0, 'complete' => true];
        }

        $deadline = microtime(true) + max(0.05, $budgetSeconds);
        $bytes = 0;
        $files = 0;
        $counter = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD, // an unreadable sub-folder must not abort the whole measurement
            );

            foreach ($iterator as $entry) {
                if ($entry->isLink() || ! $entry->isFile()) {
                    continue;
                }

                $bytes += (int) @$entry->getSize();
                $files++;

                if ((++$counter & 0xFF) === 0 && microtime(true) >= $deadline) {
                    return ['bytes' => $bytes, 'files' => $files, 'complete' => false];
                }
            }
        } catch (Throwable $e) {
            Log::channel('cleanup')->notice("Could not fully measure {$dir}: ".$e->getMessage());

            return ['bytes' => $bytes, 'files' => $files, 'complete' => false];
        }

        return ['bytes' => $bytes, 'files' => $files, 'complete' => true];
    }
}
