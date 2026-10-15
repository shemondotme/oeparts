<?php

namespace App\Filament\Widgets\Migration;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * The button on the last step of a migration wizard, made of the stock Filament button (the way the Filament documentation
 * shows for `Wizard::submitAction()`). The wizard itself handles "Enter" (it moves on to the next step, and on the last step it
 * calls the method named in `alpineSubmitHandler()`), so this button only has to call the same method and look busy while it works.
 */
final class WizardSubmit
{
    /** @param  string  $method  the name of a public method of the page (a code constant, never user input) */
    public static function button(string $label, string $method, string $color = 'primary', ?string $icon = null): HtmlString
    {
        return new HtmlString(Blade::render(
            '<x-filament::button type="button" :color="$color" :icon="$icon" x-on:click="$wire.'.$method.'()" wire:loading.attr="disabled" wire:target="'.$method.'">{{ $label }}</x-filament::button>',
            ['label' => $label, 'color' => $color, 'icon' => $icon],
        ));
    }
}
