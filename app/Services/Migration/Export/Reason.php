<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;

/**
 * Why a group was added to a selection nobody asked it to be in.
 */
final class Reason
{
    public const FOREIGN_KEY = 'foreign_key';

    public const REQUIRES = 'requires';

    private function __construct(
        public readonly string $type,
        public readonly GroupKey $group,
        public readonly GroupKey $becauseOf,
        public readonly ?FkEdge $edge,
    ) {}

    /** `$group` is added because a table of `$becauseOf` cannot exist without a row of one of its tables. */
    public static function foreignKey(GroupKey $group, GroupKey $becauseOf, FkEdge $edge): self
    {
        return new self(self::FOREIGN_KEY, $group, $becauseOf, $edge);
    }

    /** `$group` is added because `$becauseOf` is a file group whose files are pointed at by `$group`'s rows. */
    public static function requires(GroupKey $group, GroupKey $becauseOf): self
    {
        return new self(self::REQUIRES, $group, $becauseOf, null);
    }

    /** One technical sentence, for logs and the details line under a plain-language explanation. */
    public function technical(): string
    {
        return $this->type === self::FOREIGN_KEY
            ? "{$this->becauseOf->value} needs {$this->group->value}: {$this->edge?->describe()}"
            : "{$this->becauseOf->value} needs {$this->group->value}: its files belong to rows of that group";
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type, 'group' => $this->group->value, 'because_of' => $this->becauseOf->value,
            'edge' => $this->edge?->signature(), 'text' => $this->technical(),
        ];
    }
}
