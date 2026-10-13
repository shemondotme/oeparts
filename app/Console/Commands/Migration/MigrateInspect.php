<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Console\Concerns\PassphraseSourceException;
use App\Console\Concerns\ReadsPassphrase;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackagePeek;
use App\Services\Migration\Package\PackageReader;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Look inside a .oeparts package without importing anything: what it is, whether it is whole,
 * and, with the passphrase, what it contains. Read only; changes nothing on this site.
 */
class MigrateInspect extends Command
{
    use EmitsJson;
    use ReadsPassphrase;

    protected $signature = 'oeparts:migrate:inspect
        {package : Path to the .oeparts file}
        {--header-only : Show only what the file says about itself without a passphrase}
        {--files : List every entry}
        {--verify : Check every byte of the package (can take a while for a large one)}
        {--json : Print newline-delimited JSON events on standard output}
        '.self::PASSPHRASE_SIGNATURE;

    protected $description = 'Show what a .oeparts package is and, with its passphrase, what it contains';

    public function handle(): int
    {
        $path = (string) $this->argument('package');
        $kdf = app(Kdf::class);

        if (! is_file($path) || ! is_readable($path)) {
            return $this->finish($this->reportUsage("There is no readable file at {$path}."));
        }

        try {
            $peek = PackageReader::peek($path, $kdf);
        } catch (PackageException $e) {
            return $this->finish($this->reportPackageError($e));
        }

        $summary = $this->describeFile($peek);

        if ($this->option('header-only')) {
            $this->event('package', $summary);

            return $this->finish(ExitCode::Ok, ['package' => $summary]);
        }

        try {
            $passphrase = $this->readPassphrase();
        } catch (PassphraseSourceException $e) {
            return $this->finish($this->reportUsage($e->getMessage()));
        }

        try {
            $reader = PackageReader::openTyped($path, $passphrase, $kdf);
        } catch (PackageException $e) {
            return $this->finish($this->reportPackageError($e));
        }

        try {
            return $this->inspect($reader, $summary);
        } catch (PackageException $e) {
            return $this->finish($this->reportPackageError($e));
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function inspect(PackageReader $reader, array $summary): int
    {
        $toc = $reader->toc();
        $meta = $reader->meta();
        $totals = $toc['totals'];

        $summary += [
            'fingerprint' => $reader->fingerprint(),
            'source_domain' => $meta['source']['domain'] ?? null,
            'created_at' => $meta['created_at'] ?? null,
            'app_version' => $meta['app_version'] ?? null,
            'entries' => $totals['entries'],
            'stored_bytes' => $totals['plain_bytes'],
            'unpacked_bytes' => $totals['raw_bytes'],
            'contains_secrets' => $reader->hasSecrets(),
        ];

        $this->human('Fingerprint:  '.$summary['fingerprint'].'   (compare with the one shown when the package was made)');
        if ($summary['source_domain'] !== null) {
            $this->human('Made on:      '.$summary['source_domain'].($summary['created_at'] !== null ? ' at '.$summary['created_at'] : ''));
        }
        $this->human(sprintf('Contents:     %d entries, %s stored, %s once unpacked', $summary['entries'], Number::fileSize($summary['stored_bytes']), Number::fileSize($summary['unpacked_bytes'])));
        $this->human('Settings with secrets (passwords, API keys): '.($summary['contains_secrets'] ? 'included, behind a separate unlock' : 'not included'));
        $this->event('package', $summary);

        if ($this->option('files')) {
            foreach ($reader->entries() as $entry) {
                $this->human(sprintf('  %-48s %-10s %12s%s', $entry['name'], $entry['kind'], Number::fileSize($entry['raw_len']), $entry['codec'] === 'gzip' ? '  (compressed)' : ''));
                $this->event('entry', ['name' => $entry['name'], 'kind' => $entry['kind'], 'group' => $entry['group'], 'stored_bytes' => $entry['plain_len'], 'unpacked_bytes' => $entry['raw_len'], 'codec' => $entry['codec']]);
            }
        }

        if ($this->option('verify')) {
            $this->human('Verifying every byte of the package...');

            $cursor = null;
            $done = 0;
            do {
                $step = $reader->verifyStep($cursor);
                $cursor = $step['cursor'];
                $done += $step['bytes'];

                $this->human('  checked '.Number::fileSize($done).' of about '.Number::fileSize($summary['size']));
                $this->event('progress', ['stage' => 'verify', 'bytes_done' => $done, 'bytes_total' => $summary['size']]);
            } while (! $step['done']);

            $this->human('The package is intact.');
            $summary['verified'] = true;
        }

        return $this->finish(ExitCode::Ok, ['package' => $summary]);
    }

    /** @return array<string, mixed> */
    private function describeFile(PackagePeek $peek): array
    {
        $header = $peek->header;

        $summary = [
            'format' => $header->formatMajor.'.'.$header->formatMinor,
            'cipher' => 'AES-256-GCM',
            'kdf' => $this->describeKdf($header),
            'block_size' => $header->blockSize(),
            'package_id' => $peek->packageIdHex(),
            'size' => $peek->fileSize,
            'complete' => $peek->hasTrailer,
        ];

        $this->human('File:         '.Number::fileSize($peek->fileSize).($peek->hasTrailer ? '' : '  (INCOMPLETE: still uploading, or cut short)'));
        $this->human('Format:       .oeparts '.$summary['format'].', encrypted with AES-256-GCM, '.$summary['kdf']);
        $this->human('Package id:   '.$summary['package_id']);

        return $summary;
    }

    private function describeKdf(Header $header): string
    {
        return match ($header->kdfId) {
            PackageFormat::KDF_ARGON2ID => sprintf('Argon2id (%d MiB, %d passes)', intdiv($header->kdfP2, 1024), $header->kdfP1),
            default => sprintf('PBKDF2-SHA512 (%s iterations)', number_format($header->kdfP1)),
        };
    }
}
