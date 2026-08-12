<?php

namespace Tests\Unit;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RedirectLoopDetectorTest extends TestCase
{
    use RefreshDatabase;

    private RedirectLoopDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = app(RedirectLoopDetector::class);
    }

    #[Test]
    public function a_clean_terminating_chain_returns_null(): void
    {
        Redirect::create(['from_url' => 'a', 'to_url' => 'b', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $this->assertNull($this->detector->findLoop('start', 'a', null));
    }

    #[Test]
    public function a_direct_reverse_pair_is_detected(): void
    {
        Redirect::create(['from_url' => 'b', 'to_url' => 'a', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $this->assertSame('a', $this->detector->findLoop('a', 'b', null));
    }

    #[Test]
    public function a_three_hop_chain_that_closes_is_detected(): void
    {
        Redirect::create(['from_url' => 'b', 'to_url' => 'c', 'type' => RedirectType::Permanent, 'is_active' => true]);
        Redirect::create(['from_url' => 'c', 'to_url' => 'a', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $this->assertSame('a', $this->detector->findLoop('a', 'b', null));
    }

    #[Test]
    public function an_external_absolute_url_destination_terminates_cleanly(): void
    {
        // to_url pointing at an external domain can never match any
        // from_url (which only ever stores relative internal paths) — the
        // walk should terminate immediately, not error.
        $this->assertNull($this->detector->findLoop('a', 'https://example.com/elsewhere', null));
    }

    #[Test]
    public function ignore_id_excludes_the_record_being_edited(): void
    {
        $redirect = Redirect::create(['from_url' => 'a', 'to_url' => 'b', 'type' => RedirectType::Permanent, 'is_active' => true]);

        // Editing this same record without changing anything must not
        // trip over its own row when walking from "b".
        $this->assertNull($this->detector->findLoop('a', 'b', $redirect->id));
    }

    #[Test]
    public function inactive_redirects_do_not_extend_the_chain(): void
    {
        Redirect::create(['from_url' => 'b', 'to_url' => 'a', 'type' => RedirectType::Permanent, 'is_active' => false]);

        $this->assertNull($this->detector->findLoop('a', 'b', null));
    }

    #[Test]
    public function find_all_loops_flags_only_redirects_whose_chain_actually_loops(): void
    {
        Redirect::create(['from_url' => 'looped-a', 'to_url' => 'looped-b', 'type' => RedirectType::Permanent, 'is_active' => true]);
        Redirect::create(['from_url' => 'looped-b', 'to_url' => 'looped-a', 'type' => RedirectType::Permanent, 'is_active' => true]);
        Redirect::create(['from_url' => 'clean-a', 'to_url' => 'clean-b', 'type' => RedirectType::Permanent, 'is_active' => true]);

        $flagged = $this->detector->findAllLoops();

        $this->assertContains('looped-a', $flagged);
        $this->assertContains('looped-b', $flagged);
        $this->assertNotContains('clean-a', $flagged);
    }
}
