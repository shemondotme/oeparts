<?php

namespace App\Jobs;

use App\Mail\OtpEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOtpEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $email,
        public readonly string $code,
        public readonly string $locale = 'en',
    ) {
        $this->onQueue('critical');
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(new OtpEmail($this->email, $this->code, $this->locale));
    }
}
