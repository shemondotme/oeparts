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

            // Get cart items for snapshot
            $items = DB::table('cart_items')
                ->where('cart_id', $cart->id)
                ->get();

            if ($items->isEmpty()) {
                continue;
            }

            // Determine email recipient
            $email = null;
            $userId = null;

            if ($cart->user_id) {
                $user = User::find($cart->user_id);
                if ($user) {
                    $email = $user->email;
                    $userId = $user->id;
                }
            } elseif ($cart->guest_email) {
                $email = $cart->guest_email;
            }

            if (!$email) {
                continue;
            }

            // Create abandoned cart record
            $abandonedCart = AbandonedCart::create([
                'user_id' => $userId,
                'guest_email' => $cart->guest_email,
                'cart_snapshot' => [
                    'items' => $items->toArray(),
                    'total' => $items->sum(fn($item) => $item->price_at_add * $item->quantity),
                ],
                'last_active_at' => $cart->updated_at,
                'recovery_email_sent' => false,
            ]);

            // Dispatch recovery email job
            dispatch(new SendAbandonedCartEmail($email, $abandonedCart->cart_snapshot));
            
            // Mark as sent
            $abandonedCart->update(['recovery_email_sent' => true]);
            
            $emailCount++;

            $this->info("Queued recovery email for: {$email}");
        }

        $this->info("Processed {$abandonedCarts->count()} abandoned carts, queued {$emailCount} recovery emails.");

        return Command::SUCCESS;
    }
}
