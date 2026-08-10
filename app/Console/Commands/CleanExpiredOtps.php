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

        // OtpService::isRecentlyVerified() gives a verified OTP a 30-minute
        // grace window (e.g. ContactController trusts a verification that
        // just happened without asking for a fresh code) — deleting purely
        // on expires_at (~10 minutes) could remove a just-verified row
        // before that grace window closes, breaking the "recently verified"
        // check mid-window even though the code itself is now irrelevant.
        $deletedCount = DB::table('otps')
            ->where('expires_at', '<', now())
            ->where(function ($query) {
                $query->whereNull('verified_at')
                    ->orWhere('verified_at', '<', now()->subMinutes(30));
            })
            ->delete();

        $this->info("Deleted {$deletedCount} expired OTP codes.");

        return Command::SUCCESS;
    }
}
