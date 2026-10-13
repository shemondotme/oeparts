<?php

namespace App\Support;

use App\Services\Instance\InstanceMode;
use App\Services\Instance\SafeMode;
use Illuminate\Support\Facades\Blade;

/**
 * The notice at the top of every admin page of a staging or quarantine copy. Built only from stock Filament
 * parts (a section and a badge) rendered from an inline string, so there is no template of its own to maintain.
 * Empty on the real shop.
 */
class InstanceBanner
{
    public function __construct(private readonly SafeMode $safeMode) {}

    public function render(): string
    {
        if ($this->safeMode->isProduction()) {
            return '';
        }

        $mode = $this->safeMode->mode();
        $masked = count($this->safeMode->activeOverrides());

        $message = match (true) {
            $this->safeMode->hasInvalidValue() => 'OE_MODE in .env is "'.trim((string) config('instance.mode')).'", which is not production, staging or quarantine. '
                .'This site is being run as quarantine until that is fixed.',
            $mode === InstanceMode::Staging => 'This is a testing copy. No real mail or payments leave it, and search engines are told to stay away.',
            default => 'This copy was just moved in and is not trusted yet. It sends nothing and runs only the work that keeps it healthy, until the launch checks pass.',
        };

        return Blade::render(
            '<div style="margin-bottom:1rem"><x-filament::section :compact="true">'
            .'<div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem">'
            .'<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>'
            .'<span>{{ $message }}</span>'
            .'<span style="opacity:.7">Safe mode is masking {{ $masked }} settings; the stored values are untouched.</span>'
            .'</div></x-filament::section></div>',
            [
                'color' => $mode === InstanceMode::Staging && ! $this->safeMode->hasInvalidValue() ? 'warning' : 'danger',
                'label' => strtoupper($mode->value),
                'message' => $message,
                'masked' => $masked,
            ],
        );
    }
}
