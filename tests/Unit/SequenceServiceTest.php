<?php

namespace Tests\Unit;

use App\Enums\SequenceType;
use App\Models\Sequence;
use App\Services\SequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private SequenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SequenceService();

        Sequence::create([
            'type'             => SequenceType::Order,
            'current_value'    => 0,
            'resets_monthly'   => true,
            'last_reset_month' => now()->format('Y-m'),
        ]);

        Sequence::create([
            'type'             => SequenceType::Invoice,
            'current_value'    => 0,
            'resets_monthly'   => true,
            'last_reset_month' => now()->format('Y-m'),
        ]);

        Sequence::create([
            'type'             => SequenceType::Rma,
            'current_value'    => 0,
            'resets_monthly'   => false,
            'last_reset_month' => null,
        ]);
    }

    #[Test]
    public function order_number_starts_at_000001(): void
    {
        $number = $this->service->nextOrderNumber();
        $month  = now()->format('Ym');

        $this->assertSame("ORD-{$month}-000001", $number);
    }

    #[Test]
    public function order_number_increments_sequentially(): void
    {
        $first  = $this->service->nextOrderNumber();
        $second = $this->service->nextOrderNumber();
        $third  = $this->service->nextOrderNumber();

        $month = now()->format('Ym');
        $this->assertSame("ORD-{$month}-000001", $first);
        $this->assertSame("ORD-{$month}-000002", $second);
        $this->assertSame("ORD-{$month}-000003", $third);
    }

    #[Test]
    public function invoice_number_uses_inv_prefix(): void
    {
        $number = $this->service->nextInvoiceNumber();
        $month  = now()->format('Ym');

        $this->assertSame("INV-{$month}-000001", $number);
    }

    #[Test]
    public function rma_number_has_no_month_segment(): void
    {
        $this->assertSame('RMA-000001', $this->service->nextRmaNumber());
    }

    #[Test]
    public function rma_number_increments_without_month(): void
    {
        $this->assertSame('RMA-000001', $this->service->nextRmaNumber());
        $this->assertSame('RMA-000002', $this->service->nextRmaNumber());
    }

    #[Test]
    public function order_resets_when_month_changes(): void
    {
        Sequence::where('type', SequenceType::Order)->update([
            'current_value'    => 42,
            'last_reset_month' => '2020-01',
        ]);

        $number = $this->service->nextOrderNumber();
        $month  = now()->format('Ym');

        $this->assertSame("ORD-{$month}-000001", $number);

        $sequence = Sequence::where('type', SequenceType::Order)->first();
        $this->assertSame(now()->format('Y-m'), $sequence->last_reset_month);
    }

    #[Test]
    public function order_sequence_persists_in_database(): void
    {
        $this->service->nextOrderNumber();
        $this->service->nextOrderNumber();

        $sequence = Sequence::where('type', SequenceType::Order)->first();
        $this->assertSame(2, $sequence->current_value);
    }

    #[Test]
    public function sequences_are_independent_of_each_other(): void
    {
        $this->service->nextOrderNumber();
        $this->service->nextOrderNumber();
        $this->service->nextInvoiceNumber();
        $this->service->nextRmaNumber();
        $this->service->nextRmaNumber();

        $month = now()->format('Ym');

        $this->assertSame("ORD-{$month}-000003", $this->service->nextOrderNumber());
        $this->assertSame("INV-{$month}-000002", $this->service->nextInvoiceNumber());
        $this->assertSame('RMA-000003', $this->service->nextRmaNumber());
    }
}
