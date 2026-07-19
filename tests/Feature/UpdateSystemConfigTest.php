<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Update & Recovery System (Module 21) — Chunk 0.3 config + permissions.
 */
class UpdateSystemConfigTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function updates_config_is_loaded_with_expected_shape(): void
    {
        $this->assertTrue(config('updates.enabled'));
        $this->assertSame('stable', config('updates.channel'));
        $this->assertTrue(config('updates.download.verify_sha256'));
        $this->assertContains('zip', config('updates.required_extensions'));
        $this->assertContains('vendor', config('updates.core_paths'));
        $this->assertContains('storage', config('updates.preserve_paths'));
        $this->assertSame('updates', config('updates.log_channel'));
    }

    #[Test]
    public function backup_encryption_is_mandatory(): void
    {
        // GDPR — backups hold customer PII (rule #45). Encryption must never be off.
        $this->assertTrue(config('backup.encryption.enabled'));
        $this->assertSame('aes-256-gcm', config('backup.encryption.cipher'));
        $this->assertSame(7, config('backup.retention.daily'));
        // vendor/ is excluded by default — backing up + encrypting the entire vendor/
        // tree previously segfaulted PHP on Windows for a real full backup (rule #49).
        // composer.json/composer.lock ARE still backed up, so `composer install
        // --no-dev` reproduces vendor/ on restore. OE_BACKUP_INCLUDE_VENDOR=true opts
        // back into the old fully self-contained (but crash-prone) behaviour.
        $this->assertContains('vendor', config('backup.files.exclude'));
    }

    #[Test]
    public function dedicated_updates_log_channel_exists(): void
    {
        $this->assertIsArray(config('logging.channels.updates'));
        $this->assertSame('daily', config('logging.channels.updates.driver'));
    }

    #[Test]
    public function update_system_permissions_exist_on_admin_guard(): void
    {
        foreach (['view updates', 'apply updates', 'manage backups', 'restore backups', 'run recovery'] as $name) {
            $this->assertTrue(
                Permission::where('guard_name', 'admin')->where('name', $name)->exists(),
                "Permission [{$name}] should exist on the admin guard",
            );
        }
    }
}
