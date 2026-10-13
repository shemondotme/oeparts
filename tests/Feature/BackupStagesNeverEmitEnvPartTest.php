<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Services\Backup\Contracts\BackupStage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comments in config/backup.php, FileBackupStage and the BackupStage contract once
 * promised a "dedicated encrypted env part" that no stage ever produced. The truth
 * is the opposite and is relied on by site migration: a backup never carries the
 * .env file, so APP_KEY, the DB password and OE_BACKUP_KEY never travel inside one.
 * This pins that, so the claim cannot quietly come back.
 */
class BackupStagesNeverEmitEnvPartTest extends TestCase
{
    #[Test]
    public function no_configured_stage_produces_an_env_part(): void
    {
        $seen = [];

        foreach ((array) config('backup.stages') as $profile => $stages) {
            foreach ($stages as $class) {
                $stage = app($class);

                $this->assertInstanceOf(BackupStage::class, $stage);
                $this->assertNotSame(BackupChunk::TYPE_ENV, $stage->key(), "{$class} (profile {$profile}) must not emit an env part");
                $seen[$stage->key()] = true;
            }
        }

        $this->assertNotEmpty($seen);
    }

    #[Test]
    public function the_env_file_is_excluded_from_every_file_backup(): void
    {
        $this->assertContains('.env', config('backup.files.exclude'));
    }

    #[Test]
    public function the_documentation_no_longer_claims_an_env_part_exists(): void
    {
        foreach ([
            config_path('backup.php'),
            app_path('Services/Backup/Stages/FileBackupStage.php'),
            app_path('Services/Backup/Contracts/BackupStage.php'),
        ] as $file) {
            $this->assertStringNotContainsString('handled as a dedicated encrypted', (string) file_get_contents($file), basename($file));
            $this->assertStringNotContainsString('DB + env', (string) file_get_contents($file), basename($file));
        }
    }
}
