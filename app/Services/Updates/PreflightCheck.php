<?php

namespace App\Services\Updates;

/**
 * One line-item in the update pre-flight report (Module 21, Chunk 3.1).
 * Immutable; safe to serialize into the readiness UI.
 */
class PreflightCheck
{
    public const PASS = 'pass'; // ready
    public const WARN = 'warn'; // proceed with caution (operator ack)
    public const FAIL = 'fail'; // blocks the update

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $status,
        public readonly string $message,
        public readonly array $meta = [],
    ) {}

    public static function pass(string $key, string $label, string $message = '', array $meta = []): self
    {
        return new self($key, $label, self::PASS, $message, $meta);
    }

    public static function warn(string $key, string $label, string $message, array $meta = []): self
    {
        return new self($key, $label, self::WARN, $message, $meta);
    }

    public static function fail(string $key, string $label, string $message, array $meta = []): self
    {
        return new self($key, $label, self::FAIL, $message, $meta);
    }

    public function isFail(): bool
    {
        return $this->status === self::FAIL;
    }

    public function isWarn(): bool
    {
        return $this->status === self::WARN;
    }

    public function toArray(): array
    {
        return [
            'key'     => $this->key,
            'label'   => $this->label,
            'status'  => $this->status,
            'message' => $this->message,
            'meta'    => $this->meta,
        ];
    }
}
