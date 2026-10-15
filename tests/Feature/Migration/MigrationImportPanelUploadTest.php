<?php

namespace Tests\Feature\Migration;

use App\Security\PackageAudit;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Ux\UploadedPackageIntake;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "From this computer": a small package that arrived through the browser's file field becomes a package of the import folder
 * through the same checks as every other way in. Plain files in a temporary folder: no database, nothing outside it.
 */
class MigrationImportPanelUploadTest extends TestCase
{
    private string $dir = '';

    private string $temp = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-upload-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/inbox', 0700, true);
        mkdir($this->dir.'/tmp', 0700, true);
        $this->temp = $this->dir.'/tmp/phpUPLOAD';
        config(['migration.upload.small_max_mb' => 10]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{inbox,tmp}/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) && @unlink($file);
        }

        @rmdir($this->dir.'/inbox');
        @rmdir($this->dir.'/tmp');
        @unlink($this->dir.'/history.ndjson');
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function intake(?\Closure $free = null): UploadedPackageIntake
    {
        return new UploadedPackageIntake(new ImportInbox($this->dir.'/inbox'), new PackageAudit($this->dir.'/history.ndjson'), $free);
    }

    private function upload(int $bytes, string $magic = PackageFormat::MAGIC): void
    {
        file_put_contents($this->temp, $magic.str_repeat('x', max(0, $bytes - strlen($magic))));
    }

    private function refusal(callable $do): MigrationErrorCode
    {
        try {
            $do();
        } catch (MigrationException $e) {
            return $e->errorCode;
        }

        $this->fail('the upload was accepted');
    }

    /** @return list<string> every file in the import folder, with the half-written ones */
    private function folder(): array
    {
        return array_values(array_diff(array_map('basename', glob($this->dir.'/inbox/{,.}*', GLOB_BRACE) ?: []), ['.', '..']));
    }

    #[Test]
    public function a_small_package_lands_in_the_import_folder_under_its_own_name_and_the_history_says_so(): void
    {
        $this->upload(9 * 1024 * 1024);

        $name = $this->intake()->take($this->temp, 'shop.oeparts', 7);

        $this->assertSame('shop.oeparts', $name);
        $this->assertFileEquals($this->temp, $this->dir.'/inbox/shop.oeparts');
        $this->assertNotContains('shop.oeparts.part', $this->folder(), 'no half-written file is left');
        $this->assertSame([['name' => 'shop.oeparts', 'size' => 9 * 1024 * 1024]], array_map(fn (array $p): array => ['name' => $p['name'], 'size' => $p['size']], (new ImportInbox($this->dir.'/inbox'))->list()));

        $history = (string) file_get_contents($this->dir.'/history.ndjson');
        $this->assertStringContainsString('package.uploaded', $history);
        $this->assertStringContainsString('shop.oeparts', $history);
    }

    #[Test]
    public function a_file_above_the_limit_is_refused_with_the_instructions_and_nothing_is_written(): void
    {
        $this->upload(10 * 1024 * 1024 + 1);

        $this->assertSame(MigrationErrorCode::UploadTooLarge, $this->refusal(fn () => $this->intake()->take($this->temp, 'big.oeparts')));
        $this->assertSame([], $this->folder());
    }

    #[Test]
    public function the_limit_can_be_lowered_but_never_raised_above_what_livewire_accepts(): void
    {
        config(['migration.upload.small_max_mb' => 2]);
        $this->assertSame(2 * 1024 * 1024, UploadedPackageIntake::maxBytes());

        config(['migration.upload.small_max_mb' => 500]);
        $this->assertSame(12 * 1024 * 1024, UploadedPackageIntake::maxBytes());

        config(['migration.upload.small_max_mb' => 0]);
        $this->assertSame(1024 * 1024, UploadedPackageIntake::maxBytes());
    }

    #[Test]
    public function a_file_that_is_not_a_package_is_refused_whatever_it_is_called(): void
    {
        $this->upload(4096, 'MZ-this-is-a-program-');

        $this->assertSame(MigrationErrorCode::PackageNotOeparts, $this->refusal(fn () => $this->intake()->take($this->temp, 'shop.oeparts')));
        $this->assertSame([], $this->folder());
    }

    #[Test]
    public function a_name_that_is_not_a_package_name_is_refused_even_when_the_content_is_a_package(): void
    {
        $this->upload(4096);

        foreach (['shop.php', 'shop.oeparts.php', 'shop', '.oeparts', 'sh op.oeparts', 'a;b.oeparts', 'shop.oeparts ', str_repeat('a', 160).'.oeparts'] as $name) {
            $this->assertSame(MigrationErrorCode::PackageNotOeparts, $this->refusal(fn () => $this->intake()->take($this->temp, $name)), $name);
        }

        $this->assertSame([], $this->folder());
    }

    #[Test]
    public function a_name_with_a_path_in_it_keeps_only_the_file_name_and_never_leaves_the_folder(): void
    {
        $this->upload(4096);

        $this->assertSame('shop.oeparts', $this->intake()->take($this->temp, '../../etc/shop.oeparts'));
        $this->assertSame('other.oeparts', $this->intake()->take($this->temp, 'C:\\Users\\me\\other.oeparts'));
        $this->assertEqualsCanonicalizing(['shop.oeparts', 'other.oeparts'], $this->folder());
    }

    #[Test]
    public function a_package_with_the_same_name_as_one_in_the_folder_never_replaces_it(): void
    {
        file_put_contents($this->dir.'/inbox/shop.oeparts', PackageFormat::MAGIC.'the one that was here first');
        $this->upload(4096);

        $name = $this->intake()->take($this->temp, 'shop.oeparts');

        $this->assertNotSame('shop.oeparts', $name);
        $this->assertTrue(ImportInbox::isPackageName($name));
        $this->assertSame(PackageFormat::MAGIC.'the one that was here first', file_get_contents($this->dir.'/inbox/shop.oeparts'));
        $this->assertFileExists($this->dir.'/inbox/'.$name);
    }

    #[Test]
    public function a_disk_without_room_for_the_file_is_refused_before_anything_is_written(): void
    {
        $this->upload(4096);

        $code = $this->refusal(fn () => $this->intake(fn () => 1024)->take($this->temp, 'shop.oeparts'));

        $this->assertSame(MigrationErrorCode::PreflightDisk, $code);
        $this->assertSame([], $this->folder());
    }

    #[Test]
    public function a_missing_upload_is_an_incomplete_file_not_a_crash(): void
    {
        $this->assertSame(MigrationErrorCode::PackageIncomplete, $this->refusal(fn () => $this->intake()->take($this->dir.'/tmp/nothing', 'shop.oeparts')));
    }
}
