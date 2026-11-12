<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicDiskUrlTest extends TestCase
{
    public function test_public_disk_url_has_no_double_slash_when_app_url_ends_with_a_slash(): void
    {
        $config = (function (string $appUrl): array {
            putenv('APP_URL='.$appUrl);
            $_ENV['APP_URL'] = $appUrl;
            $_SERVER['APP_URL'] = $appUrl;

            return require base_path('config/filesystems.php');
        })('https://oeparts.lt/');

        $this->assertSame('https://oeparts.lt/storage', $config['disks']['public']['url']);
    }
}
