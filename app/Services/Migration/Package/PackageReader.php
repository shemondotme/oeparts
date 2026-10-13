<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\EntryNotFoundException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Exceptions\SecretsLockedException;
use App\Services\Migration\Package\Exceptions\TruncatedException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;

/**
 * Reads a .oeparts package. A package is untrusted input, so nothing in it is believed until it
 * has been checked against something that cannot be forged without the passphrase:
 *
 *  1. The header proves the passphrase (key-check) before any other byte is read, so a wrong
 *     passphrase is never reported as a damaged file.
 *  2. The table of contents is an authenticated entry whose position is bound by the trailer and
 *     whose length is checked before it is read. Every record in it is validated (ids, names, sizes,
 *     frame counts, contiguous offsets with no slack) before a single entry is trusted.
 *  3. Every frame is authenticated against its package, entry, position and length, and every
 *     entry's SHA-256 is compared with the table of contents.
 *  4. Compressed entries go through BoundedInflater only, which never produces more bytes than
 *     the authenticated table of contents announced.
 *
 * Bytes handed to a sink while an entry streams are authentic frame by frame, but the entry as a
 * whole is only confirmed when the call returns: write them somewhere temporary and keep them only
 * after it returns normally.
 */
final class PackageReader
{
    /** Largest entry readString() will return unless the caller says otherwise. */
    public const DEFAULT_STRING_CAP = 64 * 1024 * 1024;

    /** Bytes one verifyStep() works through before it hands back a cursor. */
    public const VERIFY_BUDGET = 64 * 1024 * 1024;

    private const SECRETS_CAP = 16 * 1024 * 1024;

    private const HASH_CHUNK = 1 << 20;

    private bool $secretsUnlocked = false;

    private bool $closed = false;

    /** @var array<string, int> entry name => id */
    private array $byName = [];

    /** @var array<int, array<string, mixed>> validated records by id */
    private array $records = [];

    /** @var array<string, mixed> */
    private array $toc = [];

    private string $tocJson = '';

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @param  resource  $handle */
    private function __construct(
        private $handle,
        private readonly int $size,
        private readonly Header $header,
        private readonly DerivedKeys $keys,
        private readonly FrameCodec $codec,
        private readonly int $tocOffset,
        private readonly int $tocEncLen,
        private readonly string $trailerSha256,
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Look at a file without a passphrase. Valid on a partly received file.
     *
     * @throws PackageException
     */
    public static function peek(string $path, ?Kdf $kdf = null): PackagePeek
    {
        $handle = self::openFile($path);

        try {
            $head = (string) fread($handle, 4096);
            $header = Header::parse($head, $kdf);
            $size = (int) (fstat($handle)['size'] ?? 0);

            $hasTrailer = false;
            if ($size >= $header->headerLength + PackageLimits::TRAILER_LEN) {
                fseek($handle, $size - PackageLimits::TRAILER_LEN);
                $trailer = (string) fread($handle, PackageLimits::TRAILER_LEN);
                $hasTrailer = strlen($trailer) === PackageLimits::TRAILER_LEN
                    && substr($trailer, 0, 8) === PackageLimits::TRAILER_MAGIC
                    && substr($trailer, 56, 8) === PackageFormat::MAGIC;
            }

            return new PackagePeek($header, $size, $hasTrailer);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Open with a passphrase exactly as given.
     *
     * @throws PackageException
     */
    public static function open(string $path, string $passphrase, ?Kdf $kdf = null): self
    {
        $handle = self::openFile($path);

        try {
            return self::openHandle($handle, $passphrase, $kdf);
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw $e;
        }
    }

    /**
     * Open with what a person typed: the literal text first, then the forgiving forms of a generated
     * passphrase (see PassphraseInput::candidates). Costs one key derivation per candidate.
     *
     * @throws PackageException
     */
    public static function openTyped(string $path, string $typed, ?Kdf $kdf = null): self
    {
        $last = null;

        foreach (PassphraseInput::candidates($typed) as $candidate) {
            try {
                return self::open($path, $candidate, $kdf);
            } catch (WrongPassphraseException $e) {
                $last = $e;
            }
        }

        throw $last ?? new WrongPassphraseException;
    }

    // ------------------------------------------------------------------------------------------
    // What the package says about itself

    public function header(): Header
    {
        return $this->header;
    }

    public function packageIdHex(): string
    {
        return bin2hex($this->header->packageId);
    }

    public function fileSize(): int
    {
        return $this->size;
    }

    /** SHA-256 of every byte before the trailer, as the trailer states it. */
    public function fileSha256(): string
    {
        return bin2hex($this->trailerSha256);
    }

    public function fingerprint(): string
    {
        return Fingerprint::compute($this->keys->fingerprint(), $this->tocJson, $this->header->packageId);
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return $this->meta;
    }

    /** @return array<string, mixed> the authenticated table of contents */
    public function toc(): array
    {
        return $this->toc;
    }

    /**
     * Every entry the importer may see: the secrets entry is not among them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        return array_values(array_filter(
            $this->records,
            fn (array $record): bool => $record['key_class'] === FrameCodec::KEY_CLASS_DATA
        ));
    }

    public function hasSecrets(): bool
    {
        return isset($this->byName['secrets']);
    }

    /** Reading the secrets entry is a separate, explicit act. */
    public function unlockSecrets(): self
    {
        $this->secretsUnlocked = true;

        return $this;
    }

    public function lockSecrets(): self
    {
        $this->secretsUnlocked = false;

        return $this;
    }

    /** @return array<string, mixed> */
    public function entry(string $name): array
    {
        return $this->record($name);
    }

    // ------------------------------------------------------------------------------------------
    // Reading entries

    /**
     * Stream an entry. A compressed entry is inflated (through BoundedInflater) unless $decode is false.
     *
     * @param  callable(string): void  $sink
     *
     * @throws PackageException
     */
    public function readEntry(string $name, callable $sink, bool $decode = true): void
    {
        $record = $this->record($name);

        if ($decode && $record['codec'] === 'gzip') {
            $inflater = new BoundedInflater($record['raw_len']);
            $this->streamStored($record, fn (string $plain) => $inflater->feed($plain, $sink));
            $inflater->finish();

            return;
        }

        $this->streamStored($record, $sink);
    }

    /**
     * A whole entry as a string, refused above $maxBytes (of the decoded result).
     *
     * @throws PackageException
     */
    public function readString(string $name, ?int $maxBytes = null): string
    {
        $record = $this->record($name);
        $maxBytes ??= self::DEFAULT_STRING_CAP;

        $decoded = $record['raw_len'];
        if ($decoded > $maxBytes) {
            throw new LimitExceededException("Entry '{$name}' is {$decoded} bytes, more than the {$maxBytes} allowed here.");
        }

        $out = '';
        $this->readEntry($name, function (string $part) use (&$out): void {
            $out .= $part;
        });

        return $out;
    }

    /**
     * A byte range of a stored (uncompressed) entry, touching only the frames that hold it.
     * Every frame read is authenticated; the whole-entry checksum is not, since the rest is not read.
     *
     * @throws PackageException
     */
    public function readRange(string $name, int $offset, int $length): string
    {
        $record = $this->record($name);

        if ($record['codec'] !== 'none') {
            throw new \LogicException("Entry '{$name}' is compressed: stream it with readEntry().");
        }
        if ($offset < 0 || $length < 0 || $offset + $length > $record['plain_len']) {
            throw new \InvalidArgumentException('The range is outside the entry.');
        }
        if ($length > self::DEFAULT_STRING_CAP) {
            throw new LimitExceededException('The range is larger than '.self::DEFAULT_STRING_CAP.' bytes.');
        }
        if ($length === 0) {
            return '';
        }

        $this->assertEntryHeader($record);

        $block = $this->codec->blockSize();
        $first = intdiv($offset, $block);
        $last = intdiv($offset + $length - 1, $block);
        $out = '';

        for ($k = $first; $k <= $last; $k++) {
            $out .= $this->decryptFrameOf($record, $k);
        }

        return substr($out, $offset - $first * $block, $length);
    }

    /** The secrets entry's lines. Refused until unlockSecrets() has been called. */
    public function readSecrets(): string
    {
        return $this->readString('secrets', self::SECRETS_CAP);
    }

    // ------------------------------------------------------------------------------------------
    // Verification

    /**
     * Verify the whole package, for example before an import changes anything: the file checksum,
     * then every entry's authentication, checksum and (for compressed entries) exact decompressed length.
     *
     * @return array{entries: int, plain_bytes: int, raw_bytes: int}
     *
     * @throws PackageException
     */
    public function verifyAll(): array
    {
        $cursor = null;

        do {
            $step = $this->verifyStep($cursor, PHP_INT_MAX);
            $cursor = $step['cursor'];
        } while (! $step['done']);

        $totals = $this->toc['totals'];

        return ['entries' => $totals['entries'], 'plain_bytes' => $totals['plain_bytes'], 'raw_bytes' => $totals['raw_bytes']];
    }

    /**
     * One resumable slice of verifyAll(): about $byteBudget bytes of work, then a cursor that is
     * plain JSON-able data, so a long verification can be spread over queued jobs and survive a restart.
     * The whole-file hash resumes mid-file; entries resume at entry boundaries. A cursor that makes no
     * sense is not trusted: verification starts again from the beginning.
     *
     * @param  array<string, mixed>|null  $cursor
     * @return array{done: bool, cursor: array<string, mixed>, bytes: int}
     *
     * @throws PackageException
     */
    public function verifyStep(?array $cursor = null, int $byteBudget = self::VERIFY_BUDGET): array
    {
        $cursor = $this->sanitiseCursor($cursor);
        $spent = 0;

        if ($cursor['phase'] === 'hash') {
            $end = $this->tocOffset + $this->tocEncLen;
            $offset = $cursor['offset'];
            $context = $offset === 0 ? hash_init('sha256') : $this->restoreHash($cursor['ctx']);

            if ($context === null) {
                $offset = 0;
                $context = hash_init('sha256');
            }

            while ($offset < $end && $spent < $byteBudget) {
                $chunk = $this->readAt($offset, min(self::HASH_CHUNK, $end - $offset));
                hash_update($context, $chunk);
                $offset += strlen($chunk);
                $spent += strlen($chunk);
            }

            if ($offset < $end) {
                return [
                    'done' => false,
                    'cursor' => ['phase' => 'hash', 'offset' => $offset, 'ctx' => base64_encode(serialize($context))],
                    'bytes' => $spent,
                ];
            }

            if (! hash_equals($this->trailerSha256, hash_final($context, true))) {
                throw new CorruptException('The checksum of the whole file does not match the one in its trailer.', 'file');
            }

            $cursor = ['phase' => 'entries', 'index' => 0];
        }

        if ($cursor['phase'] === 'entries') {
            $ids = array_keys($this->records);
            $index = $cursor['index'];

            while ($index < count($ids) && $spent < $byteBudget) {
                $record = $this->records[$ids[$index]];
                $this->verifyEntry($record);
                $spent += $record['enc_len'];
                $index++;
            }

            if ($index < count($ids)) {
                return ['done' => false, 'cursor' => ['phase' => 'entries', 'index' => $index], 'bytes' => $spent];
            }
        }

        return ['done' => true, 'cursor' => ['phase' => 'done'], 'bytes' => $spent];
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->keys->wipe();
    }

    // ------------------------------------------------------------------------------------------

    /**
     * @param  resource  $handle
     */
    private static function openHandle($handle, string $passphrase, ?Kdf $kdf): self
    {
        $size = (int) (fstat($handle)['size'] ?? 0);
        $opened = Header::open((string) fread($handle, 4096), $passphrase, $kdf);
        $header = $opened->header;
        $codec = new FrameCodec($opened->keys->data(), $opened->keys->secrets(), $header->packageId, $header->blockLog2);

        if ($size < $header->headerLength + PackageLimits::TRAILER_LEN) {
            throw new TruncatedException('The file ends before its trailer: it is incomplete or was cut short.');
        }

        if ($size > PackageLimits::MAX_PACKAGE_BYTES) {
            throw new LimitExceededException('The file is larger than the '.PackageLimits::MAX_PACKAGE_BYTES.' bytes a package may have.');
        }

        fseek($handle, $size - PackageLimits::TRAILER_LEN);
        $trailer = (string) fread($handle, PackageLimits::TRAILER_LEN);

        // A transfer that stops early leaves arbitrary bytes at the end: say "incomplete", not "damaged".
        if (strlen($trailer) !== PackageLimits::TRAILER_LEN || substr($trailer, 56, 8) !== PackageFormat::MAGIC) {
            throw new TruncatedException('The file does not end with its trailer: it is incomplete or was cut short.');
        }
        if (substr($trailer, 0, 8) !== PackageLimits::TRAILER_MAGIC) {
            throw new CorruptException('The trailer is damaged.', 'trailer');
        }

        $tocOffset = unpack('J', substr($trailer, 8, 8))[1];
        $tocEncLen = unpack('J', substr($trailer, 16, 8))[1];

        if ($tocOffset < $header->headerLength || $tocEncLen < FrameCodec::ENTRY_HEADER_LEN + FrameCodec::FRAME_HEADER_LEN
            || $tocOffset + $tocEncLen + PackageLimits::TRAILER_LEN !== $size) {
            throw new CorruptException('The trailer does not describe this file: bytes are missing, added or moved.', 'trailer');
        }

        $reader = new self($handle, $size, $header, $opened->keys, $codec, $tocOffset, $tocEncLen, substr($trailer, 24, 32));

        $toc = $reader->readToc();
        $reader->records = self::validateToc($toc['decoded'], $header, $codec, $tocOffset);
        $reader->toc = $toc['decoded'];
        $reader->tocJson = $toc['json'];
        foreach ($reader->records as $id => $record) {
            $reader->byName[$record['name']] = $id;
        }

        if ($reader->records[PackageLimits::ENTRY_ID_META]['plain_len'] > PackageLimits::MAX_META_BYTES) {
            throw new LimitExceededException('The package meta is larger than '.PackageLimits::MAX_META_BYTES.' bytes.');
        }

        try {
            $meta = json_decode($reader->readString('meta', PackageLimits::MAX_META_BYTES), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new CorruptException('The package meta is not valid JSON.', 'meta');
        }

        if (! is_array($meta) || (isset($meta['package_id']) && $meta['package_id'] !== bin2hex($header->packageId))) {
            throw new CorruptException('The package meta belongs to a different package.', 'meta');
        }

        $reader->meta = $meta;

        return $reader;
    }

    /**
     * @return array{json: string, decoded: array<string, mixed>}
     */
    private function readToc(): array
    {
        $head = FrameCodec::parseEntryHeader($this->readAt($this->tocOffset, FrameCodec::ENTRY_HEADER_LEN));

        if ($head['entryId'] !== PackageLimits::ENTRY_ID_TOC || $head['keyClass'] !== FrameCodec::KEY_CLASS_DATA) {
            throw new CorruptException('The trailer does not point at the table of contents.', 'table of contents');
        }
        if ($head['plainLen'] > PackageLimits::MAX_TOC_BYTES) {
            throw new LimitExceededException('The table of contents is larger than '.PackageLimits::MAX_TOC_BYTES.' bytes.');
        }
        if ($head['frameCount'] !== $this->codec->frameCountFor($head['plainLen'])
            || $this->codec->encryptedLengthFor($head['plainLen']) !== $this->tocEncLen) {
            throw new CorruptException('The table of contents does not have the length the trailer states.', 'table of contents');
        }

        $record = [
            'id' => PackageLimits::ENTRY_ID_TOC, 'name' => 'toc', 'key_class' => FrameCodec::KEY_CLASS_DATA,
            'offset' => $this->tocOffset, 'frames' => $head['frameCount'], 'plain_len' => $head['plainLen'],
            'enc_len' => $this->tocEncLen, 'plain_sha256' => null, 'enc_sha256' => null,
        ];

        $json = '';
        $this->streamStored($record, function (string $part) use (&$json): void {
            $json .= $part;
        });

        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new CorruptException('The table of contents is not valid JSON.', 'table of contents');
        }

        if (! is_array($decoded)) {
            throw new CorruptException('The table of contents has the wrong shape.', 'table of contents');
        }

        return ['json' => $json, 'decoded' => $decoded];
    }

    /**
     * Every record is checked before any entry is trusted. The layout is exact: entries follow each
     * other with no gap from the end of the header to the table of contents.
     *
     * @param  array<string, mixed>  $toc
     * @return array<int, array<string, mixed>>
     */
    private static function validateToc(array $toc, Header $header, FrameCodec $codec, int $tocOffset): array
    {
        $fail = static fn (string $why): CorruptException => new CorruptException("The table of contents is invalid: {$why}.", 'table of contents');

        $format = $toc['format'] ?? null;
        if (! is_array($format) || ($format['major'] ?? null) !== $header->formatMajor) {
            throw $fail('its format does not match the header');
        }
        if (($toc['package_id'] ?? null) !== bin2hex($header->packageId)) {
            throw $fail('it belongs to a different package');
        }

        $entries = $toc['entries'] ?? null;
        if (! is_array($entries) || ! array_is_list($entries) || $entries === [] || count($entries) > PackageLimits::MAX_ENTRIES) {
            throw $fail('the entry list is missing or has an impossible size');
        }

        $isSha = static fn (mixed $v): bool => is_string($v) && preg_match('/^[0-9a-f]{64}$/', $v) === 1;
        $records = [];
        $names = [];
        $expectedOffset = $header->headerLength;
        $totalRaw = 0;
        $totalPlain = 0;

        foreach ($entries as $index => $r) {
            if (! is_array($r)) {
                throw $fail("entry {$index} is not an object");
            }

            foreach (['id', 'key_class', 'offset', 'frames', 'plain_len', 'enc_len', 'raw_len'] as $field) {
                if (! isset($r[$field]) || ! is_int($r[$field]) || $r[$field] < 0) {
                    throw $fail("entry {$index} has a bad '{$field}'");
                }
            }

            if ($r['id'] !== $index) {
                throw $fail("entry {$index} has id {$r['id']}: ids must run 0, 1, 2 ... in file order");
            }

            $name = $r['name'] ?? null;
            if (! is_string($name) || isset($names[$name])) {
                throw $fail("entry {$index} has a missing or repeated name");
            }

            if ($index === PackageLimits::ENTRY_ID_META) {
                if ($name !== 'meta') {
                    throw $fail('the first entry must be the meta');
                }
            } elseif ($name === 'meta' || $name === 'toc') {
                throw $fail("the name '{$name}' is reserved");
            } elseif ($name !== 'secrets' && ! PackageLimits::isValidName($name)) {
                throw $fail("entry {$index} has an invalid name");
            }

            $isSecrets = $name === 'secrets';
            if ($r['key_class'] !== ($isSecrets ? FrameCodec::KEY_CLASS_SECRETS : FrameCodec::KEY_CLASS_DATA)) {
                throw $fail("entry {$index} uses the wrong key class for its name");
            }

            if (! is_string($r['kind'] ?? null) || strlen($r['kind']) > 64) {
                throw $fail("entry {$index} has a bad kind");
            }
            if (isset($r['group']) && (! is_string($r['group']) || strlen($r['group']) > 64)) {
                throw $fail("entry {$index} has a bad group");
            }
            if (! in_array($r['codec'] ?? null, ['none', 'gzip'], true)) {
                throw $fail("entry {$index} has an unknown codec");
            }
            if (! $isSha($r['enc_sha256'] ?? null) || ! $isSha($r['plain_sha256'] ?? null)) {
                throw $fail("entry {$index} has a bad checksum");
            }

            if ($r['plain_len'] > PackageLimits::MAX_ENTRY_BYTES || $r['raw_len'] > PackageLimits::MAX_ENTRY_RAW_BYTES) {
                throw $fail("entry {$index} is larger than the limit");
            }
            if ($r['codec'] === 'none' && $r['raw_len'] !== $r['plain_len']) {
                throw $fail("entry {$index} is stored uncompressed but announces a different size");
            }

            if ($r['frames'] !== $codec->frameCountFor($r['plain_len']) || $r['enc_len'] !== $codec->encryptedLengthFor($r['plain_len'])) {
                throw $fail("entry {$index} has a frame count or length that does not follow from its size");
            }
            if ($r['offset'] !== $expectedOffset) {
                throw $fail("entry {$index} does not start where the previous one ends");
            }

            $expectedOffset += $r['enc_len'];
            $totalRaw += $r['raw_len'];
            $totalPlain += $r['plain_len'];

            if ($totalRaw > PackageLimits::MAX_TOTAL_RAW_BYTES) {
                throw new LimitExceededException('The package would unpack to more than the allowed total size.');
            }
            $names[$name] = true;

            $records[$index] = [
                'id' => $r['id'], 'name' => $name, 'kind' => $r['kind'], 'group' => $r['group'] ?? null,
                'critical' => (bool) ($r['critical'] ?? false), 'key_class' => $r['key_class'],
                'offset' => $r['offset'], 'frames' => $r['frames'], 'plain_len' => $r['plain_len'], 'enc_len' => $r['enc_len'],
                'enc_sha256' => $r['enc_sha256'], 'plain_sha256' => $r['plain_sha256'],
                'codec' => $r['codec'], 'raw_len' => $r['raw_len'], 'rows' => $r['rows'] ?? null, 'attrs' => $r['attrs'] ?? [],
            ];
        }

        if ($expectedOffset !== $tocOffset) {
            throw $fail('the entries do not end where the table of contents begins');
        }
        if (($toc['meta_sha256'] ?? null) !== $records[PackageLimits::ENTRY_ID_META]['plain_sha256']) {
            throw $fail('the meta checksum does not match the meta entry');
        }

        // The totals the package announces are shown to people: they must be true.
        $totals = $toc['totals'] ?? null;
        if (! is_array($totals) || ($totals['entries'] ?? null) !== count($records) || ($totals['plain_bytes'] ?? null) !== $totalPlain || ($totals['raw_bytes'] ?? null) !== $totalRaw) {
            throw $fail('the totals do not add up');
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws EntryNotFoundException|SecretsLockedException
     */
    private function record(string $name): array
    {
        $this->assertOpen();

        $id = $this->byName[$name] ?? null;
        if ($id === null) {
            throw new EntryNotFoundException("The package has no entry named '{$name}'.");
        }

        $record = $this->records[$id];

        if ($record['key_class'] === FrameCodec::KEY_CLASS_SECRETS && ! $this->secretsUnlocked) {
            throw new SecretsLockedException;
        }

        return $record;
    }

    /**
     * Decrypt an entry frame by frame into $sink, checking the entry header against its record
     * and, when the record carries them, the SHA-256 of the stored and of the encrypted bytes.
     *
     * @param  array<string, mixed>  $record
     * @param  callable(string): void  $sink
     */
    private function streamStored(array $record, callable $sink): void
    {
        $header = $this->readAt($record['offset'], FrameCodec::ENTRY_HEADER_LEN);
        $this->assertEntryHeader($record, $header);

        $enc = hash_init('sha256');
        $plain = hash_init('sha256');
        hash_update($enc, $header);

        for ($k = 0; $k < $record['frames']; $k++) {
            $length = $this->codec->plainLengthOfFrame($k, $record['frames'], $record['plain_len']);
            $frame = $this->readAt($record['offset'] + FrameCodec::ENTRY_HEADER_LEN + $this->codec->frameOffset($k), FrameCodec::FRAME_HEADER_LEN + $length);

            $part = $this->codec->decryptFrame($frame, $record['id'], $record['key_class'], $k, $record['frames'], $record['plain_len']);

            hash_update($enc, $frame);
            hash_update($plain, $part);
            $sink($part);
        }

        if ($record['enc_sha256'] !== null && ! hash_equals($record['enc_sha256'], hash_final($enc))) {
            throw new CorruptException("Entry '{$record['name']}' does not match the checksum in the table of contents.", $record['name']);
        }
        if ($record['plain_sha256'] !== null && ! hash_equals($record['plain_sha256'], hash_final($plain))) {
            throw new CorruptException("Entry '{$record['name']}' does not match the content checksum in the table of contents.", $record['name']);
        }
    }

    /** @param  array<string, mixed>  $record */
    private function decryptFrameOf(array $record, int $k): string
    {
        $length = $this->codec->plainLengthOfFrame($k, $record['frames'], $record['plain_len']);
        $frame = $this->readAt($record['offset'] + FrameCodec::ENTRY_HEADER_LEN + $this->codec->frameOffset($k), FrameCodec::FRAME_HEADER_LEN + $length);

        return $this->codec->decryptFrame($frame, $record['id'], $record['key_class'], $k, $record['frames'], $record['plain_len']);
    }

    /** @param  array<string, mixed>  $record */
    private function assertEntryHeader(array $record, ?string $bytes = null): void
    {
        $head = FrameCodec::parseEntryHeader($bytes ?? $this->readAt($record['offset'], FrameCodec::ENTRY_HEADER_LEN));

        if ($head['entryId'] !== $record['id'] || $head['keyClass'] !== $record['key_class']
            || $head['frameCount'] !== $record['frames'] || $head['plainLen'] !== $record['plain_len']) {
            throw new CorruptException("Entry '{$record['name']}' does not match its record in the table of contents.", $record['name']);
        }
    }

    /** @param  array<string, mixed>  $record */
    private function verifyEntry(array $record): void
    {
        if ($record['codec'] === 'gzip') {
            $inflater = new BoundedInflater($record['raw_len']);
            $this->streamStored($record, fn (string $plain) => $inflater->feed($plain, static function (): void {}));
            $inflater->finish();

            return;
        }

        $this->streamStored($record, static function (): void {});
    }

    /** @param  array<string, mixed>|null  $cursor */
    private function sanitiseCursor(?array $cursor): array
    {
        $end = $this->tocOffset + $this->tocEncLen;
        $fresh = ['phase' => 'hash', 'offset' => 0, 'ctx' => null];

        if ($cursor === null) {
            return $fresh;
        }

        if (($cursor['phase'] ?? null) === 'hash' && is_int($cursor['offset'] ?? null) && $cursor['offset'] >= 0 && $cursor['offset'] <= $end
            && ($cursor['offset'] === 0 || is_string($cursor['ctx'] ?? null))) {
            return ['phase' => 'hash', 'offset' => $cursor['offset'], 'ctx' => $cursor['ctx'] ?? null];
        }

        if (($cursor['phase'] ?? null) === 'entries' && is_int($cursor['index'] ?? null) && $cursor['index'] >= 0 && $cursor['index'] <= count($this->records)) {
            return ['phase' => 'entries', 'index' => $cursor['index']];
        }

        return $fresh;
    }

    private function restoreHash(?string $encoded): ?\HashContext
    {
        if ($encoded === null) {
            return null;
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return null;
        }

        $context = @unserialize($decoded, ['allowed_classes' => [\HashContext::class]]);

        return $context instanceof \HashContext ? $context : null;
    }

    private function readAt(int $offset, int $length): string
    {
        $this->assertOpen();

        if ($offset < 0 || $length < 0 || $offset + $length > $this->size) {
            throw new TruncatedException('A read would go past the end of the file.');
        }

        if ($length === 0) {
            return '';
        }

        if (fseek($this->handle, $offset) !== 0) {
            throw new \RuntimeException('Cannot seek in the package file.');
        }

        $buffer = '';
        while (strlen($buffer) < $length) {
            $part = fread($this->handle, $length - strlen($buffer));

            if ($part === false || $part === '') {
                throw new TruncatedException('The file ended while it was being read.');
            }

            $buffer .= $part;
        }

        return $buffer;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new \LogicException('This package reader is closed.');
        }
    }

    /** @return resource */
    private static function openFile(string $path)
    {
        $handle = is_file($path) ? @fopen($path, 'rb') : false;

        if ($handle === false) {
            throw new \RuntimeException("Cannot open the package file {$path}.");
        }

        return $handle;
    }
}
