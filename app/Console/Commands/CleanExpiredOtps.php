<?php

namespace App\Console\Commands;

use App\Models\Otp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanExpiredOtps extends Command
{
    protected $signature = 'otp:clean';

    protected $description = 'Clean expired OTP codes from the database';

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
