<?php

namespace App\Console\Commands;

use App\Models\Otp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired OTP codes from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning expired OTP codes...');

        $deletedCount = DB::table('otps')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deletedCount} expired OTP codes.");

        return Command::SUCCESS;
    }
}
