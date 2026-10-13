<?php

namespace App\Services\Instance;

/** What this installation is: the real shop, a testing copy, or a copy that is not trusted yet. */
enum InstanceMode: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Quarantine = 'quarantine';

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /** Staging and quarantine: a copy that must stay harmless. */
    public function isCopy(): bool
    {
        return $this !== self::Production;
    }

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Staging => 'Staging',
            self::Quarantine => 'Quarantine',
        };
    }

    /** @return array<int, string> every value that may be written to OE_MODE */
    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
