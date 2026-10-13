<?php

namespace App\Services\Migration\Groups;

/**
 * A folder of user files that belongs to a group: `public:media` is the `media` folder on the
 * `public` filesystem disk. Only these folders are ever looked at when files are exported.
 */
final class FileRoot
{
    private function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {}

    public static function parse(string $spec): self
    {
        if (preg_match('/^(public|local):([a-z0-9][a-z0-9_-]*)$/', $spec, $m) !== 1) {
            throw new \InvalidArgumentException("'{$spec}' is not a file root like 'public:media'.");
        }

        return new self($m[1], $m[2]);
    }

    public function __toString(): string
    {
        return $this->disk.':'.$this->path;
    }
}
