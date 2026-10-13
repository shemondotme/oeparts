<?php

namespace Tests\Unit\Migration;

use App\Services\Backup\Exceptions\RestoreException;
use App\Services\Backup\RestoreManager;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\Package\Exceptions\BackupPartNotPackageException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Exceptions\UnsupportedFormatException;
use App\Services\Migration\Package\PackageReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;
use Tests\Unit\Migration\Package\Concerns\BuildsSyntheticPackages;

/**
 * The promise a file format makes: a package written today opens in every later version of the
 * reader, a newer MINOR version only adds things an old reader may ignore, and a MAJOR version or a
 * capability the reader lacks is refused plainly (never half-read). Also: a package and a backup
 * part are never mistaken for each other.
 */
class CompatibilityPolicyTest extends TestCase
{
    use BuildsPackages;
    use BuildsSyntheticPackages;

    private const FIXTURES = __DIR__.'/../../Fixtures/oeparts';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function expected(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURES.'/expected.json'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{0: string}> */
    public static function goldenFiles(): array
    {
        return ['Argon2id' => ['v1-argon2id.oeparts'], 'PBKDF2' => ['v1-pbkdf2.oeparts']];
    }

    // ---- golden files: written once, readable forever -------------------------------------

    #[Test]
    #[DataProvider('goldenFiles')]
    public function a_committed_version_1_package_opens_with_the_production_reader_settings(string $file): void
    {
        $expected = $this->expected();
        $want = $expected['files'][$file];

        // The default Kdf: no test-weak bounds. These files use the lowest settings a reader accepts.
        $reader = PackageReader::open(self::FIXTURES.'/'.$file, $expected['passphrase']);

        $this->assertSame($want['fingerprint'], $reader->fingerprint(), 'PHP and the independent Python reader derive the same fingerprint');
        $this->assertSame($want['package_id'], $reader->packageIdHex());
        $this->assertSame($want['file_sha256'], $reader->fileSha256());
        $this->assertSame($want['format'], $reader->header()->formatMajor.'.'.$reader->header()->formatMinor);
        $this->assertSame($want['kdf'], $reader->header()->kdfId);

        $reader->unlockSecrets();

        foreach ($want['entries'] as $entry) {
            $content = $reader->readString($entry['name']);

            $this->assertSame($entry['decoded_len'], strlen($content), $entry['name']);
            $this->assertSame($entry['decoded_sha256'], hash('sha256', $content), $entry['name']);
        }

        $reader->verifyAll();
    }

    #[Test]
    #[DataProvider('goldenFiles')]
    public function the_golden_content_is_what_the_generator_defines_not_just_what_the_files_say(string $file): void
    {
        $expected = $this->expected();
        $entries = array_column($expected['files'][$file]['entries'], null, 'name');

        $pattern = '';
        for ($i = 0; $i < 150000; $i++) {
            $pattern .= chr(($i * 7 + 3) % 251);
        }
        $rows = str_repeat("INSERT INTO t (id) VALUES (1);\n", 3000)."-- end\n";

        $this->assertSame(hash('sha256', $pattern), $entries['files/pattern.bin']['decoded_sha256']);
        $this->assertSame(hash('sha256', $rows), $entries['db/rows.sql']['decoded_sha256']);
        $this->assertSame(hash('sha256', '{"hello":"oeparts","n":1}'), $entries['report/hello.json']['decoded_sha256']);
        $this->assertSame("{\"key\":\"mail.password\",\"value\":\"hunter2\"}\n", $entries['secrets']['decoded_text']);
    }

    #[Test]
    public function the_golden_entry_with_two_gzip_members_decodes_both(): void
    {
        $reader = PackageReader::open(self::FIXTURES.'/v1-pbkdf2.oeparts', $this->expected()['passphrase']);
        $rows = str_repeat("INSERT INTO t (id) VALUES (1);\n", 3000)."-- end\n";

        $this->assertSame($rows, $reader->readString('db/rows.sql'));
    }

    // ---- minor versions add, major versions break ------------------------------------------

    #[Test]
    public function the_synthetic_builder_produces_a_package_the_reader_accepts(): void
    {
        $this->synthesize($this->packagePath());

        $reader = PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());
        $this->assertSame('{"future":true}', $reader->readString('report/a.json'));
        $reader->verifyAll();
    }

    #[Test]
    public function a_newer_minor_version_with_ignorable_flags_a_longer_header_and_unknown_fields_still_opens(): void
    {
        $this->synthesize($this->packagePath(), [
            'minor' => 7,
            'ignorable' => 0xFFFF_FFFF,
            'headerLen' => 192,
            'tocExtra' => ['new_top_level_field' => ['anything' => [1, 2, 3]], 'compression_hint' => 'zstd-in-v1.9'],
            'recordExtra' => ['future_field' => 'ignored', 'checksums' => ['crc32c' => 'deadbeef']],
        ]);

        $reader = PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());

        $this->assertSame(7, $reader->header()->formatMinor);
        $this->assertSame(192, $reader->header()->headerLength);
        $this->assertSame('{"future":true}', $reader->readString('report/a.json'));
        $this->assertSame('future.example', $reader->meta()['source']['domain']);
        $reader->verifyAll();
    }

    #[Test]
    public function a_newer_major_version_is_refused_with_the_version_too_new_message_before_any_key_derivation(): void
    {
        // The KDF settings are absurd on purpose: if anything derived a key from them this would not
        // end as "unsupported version".
        $this->synthesize($this->packagePath(), ['major' => 2, 'p1' => 999_999_999]);

        foreach ([fn () => PackageReader::peek($this->packagePath(), $this->weakKdf()), fn () => PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf())] as $attempt) {
            try {
                $attempt();
                $this->fail('a version 2 file was accepted');
            } catch (UnsupportedFormatException $e) {
                $this->assertSame(MigrationErrorCode::VersionTooNew, $e->errorCode());
            }
        }
    }

    /** @return array<string, array{0: array<string, int>}> */
    public static function capabilitiesAReaderLacks(): array
    {
        return [
            'a critical header flag' => [['critical' => 0x0000_0001]],
            'the highest critical flag' => [['critical' => 0x8000_0000]],
            'an unknown cipher' => [['cipher' => 2]],
            'cipher id zero' => [['cipher' => 0]],
        ];
    }

    /** @param  array<string, int>  $options */
    #[Test]
    #[DataProvider('capabilitiesAReaderLacks')]
    public function a_capability_the_reader_does_not_have_is_refused_never_half_read(array $options): void
    {
        $this->synthesize($this->packagePath(), $options);

        $this->expectException(UnsupportedFormatException::class);
        PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());
    }

    #[Test]
    public function an_unknown_key_derivation_is_refused_as_a_package_problem(): void
    {
        $this->synthesize($this->packagePath(), ['kdfId' => 9]);

        $this->expectException(PackageException::class);
        PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());
    }

    // ---- packages and backups are different things -----------------------------------------

    #[Test]
    public function a_backup_part_is_named_as_such_and_never_read_as_a_package(): void
    {
        file_put_contents($this->packagePath('part.bin'), 'OEENC1'.random_bytes(2000));

        try {
            PackageReader::peek($this->packagePath('part.bin'), $this->weakKdf());
            $this->fail('a backup part was accepted as a package');
        } catch (BackupPartNotPackageException $e) {
            $this->assertSame(MigrationErrorCode::PackageNotOeparts, $e->errorCode());
        }
    }

    #[Test]
    public function a_package_handed_to_the_backup_restore_importer_is_rejected_and_creates_nothing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('stolen/site.oeparts', (string) file_get_contents(self::FIXTURES.'/v1-pbkdf2.oeparts'));

        $this->expectException(RestoreException::class);
        app(RestoreManager::class)->importManifest('local', 'stolen/site.oeparts');
    }
}
