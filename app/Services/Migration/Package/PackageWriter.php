<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;

/**
 * Writes a .oeparts package: append-only, one entry at a time, resumable after a crash.
 *
 *   [ HEADER 128 ][ ENTRY 0 'meta' ][ ENTRY 1..n ][ ENTRY 0xFFFFFFFF 'toc' ][ TRAILER 64 ]
 *
 * There are no unused bytes between entries: entry i+1 starts at offset_i + 24 + frames*32 + plain_len_i,
 * and the file ends right after the trailer; a reader refuses anything else.
 *
 * The file is built as <final>.part next to a ledger directory. After every entry the ledger's
 * state.json is the commit point (see WriterLedger). resume() reopens the .part with the same
 * passphrase, cuts it back to the last committed byte and carries on. seal() writes the table of
 * contents and the trailer, fsyncs, and renames the .part to its final name; before that point
 * nothing at the final path exists, so a half-written package can never be mistaken for a finished one.
 */
final class PackageWriter
{
    /** @var resource */
    private $handle;

    private int $offset;

    private int $nextEntryId;

    /** @var array<int, array<string, mixed>> table-of-contents records by entry id */
    private array $records;

    private ?\HashContext $fileHash;

    private bool $needsRehash;

    private int $totalRaw = 0;

    private bool $finished = false;

    /**
     * @param  resource  $handle
     * @param  array<int, array<string, mixed>>  $records
     */
    private function __construct(
        private readonly string $finalPath,
        private readonly string $partPath,
        private readonly Header $header,
        private readonly DerivedKeys $keys,
        private readonly FrameCodec $codec,
        private readonly WriterLedger $ledger,
        $handle,
        int $offset,
        int $nextEntryId,
        array $records,
        ?\HashContext $fileHash,
        bool $needsRehash,
    ) {
        $this->handle = $handle;
        $this->offset = $offset;
        $this->nextEntryId = $nextEntryId;
        $this->records = $records;
        $this->fileHash = $fileHash;
        $this->needsRehash = $needsRehash;

        foreach ($records as $record) {
            $this->totalRaw += (int) ($record['raw_len'] ?? $record['plain_len']);
        }
    }

    /**
     * Start a new package.
     *
     * @param  array<string, mixed>  $meta  what the package says about itself (source, mode, groups, ...)
     * @param  bool  $enforcePassphraseRule  the passphrase policy of PassphraseInput; only tests turn it off
     * @param  int|null  $blockLog2  log2 of the frame size; production always uses the default (2^20), tests and fixtures use a small one
     */
    public static function create(
        string $finalPath,
        #[\SensitiveParameter] string $passphrase,
        array $meta,
        ?Kdf $kdf = null,
        ?int $kdfId = null,
        ?array $kdfParams = null,
        ?string $salt = null,
        ?string $packageId = null,
        bool $enforcePassphraseRule = true,
        ?int $blockLog2 = null,
    ): self {
        $kdf ??= new Kdf;
        $kdfId ??= Kdf::preferredKdfId();

        if ($enforcePassphraseRule && ($problem = PassphraseInput::validate($passphrase, $kdfId)) !== null) {
            throw new \InvalidArgumentException("The passphrase is not acceptable ({$problem}).");
        }

        $partPath = $finalPath.'.part';
        $ledger = new WriterLedger($partPath.'.ledger');

        if (file_exists($finalPath)) {
            throw new \RuntimeException("{$finalPath} already exists.");
        }
        if (file_exists($partPath) || $ledger->exists()) {
            throw new \RuntimeException("A previous, unfinished export exists at {$partPath}: resume or abort it first.");
        }

        $params = $kdfParams ?? $kdf->defaultParams($kdfId);
        $header = Header::create($kdfId, $params['p1'], $params['p2'], $salt, $packageId, $blockLog2 ?? PackageFormat::DEFAULT_BLOCK_LOG2);
        [$sealed, $keys] = $header->seal($passphrase, $kdf);

        $dir = dirname($finalPath);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create the directory {$dir}.");
        }

        $handle = @fopen($partPath, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot create {$partPath}.");
        }
        ftruncate($handle, 0);

        $bytes = $sealed->toBytes();
        if (fwrite($handle, $bytes) !== strlen($bytes)) {
            fclose($handle);
            @unlink($partPath);

            throw new \RuntimeException('Short write of the package header.');
        }
        fflush($handle);

        $hash = hash_init('sha256');
        hash_update($hash, $bytes);

        $codec = new FrameCodec($keys->data(), $keys->secrets(), $sealed->packageId, $sealed->blockLog2);
        $writer = new self($finalPath, $partPath, $sealed, $keys, $codec, $ledger, $handle, strlen($bytes), 0, [], $hash, false);
        $writer->persistState();

        $metaJson = json_encode(
            array_merge($meta, ['format' => ['major' => PackageFormat::FORMAT_MAJOR, 'minor' => PackageFormat::FORMAT_MINOR], 'package_id' => bin2hex($sealed->packageId)]),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (strlen($metaJson) > PackageLimits::MAX_META_BYTES) {
            $writer->abort();

            throw new LimitExceededException('The package meta is larger than '.PackageLimits::MAX_META_BYTES.' bytes.');
        }

        $writer->writeEntryFromString(PackageLimits::ENTRY_ID_META, 'meta', 'meta', $metaJson, ['critical' => true]);

        return $writer;
    }

    /**
     * Reopen an unfinished package with the same passphrase, drop everything after the last
     * committed entry, and continue.
     */
    public static function resume(string $finalPath, #[\SensitiveParameter] string $passphrase, ?Kdf $kdf = null): self
    {
        return self::resumeFrom($finalPath, $passphrase, $kdf);
    }

    /**
     * Resume from keys derived earlier (the KeyEscrow) instead of a passphrase: no key derivation runs, and the
     * key-check in the header still proves the keys belong to this very file.
     */
    public static function resumeWithKeys(string $finalPath, DerivedKeys $keys, ?Kdf $kdf = null): self
    {
        return self::resumeFrom($finalPath, DerivedKeys::restore($keys->subkeys()), $kdf);
    }

    private static function resumeFrom(string $finalPath, string|DerivedKeys $secret, ?Kdf $kdf): self
    {
        $partPath = $finalPath.'.part';
        $ledger = new WriterLedger($partPath.'.ledger');

        if (! is_file($partPath) || ! $ledger->exists()) {
            throw new \RuntimeException("There is no unfinished package at {$finalPath} to resume.");
        }

        $state = $ledger->state()->read();
        if (! is_array($state) || ! isset($state['committed_offset'], $state['next_entry_id'], $state['package_id'])) {
            throw new CorruptException('The writer ledger is incomplete.', 'ledger');
        }

        $handle = @fopen($partPath, 'c+b');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open {$partPath}.");
        }

        $offset = (int) $state['committed_offset'];
        $actual = (int) (fstat($handle)['size'] ?? 0);
        if ($actual < $offset) {
            fclose($handle);

            throw new CorruptException("The partial file is shorter ({$actual}) than its ledger says ({$offset}).", 'partial file');
        }

        // The same passphrase opens the same header: this proves the caller holds the right one.
        $head = (string) fread($handle, PackageFormat::HEADER_LEN);
        $opened = $secret instanceof DerivedKeys ? Header::openWithKeys($head, $secret, $kdf) : Header::open($head, $secret, $kdf);

        if (bin2hex($opened->header->packageId) !== $state['package_id']) {
            fclose($handle);

            throw new CorruptException('The partial file and its ledger belong to different packages.', 'ledger');
        }

        // Whatever was written after the last commit point is discarded: that entry is written again.
        ftruncate($handle, $offset);
        $nextEntryId = (int) $state['next_entry_id'];
        $ledger->keepOnly($nextEntryId);

        $records = [];
        foreach ($ledger->entries($nextEntryId) as $record) {
            $records[(int) $record['id']] = $record;
        }
        if (count($records) !== $nextEntryId) {
            fclose($handle);

            throw new CorruptException('The writer ledger is missing entries the state acknowledged.', 'ledger');
        }

        $hash = null;
        $needsRehash = true;
        if (! empty($state['hash_context']) && empty($state['needs_rehash'])) {
            $restored = @unserialize(base64_decode((string) $state['hash_context']), ['allowed_classes' => [\HashContext::class]]);

            if ($restored instanceof \HashContext) {
                $hash = $restored;
                $needsRehash = false;
            }
        }

        $codec = new FrameCodec($opened->keys->data(), $opened->keys->secrets(), $opened->header->packageId, $opened->header->blockLog2);

        return new self($finalPath, $partPath, $opened->header, $opened->keys, $codec, $ledger, $handle, $offset, $nextEntryId, $records, $hash, $needsRehash);
    }

    public function packageIdHex(): string
    {
        return bin2hex($this->header->packageId);
    }

    /** The keys of this package, for the KeyEscrow: so later requests resume without the passphrase. */
    public function keys(): DerivedKeys
    {
        return $this->keys;
    }

    public function partPath(): string
    {
        return $this->partPath;
    }

    /** Bytes of the .part that are committed and survive a crash. */
    public function committedOffset(): int
    {
        return $this->offset;
    }

    /** @return array<int, array<string, mixed>> */
    public function entryRecords(): array
    {
        return $this->records;
    }

    /**
     * @param  array<string, mixed>  $options  group, critical, codec (gzip|none), raw_len, rows, attrs
     * @return array<string, mixed> the entry's table-of-contents record
     */
    public function addEntryFromString(string $name, string $kind, string $data, array $options = []): array
    {
        return $this->addNamed($name, $kind, fn () => $this->streamOf($data), strlen($data), $options);
    }

    /** @param  array<string, mixed>  $options */
    public function addEntryFromFile(string $name, string $kind, string $path, array $options = []): array
    {
        $size = @filesize($path);
        if ($size === false) {
            throw new \RuntimeException("Cannot read the size of {$path}.");
        }

        return $this->addNamed($name, $kind, function () use ($path) {
            $stream = @fopen($path, 'rb');

            if ($stream === false) {
                throw new \RuntimeException("Cannot open {$path}.");
            }

            return $stream;
        }, $size, $options);
    }

    /**
     * @param  resource  $stream  must deliver exactly $plainLen bytes
     * @param  array<string, mixed>  $options
     */
    public function addEntryFromStream(string $name, string $kind, $stream, int $plainLen, array $options = []): array
    {
        return $this->addNamed($name, $kind, fn () => $stream, $plainLen, $options, closeStream: false);
    }

    /** The optional secrets entry: its own key, readable only through an explicit unlock. */
    public function addSecrets(string $ndjson): array
    {
        $this->assertOpen();
        $record = $this->writeEntryFromString($this->nextEntryId, 'secrets', 'secrets', $ndjson, ['critical' => false], FrameCodec::KEY_CLASS_SECRETS);

        return $record;
    }

    /**
     * Write the table of contents and the trailer, flush, and move the package to its final name.
     *
     * @return array{path: string, size: int, sha256: string, fingerprint: string, entries: int}
     */
    public function seal(): array
    {
        $this->assertOpen();

        $meta = $this->records[PackageLimits::ENTRY_ID_META] ?? null;
        if ($meta === null) {
            throw new \LogicException('The package has no meta entry.');
        }

        $entries = array_values($this->records);
        $toc = json_encode([
            'format' => ['major' => PackageFormat::FORMAT_MAJOR, 'minor' => PackageFormat::FORMAT_MINOR],
            'package_id' => $this->packageIdHex(),
            'meta_sha256' => $meta['plain_sha256'],
            'contains_secrets' => (bool) array_filter($entries, fn ($e) => (int) $e['key_class'] === FrameCodec::KEY_CLASS_SECRETS),
            'totals' => ['entries' => count($entries), 'plain_bytes' => array_sum(array_column($entries, 'plain_len')), 'raw_bytes' => $this->totalRaw],
            'entries' => $entries,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($toc) > PackageLimits::MAX_TOC_BYTES) {
            throw new LimitExceededException('The table of contents is larger than '.PackageLimits::MAX_TOC_BYTES.' bytes.');
        }

        // The table of contents is not listed in itself, and does not count as a resumable entry.
        $tocRecord = $this->writeEntry(PackageLimits::ENTRY_ID_TOC, 'toc', 'toc', fn () => $this->streamOf($toc), strlen($toc), FrameCodec::KEY_CLASS_DATA, ['critical' => true], commit: false);

        $fileSha256 = $this->finalFileHash($tocRecord['offset'] + $tocRecord['enc_len']);

        $trailer = PackageLimits::TRAILER_MAGIC
            .pack('J', $tocRecord['offset'])
            .pack('J', $tocRecord['enc_len'])
            .hex2bin($fileSha256)
            .PackageFormat::MAGIC;

        if (strlen($trailer) !== PackageLimits::TRAILER_LEN) {
            throw new \LogicException('The trailer must be exactly '.PackageLimits::TRAILER_LEN.' bytes.');
        }

        $this->writeAt($tocRecord['offset'] + $tocRecord['enc_len'], $trailer);
        $size = $tocRecord['offset'] + $tocRecord['enc_len'] + PackageLimits::TRAILER_LEN;

        ftruncate($this->handle, $size);
        fflush($this->handle);
        if (function_exists('fsync')) {
            @fsync($this->handle);
        }
        fclose($this->handle);
        $this->finished = true;

        if (! @rename($this->partPath, $this->finalPath)) {
            throw new \RuntimeException("Cannot move the finished package to {$this->finalPath}.");
        }

        $this->ledger->destroy();

        return [
            'path' => $this->finalPath,
            'size' => $size,
            'sha256' => $fileSha256,
            'fingerprint' => Fingerprint::compute($this->keys->fingerprint(), $toc, $this->header->packageId),
            'entries' => count($entries),
        ];
    }

    /** Throw the unfinished package away: the .part, and the ledger. */
    public function abort(): void
    {
        if (! $this->finished) {
            if (is_resource($this->handle)) {
                fclose($this->handle);
            }
            @unlink($this->partPath);
            $this->ledger->destroy();
            $this->finished = true;
        }
    }

    // ------------------------------------------------------------------------------------------

    /**
     * @param  callable(): resource  $open
     * @param  array<string, mixed>  $options
     */
    private function addNamed(string $name, string $kind, callable $open, int $plainLen, array $options, bool $closeStream = true): array
    {
        $this->assertOpen();

        if (! PackageLimits::isValidName($name) || in_array($name, PackageLimits::RESERVED_NAMES, true)) {
            throw new \InvalidArgumentException("'{$name}' is not a valid entry name.");
        }
        foreach ($this->records as $record) {
            if ($record['name'] === $name) {
                throw new \InvalidArgumentException("An entry named '{$name}' already exists.");
            }
        }
        if (count($this->records) >= PackageLimits::MAX_ENTRIES) {
            throw new LimitExceededException('Too many entries.');
        }

        return $this->writeEntry($this->nextEntryId, $name, $kind, $open, $plainLen, FrameCodec::KEY_CLASS_DATA, $options, closeStream: $closeStream);
    }

    /** @param  array<string, mixed>  $options */
    private function writeEntryFromString(int $id, string $name, string $kind, string $data, array $options = [], int $keyClass = FrameCodec::KEY_CLASS_DATA): array
    {
        return $this->writeEntry($id, $name, $kind, fn () => $this->streamOf($data), strlen($data), $keyClass, $options);
    }

    /**
     * @param  callable(): resource  $open
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function writeEntry(int $id, string $name, string $kind, callable $open, int $plainLen, int $keyClass, array $options, bool $commit = true, bool $closeStream = true): array
    {
        if ($plainLen > PackageLimits::MAX_ENTRY_BYTES) {
            throw new LimitExceededException("Entry '{$name}' is larger than ".PackageLimits::MAX_ENTRY_BYTES.' bytes.');
        }

        $raw = (int) ($options['raw_len'] ?? $plainLen);
        if ($raw < 0 || $raw > PackageLimits::MAX_ENTRY_RAW_BYTES) {
            throw new LimitExceededException("Entry '{$name}' announces a decompressed size outside the allowed range.");
        }
        if ($this->totalRaw + $raw > PackageLimits::MAX_TOTAL_RAW_BYTES) {
            throw new LimitExceededException('The package would exceed the total size limit.');
        }

        $start = $this->offset;
        $frames = $this->codec->frameCountFor($plainLen);
        $stream = $open();

        // Work on copies of the hashes: if this entry fails half way, the committed state is untouched.
        $fileHash = $this->fileHash !== null ? hash_copy($this->fileHash) : null;
        $encHash = hash_init('sha256');
        $plainHash = hash_init('sha256');

        try {
            fseek($this->handle, $start);
            ftruncate($this->handle, $start); // nothing may linger beyond the committed offset

            $entryHeader = $this->codec->entryHeader($id, $keyClass, $frames, $plainLen);
            $this->emit($entryHeader, $encHash, $fileHash);

            for ($k = 0; $k < $frames; $k++) {
                $length = $this->codec->plainLengthOfFrame($k, $frames, $plainLen);
                $chunk = $length === 0 ? '' : $this->readExactly($stream, $length, $name);

                hash_update($plainHash, $chunk);
                $this->emit($this->codec->encryptFrame($chunk, $id, $keyClass, $k, $frames, $plainLen), $encHash, $fileHash);
            }

            // Exactly the announced length, no more: the length is bound into every frame's AAD.
            $extra = fread($stream, 1);
            if ($extra !== false && $extra !== '') {
                throw new \RuntimeException("The source of entry '{$name}' is longer than the {$plainLen} bytes announced.");
            }

            fflush($this->handle);
            if (function_exists('fsync')) {
                @fsync($this->handle);
            }
        } catch (\Throwable $e) {
            ftruncate($this->handle, $start);
            throw $e;
        } finally {
            if ($closeStream && is_resource($stream)) {
                fclose($stream);
            }
        }

        $encLen = $this->codec->encryptedLengthFor($plainLen);
        $record = [
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'group' => $options['group'] ?? null,
            'critical' => (bool) ($options['critical'] ?? false),
            'key_class' => $keyClass,
            'offset' => $start,
            'frames' => $frames,
            'plain_len' => $plainLen,
            'enc_len' => $encLen,
            'enc_sha256' => hash_final($encHash),
            'plain_sha256' => hash_final($plainHash),
            'codec' => $options['codec'] ?? 'none',
            'raw_len' => $raw,
            'rows' => $options['rows'] ?? null,
            'attrs' => $options['attrs'] ?? new \stdClass,
        ];

        $this->offset = $start + $encLen;
        $this->fileHash = $fileHash;
        $this->totalRaw += $raw;

        if ($commit) {
            $this->records[$id] = $record;
            $this->nextEntryId = max($this->nextEntryId, $id + 1);

            $this->ledger->appendEntry($record);
            $this->persistState();
        }

        return $record;
    }

    private function emit(string $bytes, \HashContext $encHash, ?\HashContext $fileHash): void
    {
        if (fwrite($this->handle, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException('Short write to the package: the disk may be full.');
        }

        hash_update($encHash, $bytes);
        if ($fileHash !== null) {
            hash_update($fileHash, $bytes);
        }
    }

    /** @param  resource  $stream */
    private function readExactly($stream, int $length, string $name): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $part = fread($stream, $length - strlen($buffer));

            if ($part === false || $part === '') {
                throw new \RuntimeException("The source of entry '{$name}' ended after ".strlen($buffer).' of the announced '.$length.' bytes in a block.');
            }

            $buffer .= $part;
        }

        return $buffer;
    }

    private function writeAt(int $position, string $bytes): void
    {
        fseek($this->handle, $position);
        if (fwrite($this->handle, $bytes) !== strlen($bytes)) {
            throw new \RuntimeException('Short write to the package: the disk may be full.');
        }
    }

    /** SHA-256 of every byte before the trailer: the keyless check anyone can make after a transfer. */
    private function finalFileHash(int $endOffset): string
    {
        if (! $this->needsRehash && $this->fileHash !== null) {
            return hash_final(hash_copy($this->fileHash));
        }

        // The running hash could not be restored after a resume: hash the committed bytes once.
        $hash = hash_init('sha256');
        fflush($this->handle);
        fseek($this->handle, 0);
        $remaining = $endOffset;
        while ($remaining > 0) {
            $chunk = fread($this->handle, min(1 << 20, $remaining));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Cannot re-read the package to hash it.');
            }
            hash_update($hash, $chunk);
            $remaining -= strlen($chunk);
        }

        return hash_final($hash);
    }

    private function persistState(): void
    {
        $this->ledger->state()->write([
            'package_id' => $this->packageIdHex(),
            'committed_offset' => $this->offset,
            'next_entry_id' => $this->nextEntryId,
            'hash_context' => $this->fileHash !== null ? base64_encode(serialize($this->fileHash)) : null,
            'needs_rehash' => $this->needsRehash,
        ]);
    }

    /** @return resource */
    private function streamOf(string $data)
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }

    private function assertOpen(): void
    {
        if ($this->finished) {
            throw new \LogicException('This package writer is already sealed or aborted.');
        }
    }
}
