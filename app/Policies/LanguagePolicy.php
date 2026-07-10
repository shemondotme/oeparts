<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
use App\Models\Language;

class LanguagePolicy extends BasePolicy
{
    protected string $model = 'languages';

    /**
     * 'en' is the code-wide fallback locale (every trans_field()/
     * translatableTabs assumes it) and the default language drives the
     * storefront — neither may be deleted. (Gate::before bypasses policies
     * for super_admins, so the resource ALSO hides the action.)
     */
    public function delete(Admin $admin, $record): bool
    {
        if ($record instanceof Language && self::isProtected($record)) {
            return false;
        }

        return parent::delete($admin, $record);
    }

    public static function isProtected(Language $language): bool
    {
        return $language->code === 'en' || $language->is_default;
    }
}
