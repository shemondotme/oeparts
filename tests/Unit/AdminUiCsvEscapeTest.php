<?php

namespace Tests\Unit;

use App\Filament\Support\AdminUi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * AdminUi::exportCsvBulkAction() feeds unauthenticated-visitor-supplied free
 * text (review comments, refund reasons, contact/part-inquiry messages)
 * straight into CSV cells a super_admin later opens in Excel/Google Sheets —
 * a cell starting with =/+/-/@ executes as a formula there, not just
 * displaying as text (CWE-1236). escapeCsvFormula() is the mitigation.
 */
class AdminUiCsvEscapeTest extends TestCase
{
    private function escape(string $value): string
    {
        $method = new \ReflectionMethod(AdminUi::class, 'escapeCsvFormula');
        $method->setAccessible(true);

        return $method->invoke(null, $value);
    }

    #[Test]
    #[TestWith(['=HYPERLINK("http://evil.test","click")'])]
    #[TestWith(['+1+1'])]
    #[TestWith(['-1+1'])]
    #[TestWith(['@SUM(A1:A9)'])]
    #[TestWith(["\tsneaky"])]
    public function a_formula_triggering_leading_character_is_neutralized(string $value): void
    {
        $escaped = $this->escape($value);

        $this->assertSame("'".$value, $escaped);
        $this->assertStringStartsNotWith('=', $escaped);
    }

    #[Test]
    #[TestWith(['Great product, works well'])]
    #[TestWith(['5 stars - would buy again'])]
    #[TestWith(['john@example.com'])]
    #[TestWith([''])]
    public function ordinary_text_passes_through_unchanged(string $value): void
    {
        $this->assertSame($value, $this->escape($value));
    }
}
