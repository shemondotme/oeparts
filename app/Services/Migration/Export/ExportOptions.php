<?php

namespace App\Services\Migration\Export;

/**
 * What a person decides besides "which groups": how consistent the copy must be, whether secrets
 * travel, and whether personal data is scrambled. A null means "whatever the preset says".
 */
final class ExportOptions
{
    public const LIVE = 'live';

    public const FREEZE = 'freeze';

    public function __construct(
        /** `live`: the site keeps running, the copy is eventually consistent. `freeze`: the shop shows the maintenance page while exporting. */
        public readonly string $consistency = self::LIVE,
        /** Passwords and API keys travel inside the package (behind a separate unlock). Never combined with scrambling. */
        public readonly bool $includeSecrets = false,
        public readonly ?bool $anonymise = null,
        public readonly ?bool $resetSequences = null,
        /** Freeze only: keep the site frozen after the package is sealed, until the operator lifts it (or the hold timer ends). */
        public readonly bool $hold = true,
        public readonly ?int $holdReleaseMinutes = null,
    ) {
        if (! in_array($consistency, [self::LIVE, self::FREEZE], true)) {
            throw new \InvalidArgumentException("Consistency must be 'live' or 'freeze', not '{$consistency}'.");
        }

        if ($holdReleaseMinutes !== null && $holdReleaseMinutes < 0) {
            throw new \InvalidArgumentException('The hold time cannot be negative (0 means never).');
        }
    }

    public function freeze(): bool
    {
        return $this->consistency === self::FREEZE;
    }
}
