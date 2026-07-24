<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupRun;
use App\Services\Backup\BackupCipher;
use App\Services\Backup\BackupManager;
use App\Services\Backup\Exceptions\BackupException;
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
        $src   = $this->statePath.'/plain.bin';
        $enc   = $this->statePath.'/cipher.enc';
        $out   = $this->statePath.'/restored.bin';
        file_put_contents($src, $plain);

        $cipher = app(BackupCipher::class);
        $meta   = $cipher->encryptFile($src, $enc);
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

        $bytes            = file_get_contents($enc);
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
        $part     = $second->parts()->where('name', 'files-manifest')->firstOrFail();
        $manifest = json_decode(gzdecode(app(BackupCipher::class)->decryptData(
            Storage::disk($part->disk)->get($part->path)
        )), true);

        $this->assertSame($first->id, $manifest['baseline_run_id']);
        $this->assertSame(1, $manifest['counts']['unchanged'], 'keep.txt');
        $this->assertSame(2, $manifest['counts']['archived'], 'change.txt + new.txt');
        $this->assertSame(1, $manifest['counts']['deleted'], 'del.txt');
    }
}
