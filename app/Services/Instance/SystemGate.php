<?php

namespace App\Services\Instance;

use App\Services\Instance\Contracts\BlockSource;

/**
 * The single answer to "may this run right now?" for everything that can be held back while the
 * installation is held still. With no source registered, or none blocking, it answers yes to everything
 * and the installation behaves exactly as it did before this class existed.
 */
final class SystemGate
{
    public const ALWAYS = 'always';

    public const INTERNAL = 'internal';

    public const CUSTOMER = 'customer';

    public const UNKNOWN = 'unknown';

    /** @var array<int, BlockSource> */
    private array $sources = [];

    public function register(BlockSource $source): void
    {
        $this->sources[] = $source;
    }

    /** @return array<int, BlockSource> */
    public function sources(): array
    {
        return $this->sources;
    }

    /** The highest level among the sources that are blocking right now. */
    public function level(): BlockLevel
    {
        $highest = BlockLevel::None;

        foreach ($this->sources as $source) {
            $level = $source->level();

            if ($level->value > $highest->value) {
                $highest = $level;
            }
        }

        return $highest;
    }

    /** @return array<int, string> why the installation is held, one phrase per blocking source */
    public function reasons(): array
    {
        $reasons = [];

        foreach ($this->sources as $source) {
            if ($source->level() !== BlockLevel::None) {
                $reasons[] = $source->reason();
            }
        }

        return $reasons;
    }

    public function isBlocking(): bool
    {
        return $this->level() !== BlockLevel::None;
    }

    /** May a scheduled command of this class run now? */
    public function allows(string $class): bool
    {
        return match ($this->level()) {
            BlockLevel::None => true,
            BlockLevel::CustomerOnly => in_array($class, [self::ALWAYS, self::INTERNAL], true),
            BlockLevel::Everything => $class === self::ALWAYS,
        };
    }

    /** The class of a scheduled command, by its bare name (no arguments). Unlisted commands are UNKNOWN. */
    public function classOf(string $command): string
    {
        $command = trim((string) strtok($command, ' '));

        foreach ([self::ALWAYS, self::INTERNAL, self::CUSTOMER] as $class) {
            if (in_array($command, (array) config("instance.schedule.{$class}", []), true)) {
                return $class;
            }
        }

        return self::UNKNOWN;
    }

    public function allowsCommand(string $command): bool
    {
        return $this->allows($this->classOf($command));
    }
}
