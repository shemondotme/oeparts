<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\Exceptions\PackageChangedException;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * The escrow carries a package's keys from one request to the next. What matters: it is useless
 * without the site key, it is bound to its run and package, it is private on disk, it expires,
 * and keys that come back out of it open the package exactly like the passphrase did.
 */
class KeyEscrowTest extends TestCase
{
    use BuildsPackages;

    private const PACKAGE_ID = '0123456789abcdef0123456789abcdef';

    private int $now = 1_800_000_000;

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

    private function escrow(string $appKey = 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', int $ttl = 3600): KeyEscrow
    {
        return new KeyEscrow($this->workDir.'/escrow', $appKey, $ttl, fn (): int => $this->now);
    }

    private function keys(): DerivedKeys
    {
        return DerivedKeys::restore(['check' => str_repeat('c', 32), 'data' => str_repeat('d', 32), 'secrets' => str_repeat('s', 32), 'fp' => str_repeat('f', 32)]);
    }

    #[Test]
    public function keys_put_in_come_back_out_identical(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());

        $entry = $this->escrow()->get('import-1');

        $this->assertSame($this->keys()->subkeys(), $entry->keys->subkeys());
        $this->assertSame(self::PACKAGE_ID, $entry->packageIdHex);
        $this->assertSame($this->now, $entry->createdAt);
        $this->assertSame($this->now + 3600, $entry->expiresAt);
    }

    #[Test]
    public function the_file_holds_no_key_material_in_the_clear(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());

        $file = (string) file_get_contents($this->workDir.'/escrow/import-1.key');

        foreach (['c', 'd', 's', 'f'] as $letter) {
            $this->assertStringNotContainsString(str_repeat($letter, 32), $file);
            $this->assertStringNotContainsString(base64_encode(str_repeat($letter, 32)), $file);
        }
    }

    #[Test]
    public function a_different_site_key_cannot_read_it(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());

        try {
            $this->escrow('base64:'.base64_encode(str_repeat('Z', 32)))->get('import-1');
            $this->fail('read with the wrong site key');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::KeyEscrowLost, $e->errorCode);
        }
    }

    #[Test]
    public function the_wrapping_binds_the_entry_to_its_run_id_package_and_expiry(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());
        $path = $this->workDir.'/escrow/import-1.key';
        $original = json_decode((string) file_get_contents($path), true);

        $tampers = [
            'another package id' => ['package_id' => str_repeat('a', 32)],
            'a longer life' => ['expires' => $original['expires'] + 99999],
            'another creation time' => ['created' => $original['created'] - 5],
        ];

        foreach ($tampers as $what => $change) {
            file_put_contents($path, json_encode(array_merge($original, $change)));

            try {
                $this->escrow()->get('import-1');
                $this->fail("{$what} went unnoticed");
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::KeyEscrowLost, $e->errorCode, $what);
            }
        }

        // The same file under another run id: copy import-1.key to import-2.key.
        file_put_contents($path, json_encode($original));
        copy($path, $this->workDir.'/escrow/import-2.key');
        $this->expectException(MigrationException::class);
        $this->escrow()->get('import-2');
    }

    #[Test]
    public function a_damaged_or_foreign_file_is_reported_as_lost_not_as_missing(): void
    {
        mkdir($this->workDir.'/escrow', 0700, true);

        foreach (['not json', '{"v":2}', '[]', '{"v":1,"id":"import-1","created":"x"}'] as $content) {
            file_put_contents($this->workDir.'/escrow/import-1.key', $content);

            try {
                $this->escrow()->get('import-1');
                $this->fail('accepted: '.$content);
            } catch (MigrationException $e) {
                $this->assertSame(MigrationErrorCode::KeyEscrowLost, $e->errorCode);
            }
        }
    }

    #[Test]
    public function there_is_nothing_for_an_unknown_run_and_require_says_it_is_lost(): void
    {
        $this->assertNull($this->escrow()->get('never-stored'));

        $this->expectException(MigrationException::class);
        $this->escrow()->require('never-stored');
    }

    #[Test]
    public function an_entry_expires_and_is_removed_when_found_expired(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());

        $this->now += 3599;
        $this->assertNotNull($this->escrow()->get('import-1'));

        $this->now += 1;
        $this->assertNull($this->escrow()->get('import-1'));
        $this->assertFileDoesNotExist($this->workDir.'/escrow/import-1.key');
    }

    #[Test]
    public function the_file_and_directory_are_private(): void
    {
        $this->escrow()->put('import-1', self::PACKAGE_ID, $this->keys());

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->workDir.'/escrow/import-1.key')), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->workDir.'/escrow')), -4));
    }

    #[Test]
    public function delete_is_idempotent_and_leaves_no_temporary_files(): void
    {
        $escrow = $this->escrow();
        $escrow->put('import-1', self::PACKAGE_ID, $this->keys());
        $escrow->delete('import-1');
        $escrow->delete('import-1');

        $this->assertNull($escrow->get('import-1'));
        $this->assertSame([], array_values(array_diff(scandir($this->workDir.'/escrow'), ['.', '..'])));
    }

    #[Test]
    public function putting_again_replaces_the_entry(): void
    {
        $escrow = $this->escrow();
        $escrow->put('import-1', self::PACKAGE_ID, $this->keys());
        $escrow->put('import-1', str_repeat('b', 32), DerivedKeys::restore(['check' => str_repeat('1', 32), 'data' => str_repeat('2', 32), 'secrets' => str_repeat('3', 32), 'fp' => str_repeat('4', 32)]));

        $entry = $escrow->get('import-1');
        $this->assertSame(str_repeat('b', 32), $entry->packageIdHex);
        $this->assertSame(str_repeat('2', 32), $entry->keys->data());
    }

    #[Test]
    public function ids_that_could_escape_the_directory_are_refused(): void
    {
        foreach (['../x', 'a/b', '', 'a..b', '.hidden', str_repeat('x', 65), "a\0b"] as $id) {
            try {
                $this->escrow()->get($id);
                $this->fail("accepted id '{$id}'");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_missing_or_short_site_key_is_refused_at_construction(): void
    {
        foreach (['', 'short', 'base64:!!!'] as $key) {
            try {
                $this->escrow($key);
                $this->fail("accepted site key '{$key}'");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function prune_removes_expired_entries_stale_temporaries_and_stray_files_but_not_live_ones(): void
    {
        $escrow = $this->escrow(ttl: 3600);
        $escrow->put('live', self::PACKAGE_ID, $this->keys());
        $escrow->put('old', self::PACKAGE_ID, $this->keys());

        $dir = $this->workDir.'/escrow';
        touch($dir.'/.abandoned.tmp', $this->now - 7200);
        touch($dir.'/stray.txt', $this->now - 7200);
        file_put_contents($dir.'/garbage.key', 'x');
        touch($dir.'/garbage.key', $this->now - 7200);

        // "old" was stored an hour and a bit ago; "live" is stored now.
        $this->now += 3601;
        $escrow->put('live', self::PACKAGE_ID, $this->keys());

        $this->assertSame(4, $escrow->prune());
        $this->assertNotNull($escrow->get('live'));
        $this->assertSame(['live.key'], array_values(array_diff(scandir($dir), ['.', '..'])));
    }

    #[Test]
    public function keys_from_the_escrow_open_the_package_without_a_passphrase_and_never_wipe_the_callers_copy(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{"a":1}');
        $writer->seal();

        $reader = PackageReader::open($this->packagePath(), $this->passphrase, $this->weakKdf());
        $this->escrow()->put('import-1', $reader->packageIdHex(), $this->keysOf($this->packagePath()));
        $reader->close();

        $entry = $this->escrow()->require('import-1');
        $second = PackageReader::openWithKeys($this->packagePath(), $entry->keys, $this->weakKdf());
        $this->assertSame('{"a":1}', $second->readString('report/a.json'));
        $second->verifyAll();
        $second->close();

        $this->assertNotSame('', $entry->keys->data(), 'the reader worked on a copy; the escrow entry still has its keys');
        $third = PackageReader::openWithKeys($this->packagePath(), $entry->keys, $this->weakKdf());
        $this->assertSame('{"a":1}', $third->readString('report/a.json'));
    }

    #[Test]
    public function keys_of_another_package_do_not_open_this_one(): void
    {
        $first = $this->newWriter('first.oeparts');
        $first->addEntryFromString('report/a.json', 'report', 'one');
        $first->seal();

        $second = $this->newWriter('second.oeparts');
        $second->addEntryFromString('report/a.json', 'report', 'two');
        $second->seal();

        $this->expectException(PackageChangedException::class);
        PackageReader::openWithKeys($this->packagePath('second.oeparts'), $this->keysOf($this->packagePath('first.oeparts')), $this->weakKdf());
    }

    private function keysOf(string $path): DerivedKeys
    {
        $opened = Header::open(substr((string) file_get_contents($path), 0, PackageFormat::HEADER_LEN), $this->passphrase, $this->weakKdf());

        return $opened->keys;
    }
}
