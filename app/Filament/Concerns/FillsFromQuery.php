<?php

namespace App\Filament\Concerns;

/**
 * Lets a Create page prefill whitelisted form fields from a `?data[...]`
 * query string — e.g. the dashboard's "Create Product" / "Inquire" actions
 * pass the searched OEM number. Filament's CreateRecord never reads query
 * params on its own, so without this the links open a blank form.
 */
trait FillsFromQuery
{
    /** @return list<string> form field names that may be prefilled from the query string */
    protected function queryFillable(): array
    {
        return [];
    }

    protected function afterFill(): void
    {
        $params = request()->query('data', []);

        if (! is_array($params)) {
            return;
        }

        foreach ($this->queryFillable() as $field) {
            $value = $params[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $this->data[$field] = mb_substr(trim($value), 0, 255);
            }
        }
    }
}
