<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class CustomLogin extends BaseLogin
{
    /**
     * Suppress Filament's default small framework logo — getHeading() below
     * replaces it with the full storefront-matching lockup (icon + wordmark
     * + subline), which already includes the brand mark at a much larger,
     * more deliberate size instead of the compact corner icon used
     * everywhere else in the panel (topbar/sidebar).
     */
    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string | Htmlable | null
    {
        // HtmlString wrapping a pre-rendered string, not the View object
        // itself: returning the View directly rendered as literal escaped
        // text (<p class="...">Oe...) instead of markup — confirmed live.
        // Illuminate\View\View does implement Htmlable, but something in
        // Filament's Livewire-hydrated render path between here and
        // header/simple.blade.php's {{ $heading }} was losing that identity
        // (likely a stringification during Livewire property hydration).
        // HtmlString is a minimal, always-reliable Htmlable — render the
        // view to a plain string myself and hand that off instead.
        return new HtmlString(view('filament.pages.auth.login-heading')->render());
    }

    public function getSubheading(): string | Htmlable
    {
        return __('Authorized administrative personnel only.');
    }
}
