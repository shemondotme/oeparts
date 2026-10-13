<?php

namespace App\Services\Migration\Export;

use App\Services\Migration\Groups\GroupKey;

/**
 * A reference the database cannot see: no foreign key stands behind it, so leaving the target out
 * does not fail an import, it silently breaks something (a logo that points at a media row that was not
 * copied, a refund whose pictures are not on the new server).
 *
 * `$from` is the group whose data holds the reference, `$target` the group that holds the thing it
 * points at. When the first is exported without the second, the person is warned, with a count.
 */
final class LogicalRef
{
    public const SETTING = 'setting';

    public const COLUMN = 'column';

    public const JSON_LIST = 'json_list';

    public const JSON_PATH = 'json_path';

    /**
     * @param  string  $kind  one of the constants above
     * @param  string  $subject  setting `group.key`, or table name
     * @param  string|null  $column  the column (not for a setting)
     * @param  string|null  $jsonPath  the key inside the JSON column (JSON_PATH only)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $subject,
        public readonly ?string $column,
        public readonly ?string $jsonPath,
        public readonly GroupKey $from,
        public readonly GroupKey $target,
    ) {}

    public function messageKey(): string
    {
        return 'migration.logical.'.$this->id;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind, 'subject' => $this->subject, 'column' => $this->column,
            'json_path' => $this->jsonPath, 'from' => $this->from->value, 'target' => $this->target->value,
        ];
    }
}
