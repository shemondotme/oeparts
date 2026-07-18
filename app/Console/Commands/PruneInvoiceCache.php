<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneInvoiceCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oeparts:invoices:clean-cache {--days= : Days to keep cached invoice PDFs (defaults to orders.invoice_cache_retention_days setting)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old cached invoice PDFs from the private disk — invoices are regenerated on demand for every real download, so this cache is disposable';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) settings('orders.invoice_cache_retention_days', 30);

        if ($days <= 0) {
            $this->info('Invoice cache pruning disabled (orders.invoice_cache_retention_days is 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->timestamp;
        $deleted = 0;

        foreach (Storage::disk('local')->allFiles('invoices') as $path) {
            if (Storage::disk('local')->lastModified($path) < $cutoff) {
                Storage::disk('local')->delete($path);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} cached invoice PDF(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
