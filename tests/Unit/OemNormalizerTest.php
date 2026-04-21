<?php

namespace Tests\Unit;

use App\Services\OemNormalizerService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OemNormalizerTest extends TestCase
{
    private OemNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new OemNormalizerService();
    }

    #[Test]
    public function it_strips_hyphens(): void
    {
        $this->assertSame('06L906036L', $this->normalizer->normalize('06L-906-036-L'));
    }

    #[Test]
    public function it_strips_spaces(): void
    {
        $this->assertSame('BMW11227835903', $this->normalizer->normalize('BMW 11 22 7 835 903'));
    }

    #[Test]
    public function it_uppercases(): void
    {
        $this->assertSame('06L906036L', $this->normalizer->normalize('06l906036l'));
    }

    #[Test]
    public function it_strips_dots_and_slashes(): void
    {
        $this->assertSame('1K0615301AA', $this->normalizer->normalize('1K0.615.301.AA'));
        $this->assertSame('1K0615301AA', $this->normalizer->normalize('1K0/615/301/AA'));
    }

    #[Test]
    public function it_handles_already_normalized_input(): void
    {
        $this->assertSame('ABC123', $this->normalizer->normalize('ABC123'));
    }

    #[Test]
    public function it_handles_empty_string(): void
    {
        $this->assertSame('', $this->normalizer->normalize(''));
    }

    #[Test]
    public function it_strips_all_special_characters(): void
    {
        $this->assertSame('TEST123', $this->normalizer->normalize('TEST!@#$%^&*()_+={}[]|\\;:\'",.<>?/`~123'));
    }

    #[Test]
    public function is_normalized_returns_true_for_clean_input(): void
    {
        $this->assertTrue($this->normalizer->isNormalized('ABC123'));
        $this->assertTrue($this->normalizer->isNormalized(''));
    }

    #[Test]
    public function is_normalized_returns_false_for_dirty_input(): void
    {
        $this->assertFalse($this->normalizer->isNormalized('abc-123'));
        $this->assertFalse($this->normalizer->isNormalized('06L-906-036-L'));
    }

    #[Test]
    public function normalize_is_idempotent(): void
    {
        $input = '06L-906-036-L';
        $once  = $this->normalizer->normalize($input);
        $twice = $this->normalizer->normalize($once);

        $this->assertSame($once, $twice);
    }
}
