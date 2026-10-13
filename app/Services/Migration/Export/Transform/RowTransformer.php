<?php

namespace App\Services\Migration\Export\Transform;

/**
 * One rule applied to rows on their way into a package. A transformer names the tables it cares about, so
 * a table with no rule costs nothing: the pipeline is compiled per table and a table with no transformer
 * is copied as it is.
 */
interface RowTransformer
{
    /** @return array<int, string> the tables this rule applies to */
    public function tables(): array;

    /**
     * @param  array<string, mixed>  $row  column => value, as read from the database
     * @return array<string, mixed>
     */
    public function transform(string $table, array $row): array;
}
