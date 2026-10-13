<?php

namespace App\Services\Migration\Export;

use App\Security\PackagePathPolicy;
use App\Services\Migration\Groups\FileRoot;

/**
 * Counts the files an export would carry, without a request ever waiting for a million directory
 * entries: it works for a time budget and hands back a state to continue from (the caller keeps it in
 * the cache or the run). It uses the same AllowlistWalker as the export, so the number it shows is the
 * number the export will find.
 */
final class FileCounter
{
    public function __construct(private readonly AllowlistWalker $walker) {}

    /** The walker over the application's real disks. */
    public static function forApplication(): self
    {
        return new self(new AllowlistWalker(new PackagePathPolicy, [
            'public' => ['path' => storage_path('app/public'), 'prefix' => 'storage/app/public'],
            'local' => ['path' => storage_path('app/private'), 'prefix' => 'storage/app/private'],
        ]));
    }

    /**
     * @param  array<int, FileRoot>  $roots
     * @param  array<string, mixed>|null  $state  what the previous call returned, or null to start
     * @return array{state: array<string, mixed>, done: bool, count: int, bytes: int}
     */
    public function advance(array $roots, ?array $state, float $budgetSeconds = 2.0, ?\Closure $clock = null): array
    {
        $clock ??= static fn (): float => microtime(true);
        $state ??= ['walk' => $this->walker->start($roots)];
        $cursor = $state['walk'];
        $deadline = $clock() + max(0.05, $budgetSeconds);

        while (! $cursor['done'] && $clock() < $deadline) {
            $cursor = $this->walker->step($cursor, 500, static function (FileEntry $entry): void {});
        }

        $state['walk'] = $cursor;

        return ['state' => $state, 'done' => (bool) $cursor['done'], 'count' => $cursor['stats']['files'], 'bytes' => $cursor['stats']['bytes']];
    }
}
