<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\BackupPartNotPackageException;
use App\Services\Migration\Package\Exceptions\KdfParamsRejectedException;
use App\Services\Migration\Package\Exceptions\NotAPackageException;
use App\Services\Migration\Package\Exceptions\PackageChangedException;
use App\Services\Migration\Package\Exceptions\TextModeTransferException;
use App\Services\Migration\Package\Exceptions\TruncatedException;
use App\Services\Migration\Package\Exceptions\UnsupportedFormatException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;

/**
 * The 128-byte header of a .oeparts file (all integers big-endian):
 *
 *    0  8  magic "OEPARTS1"          56 16  package id
 *    8  4  text-mode guard           72 24  reserved
 *   12  2  format major              96 32  key-check (HMAC, see below)
 *   14  2  format minor
 *   16  2  header length (128)
 *   20  4  critical flags (must be 0)
 *   24  4  ignorable flags
 *   28  1  cipher id (1 = AES-256-GCM, the only one)
 *   29  1  kdf id (1 = Argon2id, 2 = PBKDF2-HMAC-SHA512)
 *   30  1  block size, log2
 *   32  4  kdf parameter 1 (Argon2 passes / PBKDF2 iterations)
 *   36  4  kdf parameter 2 (Argon2 memory, KiB)
 *   40 16  salt
 *
 * The key-check is HMAC-SHA256(check key, "OEPARTS1/keycheck" + every header byte before it), so
 * the passphrase is proven from the header alone and a wrong passphrase costs the reader no
 * more than this header: "wrong passphrase" is never confused with "damaged file". There is no
 * field, flag or value that means "not encrypted".
 */
final class Header
{
    private function __construct(
        public readonly int $formatMajor,
        public readonly int $formatMinor,
        public readonly int $headerLength,
        public readonly int $criticalFlags,
        public readonly int $ignorableFlags,
        public readonly int $cipherId,
        public readonly int $kdfId,
        public readonly int $blockLog2,
        public readonly int $kdfP1,
        public readonly int $kdfP2,
        public readonly string $salt,
        public readonly string $packageId,
        private readonly string $authenticatedBytes,
        public readonly string $keycheck,
    ) {}

    /**
     * A header for a new package, not yet sealed (no key-check).
     *
     * @param  string|null  $salt  16 bytes; random unless a test injects one
     * @param  string|null  $packageId  16 bytes; random unless a test injects one
     */
    public static function create(int $kdfId, int $p1, int $p2, ?string $salt = null, ?string $packageId = null, int $blockLog2 = PackageFormat::DEFAULT_BLOCK_LOG2, int $ignorableFlags = 0): self
    {
        $salt ??= random_bytes(PackageFormat::SALT_LEN);
        $packageId ??= random_bytes(PackageFormat::PACKAGE_ID_LEN);

        if (strlen($salt) !== PackageFormat::SALT_LEN || strlen($packageId) !== PackageFormat::PACKAGE_ID_LEN) {
            throw new \InvalidArgumentException('Salt and package id must be 16 bytes each.');
        }

        $authenticated = PackageFormat::MAGIC
            .PackageFormat::TEXT_GUARD
            .pack('nn', PackageFormat::FORMAT_MAJOR, PackageFormat::FORMAT_MINOR)
            .pack('n', PackageFormat::HEADER_LEN)
            .str_repeat("\0", 2)
            .pack('NN', PackageFormat::KNOWN_CRITICAL_FLAGS, $ignorableFlags)
            .pack('CCC', PackageFormat::CIPHER_AES_256_GCM, $kdfId, $blockLog2)
            .str_repeat("\0", 1)
            .pack('NN', $p1, $p2)
            .$salt
            .$packageId
            .str_repeat("\0", 24);

        return new self(
            PackageFormat::FORMAT_MAJOR, PackageFormat::FORMAT_MINOR, PackageFormat::HEADER_LEN,
            PackageFormat::KNOWN_CRITICAL_FLAGS, $ignorableFlags, PackageFormat::CIPHER_AES_256_GCM,
            $kdfId, $blockLog2, $p1, $p2, $salt, $packageId, $authenticated, ''
        );
    }

    /**
     * Derive the keys for a new header and put the key-check into it.
     *
     * @return array{0: self, 1: DerivedKeys}
     */
    public function seal(#[\SensitiveParameter] string $passphrase, ?Kdf $kdf = null): array
    {
        $kdf ??= new Kdf;
        $master = $kdf->derive($passphrase, $this->kdfId, $this->salt, $this->kdfP1, $this->kdfP2);
        $keys = new DerivedKeys($master, $this->authenticatedBytes);

        return [$this->withKeycheck(self::computeKeycheck($keys, $this->authenticatedBytes)), $keys];
    }

    /**
     * Read a header from the first bytes of a file and prove the passphrase. Nothing beyond
     * the header is needed, so a wrong passphrase is told apart from a damaged file.
     *
     * @throws NotAPackageException|BackupPartNotPackageException|TextModeTransferException|TruncatedException
     * @throws UnsupportedFormatException|KdfParamsRejectedException|WrongPassphraseException
     */
    public static function open(string $bytes, #[\SensitiveParameter] string $passphrase, ?Kdf $kdf = null): OpenedHeader
    {
        $kdf ??= new Kdf;
        $header = self::parse($bytes, $kdf);

        $master = $kdf->derive($passphrase, $header->kdfId, $header->salt, $header->kdfP1, $header->kdfP2);
        $keys = new DerivedKeys($master, $header->authenticatedBytes);

        if (! hash_equals($header->keycheck, self::computeKeycheck($keys, $header->authenticatedBytes))) {
            throw new WrongPassphraseException;
        }

        return new OpenedHeader($header, $keys);
    }

    /**
     * Open a header with keys derived earlier instead of a passphrase (no key derivation runs).
     * The key-check proves the keys belong to THIS header, so a package swapped for another
     * between two requests is noticed.
     *
     * @throws NotAPackageException|BackupPartNotPackageException|TextModeTransferException|TruncatedException
     * @throws UnsupportedFormatException|KdfParamsRejectedException|PackageChangedException
     */
    public static function openWithKeys(string $bytes, DerivedKeys $keys, ?Kdf $kdf = null): OpenedHeader
    {
        $header = self::parse($bytes, $kdf);

        if (! hash_equals($header->keycheck, self::computeKeycheck($keys, $header->authenticatedBytes))) {
            throw new PackageChangedException;
        }

        return new OpenedHeader($header, $keys);
    }

    /**
     * Parse and validate a header WITHOUT a passphrase (used to look at a file and to bound its
     * key-derivation settings before any key derivation runs).
     *
     * @throws NotAPackageException|BackupPartNotPackageException|TextModeTransferException|TruncatedException
     * @throws UnsupportedFormatException|KdfParamsRejectedException
     */
    public static function parse(string $bytes, ?Kdf $kdf = null): self
    {
        $kdf ??= new Kdf;

        if (str_starts_with($bytes, 'OEENC1')) {
            throw new BackupPartNotPackageException;
        }

        if (strlen($bytes) < 8 || ! str_starts_with($bytes, PackageFormat::MAGIC)) {
            throw new NotAPackageException;
        }

        // Checked BEFORE the length: a text-mode transfer that strips carriage returns also makes the
        // header shorter, and "upload it again in binary mode" is the right answer, not "incomplete".
        // A correct signature followed by a wrong guard is what such a transfer leaves behind.
        if (strlen($bytes) >= 12 && substr($bytes, 8, 4) !== PackageFormat::TEXT_GUARD) {
            throw new TextModeTransferException;
        }

        if (strlen($bytes) < PackageFormat::HEADER_LEN) {
            throw new TruncatedException('The file ends inside its header.');
        }

        $u16 = static fn (int $offset): int => unpack('n', substr($bytes, $offset, 2))[1];
        $u32 = static fn (int $offset): int => unpack('N', substr($bytes, $offset, 4))[1];

        $major = $u16(12);
        $minor = $u16(14);
        $headerLength = $u16(16);

        if ($major !== PackageFormat::FORMAT_MAJOR) {
            throw new UnsupportedFormatException("Format version {$major}.{$minor} is not supported (this reader handles ".PackageFormat::FORMAT_MAJOR.'.x).');
        }

        if ($headerLength < PackageFormat::HEADER_LEN || $headerLength > 4096 || strlen($bytes) < $headerLength) {
            throw new TruncatedException('The header length is invalid or the file ends inside its header.');
        }

        $critical = $u32(20);
        if (($critical & ~PackageFormat::KNOWN_CRITICAL_FLAGS) !== 0) {
            throw new UnsupportedFormatException('The file needs a capability this reader does not have.');
        }

        $cipher = ord($bytes[28]);
        $kdfId = ord($bytes[29]);
        $blockLog2 = ord($bytes[30]);

        if ($cipher !== PackageFormat::CIPHER_AES_256_GCM) {
            throw new UnsupportedFormatException("Cipher {$cipher} is not supported.");
        }

        if ($blockLog2 < PackageFormat::MIN_BLOCK_LOG2 || $blockLog2 > PackageFormat::MAX_BLOCK_LOG2) {
            throw new UnsupportedFormatException("Block size 2^{$blockLog2} is out of range.");
        }

        $p1 = $u32(32);
        $p2 = $u32(36);

        // Before any key derivation can run: the parameters come from the file.
        $kdf->assertAcceptable($kdfId, $p1, $p2);

        $keycheckOffset = $headerLength - PackageFormat::KEYCHECK_LEN;

        return new self(
            $major, $minor, $headerLength, $critical, $u32(24), $cipher, $kdfId, $blockLog2, $p1, $p2,
            substr($bytes, 40, PackageFormat::SALT_LEN),
            substr($bytes, 56, PackageFormat::PACKAGE_ID_LEN),
            substr($bytes, 0, $keycheckOffset),
            substr($bytes, $keycheckOffset, PackageFormat::KEYCHECK_LEN)
        );
    }

    /** Every header byte before the key-check: what the key-check and the subkeys are bound to. */
    public function authenticatedBytes(): string
    {
        return $this->authenticatedBytes;
    }

    public function withKeycheck(string $keycheck): self
    {
        if (strlen($keycheck) !== PackageFormat::KEYCHECK_LEN) {
            throw new \InvalidArgumentException('The key-check must be 32 bytes.');
        }

        return new self(
            $this->formatMajor, $this->formatMinor, $this->headerLength, $this->criticalFlags, $this->ignorableFlags,
            $this->cipherId, $this->kdfId, $this->blockLog2, $this->kdfP1, $this->kdfP2, $this->salt, $this->packageId,
            $this->authenticatedBytes, $keycheck
        );
    }

    /** The bytes to write to the file. Requires a sealed header. */
    public function toBytes(): string
    {
        if (strlen($this->keycheck) !== PackageFormat::KEYCHECK_LEN) {
            throw new \LogicException('The header has no key-check yet: seal() it with the passphrase first.');
        }

        return $this->authenticatedBytes.$this->keycheck;
    }

    public function blockSize(): int
    {
        return 1 << $this->blockLog2;
    }

    private static function computeKeycheck(DerivedKeys $keys, string $authenticatedBytes): string
    {
        return hash_hmac('sha256', PackageFormat::KEYCHECK_CONTEXT.$authenticatedBytes, $keys->check(), true);
    }
}
