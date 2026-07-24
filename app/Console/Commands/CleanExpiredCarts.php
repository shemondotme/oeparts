<?php

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredCarts extends Command
{
    protected $signature = 'cart:clean';

    protected $description = 'Clean expired carts from the database';

    public function handle(): int
    {
        $this->info('Cleaning expired carts...');

        // Delete cart items for expired carts first (due to FK constraints)
        $expiredCartIds = DB::table('carts')
            ->where('expires_at', '<', now())
            ->pluck('id');

        if ($expiredCartIds->isEmpty()) {
            $this->info('No expired carts found.');
            return Command::SUCCESS;
        }

        $deletedItemsCount = DB::table('cart_items')
            ->whereIn('cart_id', $expiredCartIds)
            ->delete();

        $deletedCartsCount = DB::table('carts')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deletedItemsCount} cart items from {$deletedCartsCount} expired carts.");

        return Command::SUCCESS;
    }
}
