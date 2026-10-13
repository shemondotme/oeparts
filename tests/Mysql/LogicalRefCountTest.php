<?php

namespace Tests\Mysql;

use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Services\Migration\Export\DependencyResolver;
use App\Services\Migration\Export\FkGraph;
use App\Services\Migration\Export\LogicalRefRegistry;
use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * The JSON-aware counts of LogicalRefRegistry on a real server: a JSON null is not an SQL NULL on MySQL,
 * so "has a proof path" has to ask for the JSON type, which SQLite cannot verify.
 */
class LogicalRefCountTest extends MysqlTestCase
{
    /** @var array<int, int> */
    private array $orders = [];

    protected function tearDown(): void
    {
        foreach ($this->orders as $id) {
            DB::table('payments')->where('order_id', $id)->delete();
            DB::table('refund_requests')->where('order_id', $id)->delete();
            DB::table('order_items')->where('order_id', $id)->delete();
            DB::table('orders')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function json_nulls_empty_strings_and_missing_keys_do_not_count_as_files_on_mysql(): void
    {
        $order = Order::factory()->create();
        $this->orders[] = $order->id;

        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => ['a.jpg']]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => []]);
        RefundRequest::factory()->create(['order_id' => $order->id, 'return_images' => null]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['proof_path' => 'proofs/a.pdf']]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['proof_path' => null]]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['proof_path' => '']]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => ['method' => 'card']]);
        Payment::factory()->create(['order_id' => $order->id, 'gateway_response' => null]);

        $resolution = (new DependencyResolver(new GroupRegistry, new FkGraph))->resolve([GroupKey::Sales]);
        $warnings = (new LogicalRefRegistry(new GroupRegistry, 1_000_000_000))->warningsFor($resolution);
        $counts = collect($warnings)->mapWithKeys(fn ($w): array => [$w->ref->id => $w->count])->all();

        $this->assertSame(1, $counts['refund_pictures'] ?? 0);
        $this->assertSame(1, $counts['payment_proofs'] ?? 0);
    }
}
