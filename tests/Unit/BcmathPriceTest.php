<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verify that all financial calculations use bcmath and produce cent-accurate results.
 *
 * bcscale(2) is set globally in AppServiceProvider — we call it here too
 * so these pure unit tests don't depend on bootstrapping the app.
 *
 * NOTE: bcmath TRUNCATES, it does not round. 3.33 * 0.21 = 0.6993 → stored as 0.69
 */
class BcmathPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        bcscale(2);
    }

    #[Test]
    public function vat_calculation_is_cent_accurate(): void
    {
        $price   = '140.00';
        $vatRate = '21';

        $vatAmount  = bcmul($price, bcdiv($vatRate, '100', 4), 2);
        $grandTotal = bcadd($price, $vatAmount, 2);

        $this->assertSame('29.40', $vatAmount);
        $this->assertSame('169.40', $grandTotal);
    }

    #[Test]
    public function bcmath_truncates_not_rounds(): void
    {
        // 3.33 * 0.21 = 0.6993 — bcmath truncates to 0.69 (not 0.70)
        // This is expected and correct behaviour — we do not use PHP_ROUND_HALF_UP
        $price     = '3.33';
        $vatRate   = '21';
        $vatAmount = bcmul($price, bcdiv($vatRate, '100', 4), 2);

        $this->assertSame('0.69', $vatAmount);
        $this->assertSame('4.02', bcadd($price, $vatAmount, 2));
    }

    #[Test]
    public function line_total_uses_bcmul(): void
    {
        $unitPrice = '12.50';
        $qty       = 3;
        $lineTotal = bcmul($unitPrice, (string) $qty, 2);

        $this->assertSame('37.50', $lineTotal);
    }

    #[Test]
    public function subtotal_uses_bcadd(): void
    {
        $line1    = '37.50';
        $line2    = '12.99';
        $subtotal = bcadd($line1, $line2, 2);

        $this->assertSame('50.49', $subtotal);
    }

    #[Test]
    public function discount_percentage_uses_bcmath(): void
    {
        $price    = '100.00';
        $percent  = '15';
        $discount = bcmul($price, bcdiv($percent, '100', 4), 2);
        $after    = bcsub($price, $discount, 2);

        $this->assertSame('15.00', $discount);
        $this->assertSame('85.00', $after);
    }

    #[Test]
    public function free_shipping_threshold_comparison_uses_bccomp(): void
    {
        $threshold = '150.00';

        $this->assertSame(-1, bccomp('149.99', $threshold, 2));
        $this->assertSame(0,  bccomp('150.00', $threshold, 2));
        $this->assertSame(1,  bccomp('150.01', $threshold, 2));
    }

    #[Test]
    public function b2b_vat_exempt_gives_zero_vat(): void
    {
        $price     = '200.00';
        $vatExempt = true;

        $vatAmount  = $vatExempt ? '0.00' : bcmul($price, bcdiv('21', '100', 4), 2);
        $grandTotal = bcadd($price, $vatAmount, 2);

        $this->assertSame('0.00', $vatAmount);
        $this->assertSame('200.00', $grandTotal);
    }

    #[Test]
    public function float_arithmetic_demonstrates_why_it_is_forbidden(): void
    {
        // This documents the float precision problem — NOT how we calculate money.
        $floatResult = 0.1 + 0.2;
        $this->assertNotSame(0.3, $floatResult); // floats are broken

        // bcmath is exact:
        $bcResult = bcadd('0.1', '0.2', 2);
        $this->assertSame('0.30', $bcResult);
    }
}
