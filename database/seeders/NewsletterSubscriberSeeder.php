<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsletterSubscriberSeeder extends Seeder
{
    public function run(): void
    {
        $subscribers = [
            ['email' => 'demo@example.com', 'lang' => 'en', 'ip_address' => '127.0.0.1'],
            ['email' => 'test@example.com', 'lang' => 'de', 'ip_address' => '127.0.0.1'],
            ['email' => 'newsletter@example.com', 'lang' => 'en', 'ip_address' => '127.0.0.1'],
        ];

        foreach ($subscribers as $subscriber) {
            NewsletterSubscriber::firstOrCreate(
                ['email' => $subscriber['email']],
                [
                    'lang'              => $subscriber['lang'],
                    'is_active'         => true,
                    'subscribed_at'     => now(),
                    'ip_address'        => $subscriber['ip_address'],
                    'unsubscribe_token' => Str::random(64),
                ]
            );
        }

        $this->command->info('Created ' . count($subscribers) . ' newsletter subscribers.');
    }
}
