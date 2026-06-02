<?php

namespace App\Jobs;

use App\Mail\PasswordReset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $email,
        public readonly string $resetUrl,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new PasswordReset($this->email, $this->resetUrl, $this->locale));
    }
}
