<?php

namespace App\Console\Commands;

use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk-generates synthetic products for realistic-scale performance testing
 * (Phase 4 of the test-quality initiative — production runs 1M+ products,
 * the demo seed is ~90). Deliberately bypasses Eloquent's factory/create()
 * per-row (each does its own manufacturer/condition lookup query plus an
 * individual INSERT — at 100k+ rows that's the dominant cost, not anything
 * about the data itself) in favor of pre-fetching lookup IDs once and bulk
 * `insert()`ing in chunks.
 */
class SeedScaleProducts extends Command
{
    protected $signature = 'oeparts:seed-scale-products
        {count=100000 : How many products to generate}
        {--chunk=2000 : Rows per bulk insert}';

    protected $description = 'Bulk-generate synthetic products for realistic-scale (100k+) performance testing';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $chunkSize = (int) $this->option('chunk');

        $manufacturerIds = Manufacturer::pluck('id')->all();
        $conditionIds = Condition::pluck('id')->all();
        $carModelIds = CarModel::pluck('id')->all();

        if ($manufacturerIds === [] || $conditionIds === []) {
            $this->error('No manufacturers or conditions exist — seed those first (php artisan db:seed).');

            return self::FAILURE;
        }

        $this->info("Generating {$count} products in chunks of {$chunkSize}...");
        $bar = $this->output->createProgressBar($count);

        $existingMax = (int) (DB::table('products')->max('id') ?? 0);
        $now = now();
        $inserted = 0;

        while ($inserted < $count) {
            $batchSize = min($chunkSize, $count - $inserted);
            $rows = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $seq = $existingMax + $inserted + $i + 1;
                // Deterministic-from-sequence, not fully random: guarantees
                // uniqueness across the whole run without an in-memory
                // "seen OEMs" set (which would grow unbounded at 100k+ rows)
                // or a unique-constraint retry loop per row.
                $oem = 'SCALE'.str_pad((string) $seq, 8, '0', STR_PAD_LEFT);

                $rows[] = [
                    'manufacturer_id' => $manufacturerIds[array_rand($manufacturerIds)],
                    'oem_number' => $oem,
                    'normalized_oem' => $oem,
                    'slug' => Str::slug($oem).'-'.$seq,
                    'name' => json_encode(['en' => 'Scale Test Part '.$seq]),
                    'description' => json_encode(['en' => 'Synthetic product generated for load testing.']),
                    'condition_id' => $conditionIds[array_rand($conditionIds)],
                    'price' => random_int(500, 50000) / 100,
                    'delivery_time' => random_int(1, 14).' days',
                    'moq' => 1,
                    'is_in_stock' => 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('products')->insert($rows);
            $inserted += $batchSize;
            $bar->advance($batchSize);
        }

        $bar->finish();
        $this->newLine();

        if ($carModelIds !== []) {
            $this->info('Attaching a random car model to each new product (pivot rows)...');
            $newIds = DB::table('products')->where('id', '>', $existingMax)->pluck('id');
            $bar = $this->output->createProgressBar($newIds->count());
            $pivotRows = [];

            foreach ($newIds as $productId) {
                $pivotRows[] = [
                    'product_id' => $productId,
                    'car_model_id' => $carModelIds[array_rand($carModelIds)],
                ];

                if (count($pivotRows) >= 2000) {
                    DB::table('product_car_models')->insert($pivotRows);
                    $bar->advance(count($pivotRows));
                    $pivotRows = [];
                }
            }

            if ($pivotRows !== []) {
                DB::table('product_car_models')->insert($pivotRows);
                $bar->advance(count($pivotRows));
            }

            $bar->finish();
            $this->newLine();
        }

        $this->info('Done. Total products now: '.Product::count());

        return self::SUCCESS;
    }
}
