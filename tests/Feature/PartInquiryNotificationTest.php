<?php

namespace Tests\Feature;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource;
use App\Jobs\SendPartInquiryStatusEmail;
use App\Mail\PartInquiryStatusUpdate;
use App\Models\PartInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PartInquiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeInquiry(array $attrs = []): PartInquiry
    {
        return PartInquiry::create(array_merge([
            'email'      => 'customer@example.com',
            'oem_number' => '04L115399F',
            'quantity'   => 1,
            'urgency'    => 'normal',
            'status'     => PartInquiryStatus::New,
            'ip_address' => '127.0.0.1',
        ], $attrs));
    }

    public function test_marking_sourced_queues_customer_email(): void
    {
        Queue::fake();
        $inquiry = $this->makeInquiry();

        PartInquiryResource::transitionAndNotify($inquiry, PartInquiryStatus::Sourced);

        $this->assertSame(PartInquiryStatus::Sourced, $inquiry->fresh()->status);
        Queue::assertPushed(SendPartInquiryStatusEmail::class, function ($job) use ($inquiry) {
            return $job->inquiry->is($inquiry) && $job->newStatus === PartInquiryStatus::Sourced;
        });
    }

    public function test_no_email_queued_when_inquiry_has_no_address(): void
    {
        Queue::fake();
        $inquiry = $this->makeInquiry(['email' => '']);

        PartInquiryResource::transitionAndNotify($inquiry, PartInquiryStatus::Unavailable);

        $this->assertSame(PartInquiryStatus::Unavailable, $inquiry->fresh()->status);
        Queue::assertNotPushed(SendPartInquiryStatusEmail::class);
    }

    public function test_mailable_renders_both_verdicts_in_en_and_de(): void
    {
        $inquiry = $this->makeInquiry();

        $sourced = (new PartInquiryStatusUpdate($inquiry, PartInquiryStatus::Sourced))->render();
        $this->assertStringContainsString('04L115399F', $sourced);
        $this->assertStringContainsString('We found your part', $sourced);

        $unavailableDe = (new PartInquiryStatusUpdate($inquiry, PartInquiryStatus::Unavailable, 'de'))->render();
        $this->assertStringContainsString('konnten dieses Teil nicht beschaffen', $unavailableDe);
    }

    public function test_job_sends_mail_to_inquiry_address(): void
    {
        Mail::fake();
        $inquiry = $this->makeInquiry();

        (new SendPartInquiryStatusEmail($inquiry, PartInquiryStatus::Sourced))->handle();

        Mail::assertSent(PartInquiryStatusUpdate::class, fn ($mail) => $mail->hasTo('customer@example.com'));
    }
}
