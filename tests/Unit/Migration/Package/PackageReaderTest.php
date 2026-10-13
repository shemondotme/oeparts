<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\BackupPartNotPackageException;
use App\Services\Migration\Package\Exceptions\EntryNotFoundException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;
use App\Services\Migration\Package\Exceptions\NotAPackageException;
use App\Services\Migration\Package\Exceptions\SecretsLockedException;
use App\Services\Migration\Package\Exceptions\TextModeTransferException;
use App\Services\Migration\Package\Exceptions\TruncatedException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

class PackageReaderTest extends TestCase
{
    use BuildsPackages;

    private const SECRETS = "{\"key\":\"mail.password\",\"value\":\"hunter2\"}\n";

    private string $big = '';

    private string $raw = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
        $this->big = random_bytes(2_500_000);
        $this->raw = str_repeat("INSERT INTO t VALUES (1);\n", 4000).random_bytes(5000);
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    /** @return array<string, mixed> the writer's seal() result */
    private function build(bool $secrets = true): array
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/small.json', 'report', '{"ok":true}', ['group' => 'settings']);
        $writer->addEntryFromString('files/big.bin', 'files', $this->big, ['group' => 'files']);

        // Two gzip members back to back, as the file backup appends them.
        $gz = gzencode(substr($this->raw, 0, 60000)).gzencode(substr($this->raw, 60000));
        $writer->addEntryFromString('db/products.sql', 'db', $gz, ['group' => 'catalog', 'codec' => 'gzip', 'raw_len' => strlen($this->raw)]);

        if ($secrets) {
            $writer->addSecrets(self::SECRETS);
        }

        return $writer->seal();
    }

    private function reader(?string $path = null): PackageReader
    {
        return PackageReader::open($path ?? $this->packagePath(), $this->passphrase, $this->weakKdf());
    }

    #[Test]
    public function what_the_writer_wrote_is_what_the_reader_reads(): void
    {
        $sealed = $this->build();
        $reader = $this->reader();

        $this->assertSame($sealed['fingerprint'], $reader->fingerprint());
        $this->assertSame($sealed['sha256'], $reader->fileSha256());
        $this->assertSame($sealed['size'], $reader->fileSize());
        $this->assertSame('old.example', $reader->meta()['source']['domain']);
        $this->assertSame($reader->packageIdHex(), $reader->meta()['package_id']);

        $this->assertSame('{"ok":true}', $reader->readString('report/small.json'));
        $this->assertSame($this->big, $reader->readString('files/big.bin', 3_000_000));
        $this->assertSame($this->raw, $reader->readString('db/products.sql'), 'every gzip member, not only the first');
    }

    #[Test]
    public function streaming_an_entry_delivers_the_same_bytes_in_pieces(): void
    {
        $this->build();

        $pieces = [];
        $this->reader()->readEntry('files/big.bin', function (string $part) use (&$pieces): void {
            $pieces[] = $part;
        });

        $this->assertGreaterThan(1, count($pieces), '2.5 MB is more than one frame');
        $this->assertSame($this->big, implode('', $pieces));
    }

    #[Test]
    public function a_compressed_entry_can_be_read_in_its_stored_form(): void
    {
        $this->build();

        $stored = '';
        $this->reader()->readEntry('db/products.sql', function (string $part) use (&$stored): void {
            $stored .= $part;
        }, decode: false);

        $this->assertStringStartsWith("\x1f\x8b", $stored);
        $this->assertSame(substr($this->raw, 0, 60000), gzdecode($stored), 'gzdecode sees only the first member, which is what the stored form holds');
    }

    #[Test]
    public function a_range_touches_only_the_frames_it_needs_and_matches_the_content(): void
    {
        $this->build();
        $reader = $this->reader();
        $block = $reader->header()->blockSize();

        foreach ([[0, 10], [$block - 5, 10], [$block, 1], [$block * 2 - 1, 2], [strlen($this->big) - 7, 7], [100, 0]] as [$offset, $length]) {
            $this->assertSame(substr($this->big, $offset, $length), $reader->readRange('files/big.bin', $offset, $length), "range {$offset}+{$length}");
        }
    }

    #[Test]
    public function a_range_outside_the_entry_is_refused(): void
    {
        $this->build();

        $this->expectException(\InvalidArgumentException::class);
        $this->reader()->readRange('files/big.bin', strlen($this->big) - 1, 2);
    }

    #[Test]
    public function a_range_of_a_compressed_entry_is_refused(): void
    {
        $this->build();

        $this->expectException(\LogicException::class);
        $this->reader()->readRange('db/products.sql', 0, 1);
    }

    #[Test]
    public function a_string_above_the_cap_is_refused_before_anything_is_read(): void
    {
        $this->build();

        $this->expectException(LimitExceededException::class);
        $this->reader()->readString('files/big.bin', 1000);
    }

    #[Test]
    public function an_unknown_entry_is_reported_as_missing(): void
    {
        $this->build();

        $this->expectException(EntryNotFoundException::class);
        $this->reader()->readString('nothing/here');
    }

    #[Test]
    public function the_secrets_entry_is_hidden_and_locked_until_explicitly_unlocked(): void
    {
        $this->build();
        $reader = $this->reader();

        $this->assertTrue($reader->hasSecrets());
        $this->assertNotContains('secrets', array_column($reader->entries(), 'name'));
        $this->assertContains('meta', array_column($reader->entries(), 'name'));

        foreach ([fn () => $reader->readSecrets(), fn () => $reader->readString('secrets'), fn () => $reader->entry('secrets')] as $attempt) {
            try {
                $attempt();
                $this->fail('the secrets entry was readable while locked');
            } catch (SecretsLockedException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(self::SECRETS, $reader->unlockSecrets()->readSecrets());

        $reader->lockSecrets();
        $this->expectException(SecretsLockedException::class);
        $reader->readSecrets();
    }

    #[Test]
    public function a_package_without_secrets_says_so(): void
    {
        $this->build(secrets: false);
        $reader = $this->reader();

        $this->assertFalse($reader->hasSecrets());
        $this->assertFalse($reader->toc()['contains_secrets']);
    }

    #[Test]
    public function verify_all_passes_on_an_intact_package_and_reports_the_totals(): void
    {
        $this->build();
        $reader = $this->reader();
        $totals = $reader->verifyAll();

        $expectedRaw = strlen($reader->readString('meta')) + 11 + strlen($this->big) + strlen($this->raw) + strlen(self::SECRETS);

        $this->assertSame(5, $totals['entries']);
        $this->assertSame($expectedRaw, $totals['raw_bytes']);
    }

    #[Test]
    public function verification_can_be_split_into_steps_and_resumed_from_a_cursor_that_survives_json(): void
    {
        $this->build();

        $cursor = null;
        $steps = 0;
        do {
            // A new reader every step: what a queued job that restarts between steps would do.
            $reader = $this->reader();
            $step = $reader->verifyStep($cursor, 1_000_000);
            $cursor = json_decode((string) json_encode($step['cursor']), true);
            $reader->close();
            $steps++;
            $this->assertLessThan(100, $steps);
        } while (! $step['done']);

        $this->assertGreaterThan(3, $steps, 'a 5 MB package with a 1 MB budget takes several steps');
    }

    #[Test]
    public function a_nonsense_cursor_restarts_verification_instead_of_being_trusted(): void
    {
        $this->build();
        $reader = $this->reader();

        $cursors = [
            ['phase' => 'hash', 'offset' => 999_999_999, 'ctx' => 'x'],
            ['phase' => 'entries', 'index' => -4],
            ['phase' => 'hash', 'offset' => 500, 'ctx' => base64_encode(serialize(new \ArrayObject))],
            ['bogus' => true],
            ['phase' => 'hash', 'offset' => 500, 'ctx' => '###'],
        ];

        foreach ($cursors as $cursor) {
            do {
                $step = $reader->verifyStep($cursor, 100_000_000);
                $cursor = $step['cursor'];
            } while (! $step['done']);

            $this->assertSame('done', $cursor['phase']);
        }
    }

    #[Test]
    public function a_wrong_passphrase_is_reported_as_wrong_not_as_damage(): void
    {
        $this->build();

        $this->expectException(WrongPassphraseException::class);
        PackageReader::open($this->packagePath(), 'not the passphrase at all', $this->weakKdf());
    }

    #[Test]
    public function the_passphrase_is_proven_from_the_header_alone_so_a_wrong_one_costs_one_header(): void
    {
        $this->build();
        $headerOnly = $this->packagePath('header-only.oeparts');
        file_put_contents($headerOnly, substr((string) file_get_contents($this->packagePath()), 0, 128));

        // Right passphrase: the file is incomplete.
        try {
            PackageReader::open($headerOnly, $this->passphrase, $this->weakKdf());
            $this->fail('expected the file to be called incomplete');
        } catch (TruncatedException) {
            $this->addToAssertionCount(1);
        }

        // Wrong passphrase: still "wrong passphrase", never "incomplete" or "damaged".
        $this->expectException(WrongPassphraseException::class);
        PackageReader::open($headerOnly, 'wrong wrong wrong wrong', $this->weakKdf());
    }

    #[Test]
    public function the_forgiving_forms_of_a_generated_passphrase_open_the_package(): void
    {
        $this->build();

        foreach ([strtolower($this->passphrase), '  '.$this->passphrase.' '] as $typed) {
            $reader = PackageReader::openTyped($this->packagePath(), $typed, $this->weakKdf());
            $this->assertSame('{"ok":true}', $reader->readString('report/small.json'));
        }

        $this->expectException(WrongPassphraseException::class);
        PackageReader::openTyped($this->packagePath(), 'definitely not it', $this->weakKdf());
    }

    #[Test]
    public function peek_reads_a_partly_received_file_without_a_passphrase(): void
    {
        $sealed = $this->build();
        $bytes = (string) file_get_contents($this->packagePath());

        $partial = $this->packagePath('partial.oeparts');
        file_put_contents($partial, substr($bytes, 0, 5000));

        $peek = PackageReader::peek($partial, $this->weakKdf());
        $this->assertFalse($peek->hasTrailer);
        $this->assertSame(5000, $peek->fileSize);
        $this->assertSame($this->reader()->packageIdHex(), $peek->packageIdHex());

        $full = PackageReader::peek($this->packagePath(), $this->weakKdf());
        $this->assertTrue($full->hasTrailer);
        $this->assertSame($sealed['size'], $full->fileSize);
    }

    #[Test]
    public function peek_names_what_a_wrong_file_is(): void
    {
        $cases = [
            'random.oeparts' => [str_repeat('x', 300), NotAPackageException::class],
            'backup.oeparts' => ['OEENC1'.random_bytes(300), BackupPartNotPackageException::class],
            'empty.oeparts' => ['', NotAPackageException::class],
        ];

        foreach ($cases as $name => [$content, $expected]) {
            file_put_contents($this->packagePath($name), $content);

            try {
                PackageReader::peek($this->packagePath($name), $this->weakKdf());
                $this->fail("{$name} was accepted");
            } catch (\Throwable $e) {
                $this->assertInstanceOf($expected, $e, $name);
            }
        }

        $this->build();
        $text = $this->packagePath('ascii.oeparts');
        file_put_contents($text, str_replace("\r\n", "\n", (string) file_get_contents($this->packagePath())));

        $this->expectException(TextModeTransferException::class);
        PackageReader::peek($text, $this->weakKdf());
    }

    #[Test]
    public function a_closed_reader_refuses_further_work(): void
    {
        $this->build();
        $reader = $this->reader();
        $reader->close();
        $reader->close();

        $this->expectException(\LogicException::class);
        $reader->readString('meta');
    }
}
