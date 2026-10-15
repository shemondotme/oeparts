<?php

namespace App\Services\Migration\Ux;

use App\Security\PackageAudit;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Services\Migration\Package\PackageFormat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

/**
 * A package that arrived through the browser's file field (small packages only) becomes a package of the import folder, through
 * the same checks as every other way in: a name that looks like a package name, the first eight bytes of a package, a size under
 * the cap, room on the disk. The file is written under a name that no listing shows (`.part`) and renamed into place in one step,
 * so a reader never sees half of it. It is NOT opened, decrypted or read any further here (that needs the passphrase and
 * happens in the next step, like for every other package).
 *
 * The cap is `migration.upload.small_max_mb` (10 MB), never above what Livewire's own temporary upload accepts (12 MB without a
 * config file); anything bigger goes in with FTP or SFTP, "From a link", or the command line.
 */
final class UploadedPackageIntake
{
    /** What Livewire's temporary upload accepts when the project has no config/livewire.php (rule `max:12288`, in KB). */
    private const LIVEWIRE_DEFAULT_MB = 12;

    /** @param  \Closure(): (int|float|false)|null  $freeBytes  only tests pass this */
    public function __construct(
        private readonly ImportInbox $inbox,
        private readonly PackageAudit $audit,
        private readonly ?\Closure $freeBytes = null,
    ) {}

    /** The largest file the browser upload takes, in bytes: the configured size, and never more than Livewire itself allows. */
    public static function maxBytes(): int
    {
        $mb = min(max(1, (int) config('migration.upload.small_max_mb', 10)), self::LIVEWIRE_DEFAULT_MB);

        return $mb * 1024 * 1024;
    }

    public static function maxMegabytes(): int
    {
        return intdiv(self::maxBytes(), 1024 * 1024);
    }

    /**
     * @param  string  $tempPath  where the browser's upload now is
     * @param  string  $clientName  the name the file had on the person's computer
     * @return string the name of the package in the import folder
     *
     * @throws MigrationException upload_too_large, package_not_oeparts (a name that is not a package name, or a file that is not
     *                            a package), preflight_disk, or package_incomplete when the file cannot be read
     */
    public function take(string $tempPath, string $clientName, ?int $adminId = null): string
    {
        $size = is_file($tempPath) ? (int) filesize($tempPath) : -1;

        if ($size < 0) {
            throw new MigrationException(MigrationErrorCode::PackageIncomplete, ['what' => 'the upload']);
        }

        if ($size > self::maxBytes()) {
            throw new MigrationException(MigrationErrorCode::UploadTooLarge, ['limit' => Number::fileSize(self::maxBytes())]);
        }

        $name = basename(str_replace('\\', '/', $clientName));

        if (! ImportInbox::isPackageName($name)) {
            throw new MigrationException(MigrationErrorCode::PackageNotOeparts, ['name' => mb_substr($name, 0, 80)]);
        }

        $source = @fopen($tempPath, 'rb');
        $magic = $source === false ? '' : (string) fread($source, strlen(PackageFormat::MAGIC));
        $source !== false && fclose($source);

        if ($magic !== PackageFormat::MAGIC) {
            throw new MigrationException(MigrationErrorCode::PackageNotOeparts, ['name' => mb_substr($name, 0, 80)]);
        }

        $directory = $this->inbox->directory();
        MigrationPaths::ensureDirectory($directory);

        $free = $this->freeBytes !== null ? ($this->freeBytes)() : @disk_free_space($directory);
        $needed = $size + 64 * 1024 * 1024;

        if ($free !== false && $free < $needed) {
            throw new MigrationException(MigrationErrorCode::PreflightDisk, ['needed' => Number::fileSize($needed), 'free' => Number::fileSize((int) $free)]);
        }

        $final = $this->freeName($directory, $name);
        $part = $directory.DIRECTORY_SEPARATOR.$final.'.part';

        try {
            if (! @copy($tempPath, $part) || (int) @filesize($part) !== $size) {
                throw new MigrationException(MigrationErrorCode::PackageIncomplete, ['what' => 'the copy into the import folder']);
            }

            if (! @rename($part, $directory.DIRECTORY_SEPARATOR.$final)) {
                throw new MigrationException(MigrationErrorCode::PackageIncomplete, ['what' => 'the move into the import folder']);
            }
        } finally {
            is_file($part) && @unlink($part);
        }

        @chmod($directory.DIRECTORY_SEPARATOR.$final, 0640);

        try {
            $this->audit->record('package.uploaded', null, ['package' => $final, 'size' => $size, 'by_admin' => $adminId]);
        } catch (\Throwable $e) {
            Log::channel('migration')->warning('The line for the upload of '.$final.' could not be written: '.$e::class);
        }

        return $final;
    }

    /** The name asked for, or the same name with a short suffix when the import folder already holds a package by that name. */
    private function freeName(string $directory, string $name): string
    {
        if (! file_exists($directory.DIRECTORY_SEPARATOR.$name) && ! file_exists($directory.DIRECTORY_SEPARATOR.$name.'.part')) {
            return $name;
        }

        $stem = substr($name, 0, -strlen('.oeparts'));

        do {
            $candidate = substr($stem, 0, 120).'-'.bin2hex(random_bytes(3)).'.oeparts';
        } while (file_exists($directory.DIRECTORY_SEPARATOR.$candidate));

        return $candidate;
    }
}
