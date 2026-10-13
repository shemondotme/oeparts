<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Install\InstallManager;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the installer writes into .env. Writes go to a throw-away file (never the
 * real .env). The dotenv parser Laravel itself uses is the oracle: whatever the
 * installer writes must read back exactly, and must never be able to add a line.
 */
class InstallerEnvWriteTest extends TestCase
{
    use RefreshDatabase;

    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envFile = tempnam(sys_get_temp_dir(), 'oeparts-env-');
        file_put_contents($this->envFile, "APP_NAME=OeParts\nAPP_URL=http://localhost\nMAIL_PASSWORD=\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
        @unlink($this->envFile.'.tmp');

        parent::tearDown();
    }

    private function installer(): InstallManager
    {
        $file = $this->envFile;

        return new class($file) extends InstallManager
        {
            public function __construct(private string $file) {}

            protected function envPath(): string
            {
                return $this->file;
            }

            public function realStep(string $key, array $input): string
            {
                return parent::runStep($key, $input);
            }
        };
    }

    /** @return array<string, string|null> */
    private function parsedEnv(): array
    {
        return Dotenv::parse((string) file_get_contents($this->envFile));
    }

    #[Test]
    public function every_awkward_value_reads_back_exactly_as_it_was_written(): void
    {
        $values = [
            'plain' => 'simple-value',
            'quote' => 'pa"ss"word',
            'backslash' => 'back\\slash\\',
            'windows_path' => 'C:\\xampp\\mysql',
            'dollar' => 'cost$5',
            'expansion' => '${APP_NAME}',
            'backref' => 'a$1b\\1c',
            'hash' => 'not # a comment',
            'equals' => 'a=b=c',
            'unicode' => 'påsswörd-é-漢字',
            'tab' => "a\tb",
            'leading_space' => '  padded  ',
        ];

        $installer = $this->installer();

        foreach ($values as $name => $value) {
            $installer->updateEnvFile(['TEST_'.strtoupper($name) => $value]);
        }

        $parsed = $this->parsedEnv();

        foreach ($values as $name => $value) {
            $this->assertSame($value, $parsed['TEST_'.strtoupper($name)], "{$name} must round-trip through .env");
        }

        // The entries that were already there are untouched.
        $this->assertSame('OeParts', $parsed['APP_NAME']);
    }

    #[Test]
    public function an_existing_key_is_replaced_in_place_and_never_duplicated(): void
    {
        $installer = $this->installer();

        $installer->updateEnvFile(['APP_URL' => 'https://shop.example.com']);
        $installer->updateEnvFile(['APP_URL' => 'https://shop.example.org']);

        $this->assertSame(1, substr_count((string) file_get_contents($this->envFile), 'APP_URL='));
        $this->assertSame('https://shop.example.org', $this->parsedEnv()['APP_URL']);
    }

    #[Test]
    public function a_line_break_cannot_smuggle_a_second_entry_into_the_file(): void
    {
        $before = (string) file_get_contents($this->envFile);

        foreach (["secret\nAPP_DEBUG=true", "secret\rAPP_DEBUG=true", "nul\0byte"] as $evil) {
            try {
                $this->installer()->updateEnvFile(['MAIL_PASSWORD' => $evil]);
                $this->fail('A value with a line break or control character must be refused.');
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        $this->assertSame($before, (string) file_get_contents($this->envFile), 'a refused value must leave the file untouched');
        $this->assertArrayNotHasKey('APP_DEBUG', $this->parsedEnv());
    }

    #[Test]
    public function an_invalid_key_name_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->installer()->updateEnvFile(['bad key=1' => 'x']);
    }

    #[Test]
    public function the_persist_settings_step_writes_the_confirmed_site_url_as_app_url(): void
    {
        $this->installer()->realStep('persist_settings', [
            'site_name' => 'Parts Shop',
            'site_url' => 'https://shop.example.com/',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $this->assertSame('https://shop.example.com', $this->parsedEnv()['APP_URL'], 'trailing slash removed');
        $this->assertSame('https://shop.example.com/', Setting::where('group', 'general')->where('key', 'site_url')->value('value'));
    }

    #[Test]
    public function a_site_url_that_is_not_http_or_https_is_refused_before_it_reaches_env(): void
    {
        foreach (['ftp://shop.example.com', 'javascript:alert(1)', 'shop.example.com', ''] as $bad) {
            try {
                $this->installer()->realStep('persist_settings', [
                    'site_name' => 'Parts Shop',
                    'site_url' => $bad,
                    'default_locale' => 'en',
                    'timezone' => 'UTC',
                ]);
                $this->fail("'{$bad}' must be refused as an APP_URL");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('http', $e->getMessage());
            }
        }

        $this->assertSame('http://localhost', $this->parsedEnv()['APP_URL'], 'the file is untouched');
    }
}
