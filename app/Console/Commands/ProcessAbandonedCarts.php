<?php

namespace App\Console\Commands;

use App\Jobs\SendAbandonedCartEmail;
use App\Models\AbandonedCart;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAbandonedCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'abandoned-cart:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process abandoned carts and send recovery emails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing abandoned carts...');

        // Find carts that are abandoned (inactive for more than 1 hour)
        // and have not been recovered yet
        $cutoffTime = now()->subHour();

        $abandonedCarts = DB::table('carts')
            ->where('updated_at', '<', $cutoffTime)
            ->whereHas('items')
            ->get();

        $emailCount = 0;

        foreach ($abandonedCarts as $cart) {
            // Check if we already sent a recovery email
            $existingRecord = AbandonedCart::where('user_id', $cart->user_id)
                ->where('guest_email', $cart->guest_email ?? null)
                ->where('recovery_email_sent', true)
                ->where('created_at', '>', now()->subDays(7))
                ->first();

            if ($existingRecord) {
                continue; // Already sent recovery email in the last 7 days
            }

            // Get cart items for snapshot with product data
            $items = DB::table('cart_items')
                ->leftJoin('products', 'cart_items.product_id', '=', 'products.id')
                ->where('cart_items.cart_id', $cart->id)
                ->select('cart_items.*', 'products.oem_number')
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            // Determine email recipient
            $email = null;
            $userId = null;
            $customerName = null;

            if ($cart->user_id) {
                $user = User::find($cart->user_id);
                if ($user) {
                    $email = $user->email;
                    $userId = $user->id;
                    $customerName = $user->name;
                }
            } elseif ($cart->guest_email) {
                $email = $cart->guest_email;
            }

            if (!$email) {
                continue;
            }

            // Build snapshot with enriched item data
            $snapshotItems = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'cart_id' => $item->cart_id,
                    'product_id' => $item->product_id,
                    'oem_number' => $item->oem_number ?? null,
                    'oem_number_snapshot' => $item->oem_number ?? null,
                    'quantity' => $item->quantity,
                    'price_at_add' => $item->price_at_add,
                    'total_price' => bcmul((string) $item->price_at_add, (string) $item->quantity, 2),
                ];
            })->toArray();

            $total = array_reduce($snapshotItems, function ($carry, $item) {
                return bcadd($carry, $item['total_price'], 2);
            }, '0.00');

            // Create abandoned cart record
            $abandonedCart = AbandonedCart::create([
                'user_id' => $userId,
                'guest_email' => $cart->guest_email,
                'cart_snapshot' => [
                    'items' => $snapshotItems,
                    'total' => $total,
                    'customer_name' => $customerName,
                ],
                'last_active_at' => $cart->updated_at,
                'recovery_email_sent' => false,
            ]);

            // Dispatch recovery email job
            dispatch(new SendAbandonedCartEmail(
                $email,
                $abandonedCart->cart_snapshot,
                $customerName,
                $user?->locale ?? 'en',
            ));
            
            // Mark as sent
            $abandonedCart->update(['recovery_email_sent' => true]);
            
            $emailCount++;

            $this->info("Queued recovery email for: {$email}");
        }

        $this->info("Processed {$abandonedCarts->count()} abandoned carts, queued {$emailCount} recovery emails.");

        return Command::SUCCESS;
    }
}
