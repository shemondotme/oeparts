<?php

use App\Services\Spam\JsonAwareSpamResponder;

return [
    'enabled' => env('HONEYPOT_ENABLED', true),
    'name_field_name' => env('HONEYPOT_NAME', 'my_name'),
    'randomize_name_field_name' => env('HONEYPOT_RANDOMIZE', true),
    'valid_from_timestamp' => env('HONEYPOT_VALID_FROM_TIMESTAMP', true),
    'valid_from_field_name' => env('HONEYPOT_VALID_FROM', 'valid_from'),
    'amount_of_seconds' => (int) env('HONEYPOT_SECONDS', 1),
    'respond_to_spam_with' => JsonAwareSpamResponder::class,
    'honeypot_fields_required_for_all_forms' => false,
    'spam_protection' => \Spatie\Honeypot\SpamProtection::class,
    'with_csp' => env('HONEYPOT_WITH_CSP', false),
];
