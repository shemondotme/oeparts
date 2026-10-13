<?php

namespace App\Services\Migration\Export\Transform;

/**
 * Turns a personal value into a stand-in that cannot be turned back and still behaves like the real thing:
 * the same input always gives the same output inside one export (a customer who is a user and also placed a
 * guest order is still one person, and a unique column stays unique), and different exports give different
 * outputs (the salt is random per export and is never written into the package).
 *
 * Stand-ins use addresses reserved for examples (`example.invalid`, 192.0.2.0/24, +000) so nothing here can
 * ever reach a real mailbox, phone or host.
 */
final class Pseudonymizer
{
    public function __construct(private readonly string $salt)
    {
        if (strlen($salt) < 32) {
            throw new \InvalidArgumentException('The pseudonymisation salt must be at least 32 characters.');
        }
    }

    public static function newSalt(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function email(string $value): string
    {
        return 'u'.$this->hex('email', strtolower(trim($value)), 16).'@example.invalid';
    }

    public function name(string $value): string
    {
        return 'Person '.$this->hex('name', $this->normal($value), 8);
    }

    public function lastName(string $value): string
    {
        return $this->hex('last_name', $this->normal($value), 8);
    }

    public function firstName(): string
    {
        return 'Person';
    }

    public function company(string $value): string
    {
        return 'Company '.$this->hex('company', $this->normal($value), 8);
    }

    public function street(string $value): string
    {
        return 'Street '.$this->hex('street', $this->normal($value), 8);
    }

    public function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return '+000'.sprintf('%08d', hexdec($this->hex('phone', $digits, 7)) % 100_000_000);
    }

    public function ip(string $value): string
    {
        return '192.0.2.'.(1 + hexdec($this->hex('ip', trim($value), 2)) % 254);
    }

    /** A vehicle identification number identifies one car, so one person: seventeen characters of stand-in. */
    public function vin(string $value): string
    {
        return strtoupper($this->hex('vin', strtoupper(trim($value)), 17));
    }

    /** Any other identifying code (a VAT number, an unsubscribe token). */
    public function code(string $value): string
    {
        return $this->hex('code', trim($value), 16);
    }

    private function normal(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function hex(string $kind, string $value, int $length): string
    {
        return substr(hash_hmac('sha256', $kind."\0".$value, $this->salt), 0, $length);
    }
}
