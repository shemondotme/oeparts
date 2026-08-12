<?php

namespace Tests\Feature;

use App\Jobs\PushIndexNow;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductObserverIndexNowTest extends TestCase
{
    use RefreshDatabase;

    private Manufacturer $manufacturer;
    private Condition $condition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#ecfdf5', 'text_color' => '#065f46', 'is_active' => true]
        );
        $this->manufacturer = Manufacturer::create([
            'name' => ['en' => 'Bosch'], 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true,
        ]);
    }

    private function enableIndexNow(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_enabled'], ['value' => '1', 'type' => 'boolean', 'is_encrypted' => false]);
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'indexnow_api_key'], ['value' => 'testkey123', 'type' => 'encrypted', 'is_encrypted' => true]);
        app(SettingsService::class)->forget('seo');
    }

    #[Test]
    public function saving_a_product_dispatches_push_index_now_when_enabled(): void
    {
        $this->enableIndexNow();
        Bus::fake();

        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        Bus::assertDispatched(PushIndexNow::class, fn ($job) => in_array(
            route('frontend.search.results', ['lang' => 'en', 'oem' => '06L906036L']),
            $job->urls,
            true
        ));
    }

    #[Test]
    public function saving_a_product_does_not_dispatch_when_indexnow_is_disabled(): void
    {
        // Default state — disabled.
        Bus::fake();

        Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        Bus::assertNotDispatched(PushIndexNow::class);
    }

    #[Test]
    public function product_save_succeeds_even_when_the_indexnow_endpoint_is_unreachable(): void
    {
        $this->enableIndexNow();
        \Illuminate\Support\Facades\Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        // The job itself would fail, but that's queued/async — the
        // synchronous product save (and the observer's dispatch call) must
        // never be affected by it either way.
        $product = Product::create([
            'manufacturer_id' => $this->manufacturer->id,
            'oem_number' => '06L906036L', 'normalized_oem' => '06L906036L',
            'condition_id' => $this->condition->id, 'price' => '100.00',
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $this->assertTrue($product->exists);
    }

    #[Test]
    public function push_index_now_job_no_ops_when_disabled_regardless_of_dispatch(): void
    {
        // Default disabled — dispatch it directly (bypassing the observer
        // gate) to confirm the job's OWN internal guard also no-ops.
        \Illuminate\Support\Facades\Http::fake();

        (new PushIndexNow(['https://oeparts.test/en/parts/06L906036L']))->handle();

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    #[Test]
    public function push_index_now_job_posts_to_the_indexnow_api_when_enabled(): void
    {
        $this->enableIndexNow();
        \Illuminate\Support\Facades\Http::fake(['api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response('OK', 200)]);

        (new PushIndexNow(['https://oeparts.test/en/parts/06L906036L']))->handle();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.indexnow.org')
                && $request['key'] === 'testkey123'
                && in_array('https://oeparts.test/en/parts/06L906036L', $request['urlList'], true);
        });
    }
}
