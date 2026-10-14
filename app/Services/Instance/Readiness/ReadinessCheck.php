<?php

namespace App\Services\Instance\Readiness;

/**
 * One line of the launch-readiness report: what was looked at, what was found, and (when it is not fine) what to do about it.
 * Immutable. Messages name things and give counts; they never carry a setting value, a key or an address of a person.
 *
 * `pending` means "cannot be known yet" (a worker has not answered, the scheduler has not ticked since the install): it blocks a
 * release like a failure does, but it is not a failure and clears by itself. `overridable` marks the few failures a person may
 * waive with a typed reason (never a data check, never the worker's mode).
 */
final class ReadinessCheck
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    public const PENDING = 'pending';

    /** @param  array<string, mixed>  $meta */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly string $status,
        public readonly string $message = '',
        public readonly array $meta = [],
        public readonly string $fix = '',
        public readonly bool $overridable = false,
    ) {}

    public function isFail(): bool
    {
        return $this->status === self::FAIL;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key, 'label' => $this->label, 'group' => $this->group, 'status' => $this->status,
            'message' => $this->message, 'meta' => $this->meta, 'fix' => $this->fix, 'overridable' => $this->overridable,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['key'] ?? ''), (string) ($data['label'] ?? ''), (string) ($data['group'] ?? ''), (string) ($data['status'] ?? self::SKIP),
            (string) ($data['message'] ?? ''), (array) ($data['meta'] ?? []), (string) ($data['fix'] ?? ''), (bool) ($data['overridable'] ?? false),
        );
    }
}
