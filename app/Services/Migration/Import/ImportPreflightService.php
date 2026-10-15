<?php

namespace App\Services\Migration\Import;

use App\Services\Backup\BackupLock;
use App\Services\Migration\Export\ExportDisks;
use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageReader;
use App\Services\Updates\PreflightCheck;
use App\Services\Updates\PreflightReport;
use App\Services\Updates\UpdateChecker;
use App\Support\Database\DatabaseFlavour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Everything that can be checked about an import WITHOUT changing the site, in one report: is the package whole and
 * made for this release, can this server do the work, how much is there to replace and how much room does it need,
 * and what must the person say "yes, I know" to before it starts.
 *
 * A FAIL blocks. A WARN needs the person's acknowledgement when its `meta.ack` names a key (the populated-site
 * replace, a major version jump); other warnings are shown but need nothing. Nothing here writes: the only thing
 * touched is a throw-away probe table that is dropped again in a `finally`, to learn what the database user may do.
 *
 * Pure detection: every check is separately callable and tested. It uses the value objects the update pre-flight uses
 * so the two screens look alike.
 */
class ImportPreflightService
{
    private const MIN_PHP = '8.3.0';

    private const MIN_MAX_ALLOWED_PACKET = 2 * 1024 * 1024;

    public function __construct(
        private readonly TargetFreshness $freshness,
        private readonly MigrationLedgerReconciler $ledger,
        private readonly BackupLock $lock,
        private readonly GroupRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $options  door (cli|admin|installer), launch (live|staging), groups, include_secrets, extra_admin
     */
    public function run(ImportState $state, PackageReader $reader, array $options = []): PreflightReport
    {
        $target = $this->freshness->assess();
        $comparison = $this->ledger->compare((array) ($reader->meta()['migrations'] ?? []), $reader->meta()['app_version'] ?? null, $this->targetVersion());

        return new PreflightReport([
            $this->checkPackage($state),
            $this->checkFormat($reader),
            $this->checkAppVersion($comparison),
            $this->checkMigrationLedger($comparison),
            $this->checkPhp($reader),
            $this->checkExtensions($reader),
            $this->checkDbEngine(),
            $this->checkCollation(),
            $this->checkMaxAllowedPacket(),
            $this->checkPrivileges(),
            $this->checkMemory(),
            $this->checkTargetState($target, (string) ($options['door'] ?? 'cli')),
            $this->checkRecoveryPoint($target),
            $this->checkDisk($state, $target),
            $this->checkLockFree($state),
            $this->checkAppKey(),
            $this->checkWritable(),
            $this->checkSymlink(),
            $this->checkExistingFiles($state),
            $this->checkAdminLoginPossible($reader, $options),
            $this->checkPackageIdentity($state),
            $this->checkQueueGuard(),
            $this->checkPendingJobs(),
            $this->checkLaunchMode((string) ($options['launch'] ?? 'live')),
            $this->checkEta($state),
        ]);
    }

    // ---- the package ----------------------------------------------------------------------------------------

    public function checkPackage(ImportState $state): PreflightCheck
    {
        if (! $state->flag('locate.done')) {
            return PreflightCheck::fail('package', 'The package is open', 'The package has not been opened with its passphrase.');
        }

        if (($state->package['verified'] ?? false) !== true) {
            return PreflightCheck::fail('integrity', 'The package is intact', 'The package has not been checked all the way through yet.');
        }

        return PreflightCheck::pass('package', 'The package is open and every part of it is intact', 'Fingerprint '.($state->package['fingerprint'] ?? ''));
    }

    public function checkFormat(PackageReader $reader): PreflightCheck
    {
        $h = $reader->header();

        return $h->formatMajor > PackageFormat::FORMAT_MAJOR
            ? PreflightCheck::fail('format_version', 'The package format is one this site understands', "This file uses format {$h->formatMajor}.{$h->formatMinor}, newer than this site reads.")
            : PreflightCheck::pass('format_version', 'The package format is one this site understands', "Format {$h->formatMajor}.{$h->formatMinor}");
    }

    public function checkAppVersion(LedgerComparison $comparison): PreflightCheck
    {
        $label = 'The package comes from a release this site can take';

        if ($comparison->packageIsNewerRelease()) {
            return PreflightCheck::fail('app_version', $label, "The package was made by release {$comparison->packageVersion}, newer than this site ({$comparison->targetVersion}). Update this site first.");
        }

        if ($comparison->isMajorJump()) {
            return PreflightCheck::warn('app_version', $label, "The package was made by release {$comparison->packageVersion} and this site runs {$comparison->targetVersion}: a different major version.", ['ack' => 'version_major_jump']);
        }

        if ($comparison->versionsDiffer()) {
            return PreflightCheck::warn('app_version', $label, "The package was made by release {$comparison->packageVersion}; this site runs {$comparison->targetVersion}. The database is the same, so this is only a note.");
        }

        return PreflightCheck::pass('app_version', $label, (string) $comparison->targetVersion);
    }

    public function checkMigrationLedger(LedgerComparison $comparison): PreflightCheck
    {
        $label = 'Both sites have the same database structure';

        if ($comparison->packageLedgerIsEmpty()) {
            return PreflightCheck::fail('migration_ledger', $label, 'The package does not say which database migrations its site had run, so it cannot be matched with this site. Export it again with a current release.', $comparison->toArray());
        }

        if (! $comparison->identical()) {
            $which = $comparison->targetIsOlder() ? 'This site is behind the package' : 'This site is ahead of the package';

            return PreflightCheck::fail('migration_ledger', $label, "{$which}: the two sites ran different database migrations. Make both sites the same release first, then export and import again.", $comparison->toArray());
        }

        if ($comparison->retired !== []) {
            $n = count($comparison->retired);
            $names = implode(', ', array_slice($comparison->retired, 0, 8)).($n > 8 ? ', ...' : '');

            return PreflightCheck::warn('migration_ledger', $label, "{$n} migrations of the other site were retired in this release ({$names}). Nothing else differs, so this is only a note you must confirm.", $comparison->toArray() + ['ack' => 'migrations_retired']);
        }

        return PreflightCheck::pass('migration_ledger', $label, $comparison->packageCount.' migrations on both sites');
    }

    // ---- this server ------------------------------------------------------------------------------------------

    public function checkPhp(PackageReader $reader): PreflightCheck
    {
        $label = 'PHP version';

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            return PreflightCheck::fail('php_version', $label, 'This server runs PHP '.PHP_VERSION.'; at least '.self::MIN_PHP.' is needed.');
        }

        $from = (string) ($reader->meta()['php_version'] ?? '');

        if ($from !== '' && version_compare(implode('.', array_slice(explode('.', $from), 0, 2)), implode('.', array_slice(explode('.', PHP_VERSION), 0, 2)), '>')) {
            return PreflightCheck::warn('php_version', $label, "The package's site ran PHP {$from}; this server runs ".PHP_VERSION.'.');
        }

        return PreflightCheck::pass('php_version', $label, PHP_VERSION);
    }

    public function checkExtensions(PackageReader $reader): PreflightCheck
    {
        $label = 'PHP extensions';
        $needed = ['openssl', 'zlib', 'mbstring', 'json', 'fileinfo', 'pdo'];

        if (DB::connection()->getDriverName() === 'mysql') {
            $needed[] = 'pdo_mysql';
        }

        if ($reader->header()->kdfId === PackageFormat::KDF_ARGON2ID) {
            $needed[] = 'sodium';
        }

        $missing = array_values(array_filter($needed, static fn (string $e): bool => ! extension_loaded($e)));

        if ($missing === []) {
            return PreflightCheck::pass('extensions', $label);
        }

        $message = 'This server is missing the PHP extension(s): '.implode(', ', $missing).'.';

        if (in_array('sodium', $missing, true)) {
            $message .= ' This package was locked with Argon2id, which needs the sodium extension. Enable it, or export the package again from a server that can use PBKDF2.';
        }

        return PreflightCheck::fail('extensions', $label, $message, ['missing' => $missing]);
    }

    public function checkDbEngine(): PreflightCheck
    {
        $label = 'Database server';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return PreflightCheck::warn('db_engine', $label, 'SQLite is only used for tests. A real site runs on MySQL or MariaDB.');
        }

        $version = $this->databaseVersion();

        if ($driver === 'mysql' && ! DatabaseFlavour::supportsNgramParser(DB::connection())) {
            return PreflightCheck::warn('db_engine', $label, 'Product search will use the slower LIKE fallback because this server has no ngram FULLTEXT parser (MariaDB, or MySQL without it).', ['version' => $version]);
        }

        return PreflightCheck::pass('db_engine', $label, $version);
    }

    public function checkCollation(): PreflightCheck
    {
        $label = 'Database character set';

        if (DB::connection()->getDriverName() !== 'mysql') {
            return PreflightCheck::pass('collation_charset', $label);
        }

        try {
            $row = DB::selectOne('SELECT @@character_set_database AS charset');
            $charset = (string) ($row->charset ?? '');
        } catch (\Throwable) {
            return PreflightCheck::warn('collation_charset', $label, 'The database character set could not be read.');
        }

        return str_starts_with($charset, 'utf8mb4')
            ? PreflightCheck::pass('collation_charset', $label, $charset)
            : PreflightCheck::warn('collation_charset', $label, "The database uses {$charset}; accented and non-Latin text needs utf8mb4.");
    }

    public function checkMaxAllowedPacket(): PreflightCheck
    {
        $label = 'Largest statement the database accepts';

        if (DB::connection()->getDriverName() !== 'mysql') {
            return PreflightCheck::pass('max_allowed_packet', $label);
        }

        try {
            $size = (int) (DB::selectOne('SELECT @@max_allowed_packet AS size')->size ?? 0);
        } catch (\Throwable) {
            return PreflightCheck::warn('max_allowed_packet', $label, 'max_allowed_packet could not be read.');
        }

        return $size < self::MIN_MAX_ALLOWED_PACKET
            ? PreflightCheck::fail('max_allowed_packet', $label, "max_allowed_packet is {$size} bytes; at least ".self::MIN_MAX_ALLOWED_PACKET.' are needed to load rows in batches.')
            : PreflightCheck::pass('max_allowed_packet', $label, $size.' bytes');
    }

    /** Learn what the database user may do by doing it to a table of its own, always removed again. */
    public function checkPrivileges(): PreflightCheck
    {
        $label = 'Database permissions';
        $table = 'oe_import_probe_'.Str::lower(Str::random(8));
        $done = [];

        try {
            Schema::create($table, static function ($t): void {
                $t->id();
                $t->string('v', 20)->nullable();
            });
            $done[] = 'CREATE';

            Schema::table($table, static function ($t): void {
                $t->string('w', 20)->nullable();
            });
            $done[] = 'ALTER';

            DB::table($table)->insert(['v' => 'x']);
            $done[] = 'INSERT';

            DB::table($table)->where('v', 'x')->delete();
            $done[] = 'DELETE';

            DB::table($table)->truncate();
            $done[] = 'TRUNCATE';
        } catch (\Throwable $e) {
            return PreflightCheck::fail('privileges', $label, 'The database user may not do everything the import needs (stopped after: '.(implode(', ', $done) ?: 'nothing').'). Give it CREATE, ALTER, INSERT, DELETE, DROP and TRUNCATE on this database.', ['done' => $done]);
        } finally {
            try {
                Schema::dropIfExists($table);
            } catch (\Throwable) {
                // Swept by the next run: the probe table has a recognisable name.
            }
        }

        return PreflightCheck::pass('privileges', $label, implode(', ', $done));
    }

    public function checkMemory(): PreflightCheck
    {
        $label = 'PHP memory';
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit === -1 || $limit >= 256 * 1024 * 1024) {
            return PreflightCheck::pass('memory', $label, $limit === -1 ? 'unlimited' : (string) ini_get('memory_limit'));
        }

        return PreflightCheck::warn('memory', $label, 'PHP may use only '.ini_get('memory_limit').'. At least 256M is recommended, more if the package was locked with Argon2id.');
    }

    // ---- what there is to replace -------------------------------------------------------------------------------

    public function checkTargetState(TargetAssessment $target, string $door): PreflightCheck
    {
        $label = 'What this site holds now';
        $meta = $target->toArray();

        if ($door === 'installer' && ! $target->class->installerMayReplace()) {
            return PreflightCheck::fail('target_state', $label, 'This site has been used (it holds orders, customers or other business records). The installer can only move a site in where nobody has used it; use the admin panel or the command line instead.', $meta);
        }

        if ($target->class === TargetClass::Populated) {
            $counts = collect($target->businessRows)->map(fn (int $n, string $t): string => "{$n} {$t}")->implode(', ');

            return PreflightCheck::warn('target_state', $label, 'This site has been used and ALL of it will be replaced'.($counts !== '' ? " ({$counts})" : '').'.', $meta + ['ack' => 'target_populated']);
        }

        return PreflightCheck::pass('target_state', $label, $target->class->value.' ('.$target->totalRows.' rows)', $meta);
    }

    public function checkRecoveryPoint(TargetAssessment $target): PreflightCheck
    {
        $label = 'A way back is possible';
        $warnAt = max(1, (int) config('migration.import.recovery_warn_rows', 100_000));

        if ($target->exceedsRecoveryCap()) {
            $seconds = (int) ceil($target->totalRows / max(1, (int) config('migration.import.recovery_rows_per_second', 10_000)));

            return PreflightCheck::fail('recovery_point', $label, "This site holds {$target->totalRows} rows, more than the way-back copy may hold ({$target->rowCap}). Raise migration.import.recovery_max_rows to ".$target->totalRows.'. At this size the copy needs about '.$this->human($this->estimateBytes($target)).' of disk and about '.max(1, intdiv($seconds, 60)).' minute(s).', ['rows' => $target->totalRows, 'cap' => $target->rowCap]);
        }

        if ($target->totalRows > $warnAt) {
            return PreflightCheck::warn('recovery_point', $label, "This site holds {$target->totalRows} rows; saving the way back will take a while and about ".$this->human($this->estimateBytes($target)).' of disk.');
        }

        return PreflightCheck::pass('recovery_point', $label, $target->totalRows.' rows');
    }

    public function checkDisk(ImportState $state, TargetAssessment $target): PreflightCheck
    {
        $label = 'Disk space';
        $size = (int) ($state->package['identity']['size'] ?? 0);
        $floor = (int) config('migration.import.min_free_bytes', 256 * 1024 * 1024);
        $needed = max($floor, 2 * $size) + $this->estimateBytes($target);
        $free = @disk_free_space(storage_path());

        if ($free === false) {
            return PreflightCheck::warn('disk', $label, 'The free disk space could not be read.');
        }

        return $free < $needed
            ? PreflightCheck::fail('disk', $label, 'About '.$this->human($needed).' of free disk space is needed and '.$this->human((int) $free).' is free.', ['needed' => $needed, 'free' => (int) $free])
            : PreflightCheck::pass('disk', $label, $this->human((int) $free).' free, '.$this->human($needed).' needed');
    }

    public function checkLockFree(ImportState $state): PreflightCheck
    {
        $label = 'Nothing else is running';

        if (! $this->lock->isLocked()) {
            return PreflightCheck::pass('lock_free', $label);
        }

        $owner = (string) ($this->lock->owner()['owner'] ?? 'unknown');

        return $owner === 'import:'.$state->id
            ? PreflightCheck::pass('lock_free', $label)
            : PreflightCheck::fail('lock_free', $label, "A backup, update or restore is in progress ({$owner}). Wait for it to finish.", ['owner' => $owner]);
    }

    public function checkAppKey(): PreflightCheck
    {
        $label = 'This site has its own encryption key';

        return trim((string) config('app.key')) === ''
            ? PreflightCheck::fail('app_key', $label, 'APP_KEY is empty. Run php artisan key:generate first.')
            : PreflightCheck::pass('app_key', $label);
    }

    public function checkWritable(): PreflightCheck
    {
        $label = 'Folders the import writes to';
        $paths = [storage_path(), storage_path('app/public'), storage_path('app/private'), base_path('bootstrap/cache'), storage_path('app')];
        $bad = [];

        foreach ($paths as $path) {
            if (is_dir($path) && ! $this->writable($path)) {
                $bad[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        if (is_file(base_path('.env')) && ! is_writable(base_path('.env'))) {
            $bad[] = '.env';
        }

        return $bad === []
            ? PreflightCheck::pass('writable', $label)
            : PreflightCheck::fail('writable', $label, 'The web server cannot write to: '.implode(', ', $bad).'.', ['paths' => $bad]);
    }

    /** The storage root counts: the closing steps (installed.lock, wizard file) write there after the site is live. */
    protected function writable(string $path): bool
    {
        return is_writable($path);
    }

    public function checkSymlink(): PreflightCheck
    {
        $label = 'Public file link';

        if (! function_exists('symlink')) {
            return PreflightCheck::warn('symlink', $label, 'This server does not allow symbolic links, so public/storage cannot be created. Uploaded images may not show until it is.');
        }

        $public = public_path('storage');

        return is_dir($public) && ! is_link($public)
            ? PreflightCheck::warn('symlink', $label, 'public/storage is a real folder, not a link to storage/app/public.')
            : PreflightCheck::pass('symlink', $label);
    }

    /** Files this import would overwrite are journalled so a rollback can put them back; others stay and are reported. */
    public function checkExistingFiles(ImportState $state): PreflightCheck
    {
        $label = 'Files already on this site';
        $count = 0;

        foreach ($this->registry->fileRootsOf(array_map(static fn (string $g): GroupKey => GroupKey::from($g), (array) ($state->package['groups'] ?? ['core']))) as $root) {
            $dir = ExportDisks::application()[$root->disk]['path'].DIRECTORY_SEPARATOR.$root->path;

            if (! is_dir($dir)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile() && ++$count >= 5000) {
                    break 2;
                }
            }
        }

        return $count === 0
            ? PreflightCheck::pass('existing_storage_files', $label, 'none')
            : PreflightCheck::warn('existing_storage_files', $label, ($count >= 5000 ? 'At least 5000 files' : "{$count} file(s)").' already exist in the folders the import fills. Files with the same name are replaced (and saved so a rollback can restore them); other files stay and are listed in the report.');
    }

    /** After the import the package's admins are the only ones: at least one must be able to sign in. */
    public function checkAdminLoginPossible(PackageReader $reader, array $options): PreflightCheck
    {
        $label = 'Someone can sign in to the new site';

        if (! empty($options['extra_admin'])) {
            return PreflightCheck::pass('admin_login_possible', $label, 'An extra administrator will be created.');
        }

        $usable = 0;

        foreach ($reader->entries() as $entry) {
            if (($entry['kind'] ?? '') !== 'db' || ($entry['attrs']['table'] ?? '') !== 'admins') {
                continue;
            }

            $usable += $this->usableAdmins($reader->readString((string) $entry['name'], 32 * 1024 * 1024));
        }

        return $usable > 0
            ? PreflightCheck::pass('admin_login_possible', $label, "{$usable} administrator(s) can sign in")
            : PreflightCheck::fail('admin_login_possible', $label, 'The package holds no active super administrator with a password, so nobody could sign in afterwards. Add an extra administrator to the import, or export again from a site that has one.');
    }

    public function checkPackageIdentity(ImportState $state): PreflightCheck
    {
        $label = 'The package file has not changed';

        try {
            PackageIdentity::assertUnchanged((string) ($state->package['path'] ?? ''), (array) ($state->package['identity'] ?? []));
        } catch (\Throwable) {
            return PreflightCheck::fail('package_identity', $label, 'The file changed after it was checked. Start again; nothing was changed.');
        }

        return PreflightCheck::pass('package_identity', $label);
    }

    public function checkQueueGuard(): PreflightCheck
    {
        $label = 'Background workers';
        $driver = (string) config('queue.default');

        if ($driver === 'sync') {
            return PreflightCheck::pass('queue_guard', $label, 'No queue workers (sync).');
        }

        return (new MigrationWorkerProbe)->isFresh()
            ? PreflightCheck::pass('queue_guard', $label, "Workers on {$driver} will be held while the site is replaced.")
            : PreflightCheck::warn('queue_guard', $label, "The queue runs on {$driver} but no worker has answered recently. If you have one, it will be held while the site is replaced.");
    }

    public function checkPendingJobs(): PreflightCheck
    {
        $label = 'Jobs waiting in the queue';

        try {
            $count = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        } catch (\Throwable) {
            $count = 0;
        }

        return $count === 0
            ? PreflightCheck::pass('pending_jobs', $label)
            : PreflightCheck::warn('pending_jobs', $label, "{$count} job(s) are queued. They stay queued and run against the NEW data once the import is done.");
    }

    public function checkLaunchMode(string $launch): PreflightCheck
    {
        $label = 'How the new site starts';

        return $launch === 'staging'
            ? PreflightCheck::pass('launch_mode', $label, 'As a staging copy: it sends no mail, takes no real payments and hides from search engines until you go live.')
            : PreflightCheck::pass('launch_mode', $label, 'As the live site, once the launch checks pass.');
    }

    public function checkEta(ImportState $state): PreflightCheck
    {
        $rows = array_sum((array) ($state->package['tables'] ?? []));
        $rate = max(1, (int) config('migration.import.throughput_rows_per_second', 1000));
        $seconds = (int) ceil($rows / $rate);
        $worst = (int) ceil(array_sum(array_map('intval', (array) ($state->package['tables'] ?? []))) / max(1, (int) config('migration.import.rewrite_rows_per_second', 400)));

        return PreflightCheck::pass('import_eta', 'Expected time', 'About '.max(1, intdiv($seconds, 60)).' minute(s) for '.number_format($rows).' rows (an estimate). Changing the old site address inside your records can add time: up to about '.max(1, intdiv($worst, 60)).' minute(s) if every record names it.', ['rows' => $rows, 'seconds' => $seconds, 'rewrite_worst_seconds' => $worst]);
    }

    // ---- helpers ---------------------------------------------------------------------------------------------------

    private function targetVersion(): ?string
    {
        $version = app(UpdateChecker::class)->currentVersion();

        return $version === 'unknown' ? null : $version;
    }

    private function databaseVersion(): string
    {
        try {
            return (string) (DB::selectOne('SELECT VERSION() AS v')->v ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** Count the admins in one decoded `admins` segment that are active super administrators with a bcrypt hash. */
    private function usableAdmins(string $text): int
    {
        $lines = array_values(array_filter(explode("\n", $text), static fn (string $l): bool => $l !== ''));
        $header = json_decode((string) array_shift($lines), true);
        $columns = is_array($header) ? array_flip((array) ($header['columns'] ?? [])) : [];

        if (! isset($columns['role'], $columns['is_active'], $columns['password'])) {
            return 0;
        }

        $usable = 0;

        foreach ($lines as $line) {
            $row = json_decode($line, true);

            if (is_array($row) && ($row[$columns['role']] ?? null) === 'super_admin' && (int) ($row[$columns['is_active']] ?? 0) === 1
                && is_string($row[$columns['password']] ?? null) && str_starts_with($row[$columns['password']], '$2y$')) {
                $usable++;
            }
        }

        return $usable;
    }

    private function estimateBytes(TargetAssessment $target): int
    {
        return (int) ($target->totalRows * (int) config('migration.import.recovery_bytes_per_row', 400));
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function human(int $bytes): string
    {
        return Number::fileSize($bytes);
    }
}
