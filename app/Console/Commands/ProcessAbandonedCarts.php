<?php

namespace App\Console\Commands;

use App\Models\AbandonedCart;
use App\Models\Cart;
use App\Services\CartRecoveryService;
use Illuminate\Console\Command;

class ProcessAbandonedCarts extends Command
{
    protected $signature = 'abandoned-cart:process';

    protected $description = 'Snapshot abandoned carts and send recovery emails';

    public function handle(CartRecoveryService $recovery): int
    {
        $this->info('Processing abandoned carts...');

        // Same staleness window the dashboard widget shows, so the operator's
        // "Abandoned Carts" list and the automated recovery agree on what
        // "abandoned" means.
        $cutoffTime = now()->subHours((int) settings('dashboard.cart_abandoned_hours', 2));

        $staleCarts = Cart::query()
            ->with(['user', 'items.product'])
            ->where('updated_at', '<', $cutoffTime)
            ->whereHas('items')
            ->get();

        $emailCount = 0;

        foreach ($staleCarts as $cart) {
            // Guest carts carry no email address — nothing to recover to.
            if (! $cart->user?->email) {
                continue;
            }

            // One recovery per customer per 7 days, regardless of cart churn.
            $alreadySent = AbandonedCart::where('user_id', $cart->user_id)
                ->where('recovery_email_sent', true)
                ->where('created_at', '>', now()->subDays(7))
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $snapshotItems = $cart->items->map(fn ($item): array => [
                'id' => $item->id,
                'cart_id' => $item->cart_id,
                'product_id' => $item->product_id,
                'oem_number' => $item->product?->oem_number,
                'oem_number_snapshot' => $item->product?->oem_number,
                'quantity' => $item->quantity,
                'price_at_add' => $item->price_at_add,
                'total_price' => bcmul((string) $item->price_at_add, (string) $item->quantity, 2),
            ])->all();

            $total = array_reduce(
                $snapshotItems,
                fn (string $carry, array $item): string => bcadd($carry, $item['total_price'], 2),
                '0.00',
            );

            $abandonedCart = AbandonedCart::create([
                'user_id' => $cart->user_id,
                'guest_email' => null,
                'cart_snapshot' => [
                    'items' => $snapshotItems,
                    'total' => $total,
                    'customer_name' => $cart->user->name,
                ],
                'last_active_at' => $cart->updated_at,
                'recovery_email_sent' => false,
            ]);

            if ($recovery->send($abandonedCart)) {
                $emailCount++;
                $this->info("Queued recovery email for: {$cart->user->email}");
            }
        }

        $this->info("Processed {$staleCarts->count()} stale carts, queued {$emailCount} recovery emails.");

        return Command::SUCCESS;
    }
}
