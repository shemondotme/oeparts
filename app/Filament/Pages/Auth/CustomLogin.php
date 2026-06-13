<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class CustomLogin extends BaseLogin
{
    public function getHeading(): string | Htmlable
    {
        return __('Sign in to OeParts');
    }

    public function getSubheading(): string | Htmlable
    {
        return __('Authorized administrative personnel only.');
    }
}
