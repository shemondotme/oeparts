<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\FrameCodec;
use App\Services\Migration\Package\Header;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * The secrets entry has its own key, so possessing the data key (what every ordinary read uses)
 * is not enough to read it, and its plaintext is nowhere in the file.
 */
class SecretsIsolationTest extends TestCase
{
    use BuildsPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();

        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{"a":1}');
        $writer->addSecrets("{\"key\":\"mail.password\",\"value\":\"hunter2-canary\"}\n");
        $writer->seal();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    /** @return array{frame: string, head: array<string, int>} */
    private function secretsFrame(): array
    {
        $walk = $this->walk($this->packagePath());
        $secrets = $walk['entries'][2];
        $this->assertSame(FrameCodec::KEY_CLASS_SECRETS, $secrets['head']['keyClass']);

        $bytes = (string) file_get_contents($this->packagePath());

        return [
            'frame' => substr($bytes, $secrets['offset'] + FrameCodec::ENTRY_HEADER_LEN, FrameCodec::FRAME_HEADER_LEN + $secrets['head']['plainLen']),
            'head' => $secrets['head'],
        ];
    }

    #[Test]
    public function the_secrets_plaintext_is_nowhere_in_the_file(): void
    {
        $bytes = (string) file_get_contents($this->packagePath());

        $this->assertStringNotContainsString('hunter2-canary', $bytes);
        $this->assertStringNotContainsString('mail.password', $bytes);
    }

    #[Test]
    public function the_data_key_cannot_decrypt_the_secrets_entry(): void
    {
        ['frame' => $frame, 'head' => $head] = $this->secretsFrame();

        // A codec whose "secrets" key is really the data key: what someone holding only the data key has.
        $bytes = (string) file_get_contents($this->packagePath());
        $opened = Header::open(substr($bytes, 0, 128), $this->passphrase, $this->weakKdf());
        $dataOnly = new FrameCodec($opened->keys->data(), $opened->keys->data(), $opened->header->packageId, $opened->header->blockLog2);

        $this->expectException(CorruptException::class);
        $dataOnly->decryptFrame($frame, $head['entryId'], FrameCodec::KEY_CLASS_SECRETS, 0, $head['frameCount'], $head['plainLen']);
    }

    #[Test]
    public function the_secrets_frame_cannot_be_passed_off_as_a_data_frame(): void
    {
        ['frame' => $frame, 'head' => $head] = $this->secretsFrame();
        $walk = $this->walk($this->packagePath());

        $this->expectException(CorruptException::class);
        $walk['codec']->decryptFrame($frame, $head['entryId'], FrameCodec::KEY_CLASS_DATA, 0, $head['frameCount'], $head['plainLen']);
    }
}
