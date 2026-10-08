<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Exceptions\BackupException;
use App\Services\Backup\Stages\EncryptTransportStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Backup encryption + transport (Module 21, Chunk 2.4).
 *
 * Covers the AES-256-GCM cipher round-trip/tamper detection and the
 * EncryptTransportStage: mandatory encryption, plaintext removal from staging,
 * off-site streaming to a separate destination disk, and incremental interop
 * (an encrypted baseline manifest is decrypted for the diff).
 */
class BackupEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('offsite');

        $this->statePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-enc-state-'.getmypid();
        @mkdir($this->statePath, 0775, true);
        config(['updates.state_path' => $this->statePath]);
        config(['backup.disk' => 'local', 'backup.staging_disk' => 'local']);
        config(['backup.db.chunk_rows' => 100]);

        $this->fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-enc-fixture-'.getmypid();
        @mkdir($this->fixture, 0775, true);
        file_put_contents($this->fixture.'/keep.txt', 'keep me');
        file_put_contents($this->fixture.'/change.txt', 'original');
        file_put_contents($this->fixture.'/del.txt', 'delete me');
        config(['backup.files.root' => $this->fixture]);

        Schema::create('oe_enc_widget', function ($t) {
            $t->id();
            $t->string('name');
        });
        DB::table('oe_enc_widget')->insert([['name' => 'secret-alpha'], ['name' => 'secret-bravo']]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('oe_enc_widget');
        $this->rrmdir($this->fixture);
        @array_map('unlink', glob($this->statePath.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->statePath);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /* ---- Cipher ---------------------------------------------------------- */

    #[Test]
    public function the_cipher_round_trips_multi_frame_content(): void
    {
        $plain = random_bytes(2_500_000); // > 2 frames (1 MB each)
        $src = $this->statePath.'/plain.bin';
        $enc = $this->statePath.'/cipher.enc';
        $out = $this->statePath.'/restored.bin';
        file_put_contents($src, $plain);

        $cipher = app(BackupCipher::class);
        $meta = $cipher->encryptFile($src, $enc);
        $cipher->decryptFile($enc, $out);

        $this->assertSame($plain, file_get_contents($out));
        $this->assertSame(hash('sha256', $plain), $meta['plain_sha256']);
        $this->assertGreaterThanOrEqual(3, $meta['frames']);
        $this->assertNotSame($plain, file_get_contents($enc), 'stored bytes must be ciphertext');
    }

    #[Test]
    public function tampered_ciphertext_fails_authentication(): void
    {
        $src = $this->statePath.'/p.bin';
        $enc = $this->statePath.'/c.enc';
        file_put_contents($src, 'sensitive customer data');

        $cipher = app(BackupCipher::class);
        $cipher->encryptFile($src, $enc);

        $bytes = file_get_contents($enc);
        $bytes[strlen($bytes) - 1] = chr(ord($bytes[strlen($bytes) - 1]) ^ 0xFF); // flip last byte
        file_put_contents($enc, $bytes);

        $this->expectException(BackupException::class);
        $cipher->decryptFile($enc, $this->statePath.'/x.bin');
    }

    #[Test]
    public function a_missing_key_blocks_encryption(): void
    {
        config(['backup.encryption.key' => '']);

        $this->assertFalse(app(BackupCipher::class)->hasKey());

        $this->expectException(BackupException::class);
        app(BackupCipher::class)->encryptFile($this->statePath.'/a', $this->statePath.'/b');
    }

    /**
     * A self-hoster hitting a real permission/missing-file/disk-space
     * problem on their own server previously got only "Could not open
     * files for encryption" — no path, no reason, undiagnosable without
     * SSH access and a code change just to find out which of the two
     * files failed and why. Confirmed live against a real production
     * failure report.
     */
    #[Test]
    public function a_missing_source_file_reports_the_path_and_reason(): void
    {
        $missing = $this->statePath.'/does-not-exist.bin';

        try {
            app(BackupCipher::class)->encryptFile($missing, $this->statePath.'/out.enc');
            $this->fail('expected a BackupException');
        } catch (BackupException $e) {
            $this->assertStringContainsString($missing, $e->getMessage());
            $this->assertStringContainsString('reading', $e->getMessage());
        }
    }

    /**
     * Inside a Laravel app error_get_last() is always null after a suppressed
     * fopen() failure, so the message used to read "(unknown reason)" for every
     * cause. It must now say what is actually wrong.
     */
    #[Test]
    public function the_failure_reason_is_never_unknown(): void
    {
        $dir = $this->statePath.'/exists';
        @mkdir($dir, 0775, true);

        try {
            app(BackupCipher::class)->encryptFile($dir.'/gone.sql.gz', $dir.'/out.enc');
            $this->fail('expected a BackupException');
        } catch (BackupException $e) {
            $this->assertStringNotContainsString('unknown reason', $e->getMessage());
            $this->assertStringContainsString('file does not exist', $e->getMessage());
            $this->assertStringContainsString('folder exists', $e->getMessage());
        }

        try {
            app(BackupCipher::class)->encryptFile($this->statePath.'/no-folder/gone.sql.gz', $dir.'/out.enc');
            $this->fail('expected a BackupException');
        } catch (BackupException $e) {
            $this->assertStringContainsString('folder do not exist', $e->getMessage());
        }
    }

    #[Test]
    public function an_unwritable_destination_reports_the_path_and_reason(): void
    {
        $src = $this->statePath.'/readable-source.bin';
        file_put_contents($src, 'data');
        $badDst = $this->statePath.'/no-such-directory/out.enc';

        try {
            app(BackupCipher::class)->encryptFile($src, $badDst);
            $this->fail('expected a BackupException');
        } catch (BackupException $e) {
            $this->assertStringContainsString($badDst, $e->getMessage());
            $this->assertStringContainsString('writing', $e->getMessage());
        }
    }

    /**
     * Same incident, stage level: this driver read a part row that another
     * driver had NOT yet saved as encrypted, then found the plaintext gone
     * (the other driver deletes it only AFTER saving the row). That is a
     * finished part, not a failure.
     */
    #[Test]
    public function a_part_secured_by_another_driver_between_query_and_read_is_not_a_failure(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local', 'started_at' => now(),
        ]);
        $part = $run->parts()->create([
            'type' => 'db', 'sequence' => 0, 'name' => 'products', 'disk' => 'local',
            'path' => 'backups/'.$run->id.'/db/products.data.325.sql.gz', 'bytes' => 5,
        ]);

        $stale = BackupChunk::find($part->id); // what THIS driver read: not encrypted yet

        // The other driver finishes: row saved as encrypted, plaintext never existed here.
        $part->update(['meta' => ['encrypted' => true]]);

        $stage = new EncryptTransportStage(app(BackupCipher::class));
        $securePart = new \ReflectionMethod($stage, 'securePart');
        $securePart->invoke($stage, $run, $stale);

        $this->assertTrue((bool) ($part->refresh()->meta['encrypted'] ?? false));
    }

    /** A source that is missing while the row is STILL unencrypted is real data loss and must still fail loudly. */
    #[Test]
    public function a_missing_source_on_an_unencrypted_part_still_fails_with_a_clear_reason(): void
    {
        $run = BackupRun::create([
            'profile' => BackupRun::PROFILE_FULL, 'status' => BackupRun::STATUS_RUNNING,
            'trigger' => BackupRun::TRIGGER_MANUAL, 'disk' => 'local', 'started_at' => now(),
        ]);
        $part = $run->parts()->create([
            'type' => 'db', 'sequence' => 0, 'name' => 'products', 'disk' => 'local',
            'path' => 'backups/'.$run->id.'/db/lost.sql.gz', 'bytes' => 5,
        ]);

        $stage = new EncryptTransportStage(app(BackupCipher::class));
        $securePart = new \ReflectionMethod($stage, 'securePart');

        try {
            $securePart->invoke($stage, $run, $part);
            $this->fail('expected a BackupException');
        } catch (BackupException $e) {
            $this->assertStringContainsString('lost.sql.gz', $e->getMessage());
            $this->assertStringNotContainsString('unknown reason', $e->getMessage());
        }
    }

    /* ---- Transport stage (via the manager) ------------------------------ */

    #[Test]
    public function a_full_backup_encrypts_every_part_and_drops_plaintext(): void
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status);
        $this->assertGreaterThan(0, $run->part_count);

        foreach ($run->parts as $part) {
            $this->assertTrue((bool) ($part->meta['encrypted'] ?? false), $part->name.' is encrypted');
            $this->assertSame('aes-256-gcm', $part->meta['cipher']);
            $this->assertStringEndsWith('.enc', $part->path);

            // Plaintext staging file is gone; only the .enc remains.
            $plain = Str::beforeLast($part->path, '.enc');
            Storage::disk('local')->assertMissing($plain);
            Storage::disk('local')->assertExists($part->path);
        }
    }

    #[Test]
    public function an_encrypted_db_part_decrypts_back_to_its_sql(): void
    {
        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        $run = app(BackupManager::class)->run($run);

        $part = $run->parts()
            ->where('name', 'oe_enc_widget')
            ->where('meta->kind', 'data')
            ->firstOrFail();

        $enc = Storage::disk($part->disk)->get($part->path);
        $sql = gzdecode(app(BackupCipher::class)->decryptData($enc));

        $this->assertStringContainsString('INSERT INTO `oe_enc_widget`', $sql);
        $this->assertStringContainsString('secret-alpha', $sql);
        // The stored ciphertext must NOT expose the plaintext.
        $this->assertStringNotContainsString('secret-alpha', $enc);
    }

    #[Test]
    public function off_site_destination_streams_encrypted_parts_and_clears_local(): void
    {
        config(['backup.disk' => 'offsite']); // staging stays 'local'

        $run = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        $run = app(BackupManager::class)->run($run);

        $this->assertSame(BackupRun::STATUS_SUCCESS, $run->status, (string) $run->error);

        $dbPart = $run->parts()->where('type', BackupChunk::TYPE_DB)->firstOrFail();
        $this->assertSame('offsite', $dbPart->disk);
        Storage::disk('offsite')->assertExists($dbPart->path);

        // Nothing left behind on local staging for this run.
        Storage::disk('local')->assertMissing($dbPart->path);
        Storage::disk('local')->assertMissing(Str::beforeLast($dbPart->path, '.enc'));
    }

    #[Test]
    public function an_incremental_backup_decrypts_the_encrypted_baseline(): void
    {
        // Baseline full backup (its file manifest is stored encrypted).
        $first = app(BackupManager::class)->start(BackupRun::PROFILE_FULL);
        app(BackupManager::class)->run($first);

        // Mutate the tree.
        file_put_contents($this->fixture.'/change.txt', 'CHANGED');
        touch($this->fixture.'/change.txt', time() + 10);
        file_put_contents($this->fixture.'/new.txt', 'brand new');
        @unlink($this->fixture.'/del.txt');

        $second = app(BackupManager::class)->start(BackupRun::PROFILE_FULL, BackupRun::TRIGGER_MANUAL, ['incremental' => true]);
        app(BackupManager::class)->run($second);

        // Decrypt the incremental run's own file manifest to read the diff.
        $part = $second->parts()->where('name', 'files-manifest')->firstOrFail();
        $manifest = json_decode(gzdecode(app(BackupCipher::class)->decryptData(
            Storage::disk($part->disk)->get($part->path)
        )), true);

        $this->assertSame($first->id, $manifest['baseline_run_id']);
        $this->assertSame(1, $manifest['counts']['unchanged'], 'keep.txt');
        $this->assertSame(2, $manifest['counts']['archived'], 'change.txt + new.txt');
        $this->assertSame(1, $manifest['counts']['deleted'], 'del.txt');
    }
}
