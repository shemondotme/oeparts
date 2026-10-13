<?php

namespace Tests\Unit\Migration\Package;

use App\Support\AtomicJsonFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * The trailer carries the SHA-256 of every byte before it: the keyless check anyone can make after
 * a transfer. A resumed writer keeps the running hash between processes (a serialised HashContext);
 * if that cannot be restored it must fall back to hashing the committed bytes once at seal. Either
 * way the trailer hash has to equal an independent SHA-256 of the file, which walk() verifies.
 */
class HashContextResumeTest extends TestCase
{
    use BuildsPackages;

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

    private function stateFile(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->packagePath().'.part.ledger/state.json');
    }

    private function interruptedPackage(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', str_repeat('A', 3000));
        $writer->addEntryFromString('report/b.json', 'report', str_repeat('B', 3000));
        unset($writer);
    }

    #[Test]
    public function the_running_hash_is_stored_as_a_serialised_hash_context(): void
    {
        $this->interruptedPackage();

        $state = $this->stateFile()->read();

        $this->assertFalse($state['needs_rehash']);
        $this->assertStringStartsWith('O:11:"HashContext"', base64_decode($state['hash_context']));
    }

    #[Test]
    public function a_resumed_package_has_the_correct_whole_file_hash_from_the_restored_context(): void
    {
        $this->interruptedPackage();

        $resumed = $this->resumeWriter();
        $resumed->addEntryFromString('report/c.json', 'report', str_repeat('C', 3000));
        $result = $resumed->seal();

        // walk() recomputes SHA-256 of every byte before the trailer and compares it with the trailer.
        $walk = $this->walk($result['path']);

        $this->assertSame($walk['fileSha256'], $result['sha256']);
        $this->assertSame(hash('sha256', substr((string) file_get_contents($result['path']), 0, $walk['tocEnd'])), $result['sha256']);
    }

    #[Test]
    public function a_context_that_cannot_be_restored_falls_back_to_hashing_the_file_once(): void
    {
        $this->interruptedPackage();

        $this->stateFile()->update(function (array $state): array {
            $state['hash_context'] = base64_encode('this is not a serialised HashContext');

            return $state;
        });

        $resumed = $this->resumeWriter();
        $resumed->addEntryFromString('report/c.json', 'report', str_repeat('C', 3000));
        $result = $resumed->seal();

        $walk = $this->walk($result['path']);
        $this->assertSame($walk['fileSha256'], $result['sha256'], 'the fallback produces the same, correct hash');
    }

    #[Test]
    public function a_missing_context_or_an_explicit_rehash_flag_also_takes_the_fallback(): void
    {
        foreach ([['hash_context' => null], ['needs_rehash' => true]] as $change) {
            $this->removeWorkDir();
            $this->makeWorkDir();
            $this->interruptedPackage();

            $this->stateFile()->update(fn (array $state): array => array_merge($state, $change));

            $resumed = $this->resumeWriter();
            $resumed->addEntryFromString('report/c.json', 'report', 'CCC');
            $result = $resumed->seal();

            $this->assertSame($this->walk($result['path'])['fileSha256'], $result['sha256'], json_encode($change));
        }
    }

    #[Test]
    public function an_object_of_another_class_planted_in_the_state_is_never_instantiated(): void
    {
        $this->interruptedPackage();

        $this->stateFile()->update(function (array $state): array {
            $state['hash_context'] = base64_encode(serialize(new \ArrayObject(['x' => 1])));

            return $state;
        });

        // unserialize() is restricted to HashContext, so this is ignored and the fallback is used.
        $resumed = $this->resumeWriter();
        $resumed->addEntryFromString('report/c.json', 'report', 'CCC');
        $result = $resumed->seal();

        $this->assertSame($this->walk($result['path'])['fileSha256'], $result['sha256']);
    }
}
