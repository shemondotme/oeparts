<?php

namespace App\Filament\Concerns;

/**
 * Hides the "Create & create another" button on Filament create pages.
 */
trait DisablesCreateAnother
{
    public function canCreateAnother(): bool
    {
        return false;
    }
}
