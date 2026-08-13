<?php

namespace Tests\Unit;

use App\Models\NotFoundLog;
use App\Models\NotFoundLogSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotFoundLogSnapshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function snapshot_records_the_current_unresolved_count(): void
    {
        NotFoundLog::recordHit('/dead-1', 'en', null, '127.0.0.1');
        NotFoundLog::recordHit('/dead-2', 'en', null, '127.0.0.1');

        $count = NotFoundLog::snapshot();

        $this->assertSame(2, $count);
        $this->assertSame(1, NotFoundLogSnapshot::count());
        $this->assertSame(2, NotFoundLogSnapshot::first()->unresolved_count);
    }

    #[Test]
    public function a_second_snapshot_within_the_throttle_window_does_not_duplicate(): void
    {
        NotFoundLog::recordHit('/dead-1', 'en', null, '127.0.0.1');

        NotFoundLog::snapshot();
        NotFoundLog::snapshot();

        $this->assertSame(1, NotFoundLogSnapshot::count());
    }

    #[Test]
    public function resolved_404s_are_not_counted(): void
    {
        NotFoundLog::recordHit('/dead-1', 'en', null, '127.0.0.1');
        NotFoundLog::query()->update(['resolved' => true]);

        $count = NotFoundLog::snapshot();

        $this->assertSame(0, $count);
    }
}
