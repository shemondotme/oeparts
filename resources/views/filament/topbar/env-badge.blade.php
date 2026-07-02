{{--
  Topbar environment badge (native Filament badge). Subtle gray on production,
  amber on any non-production environment so admins can tell at a glance when
  they are NOT on the live site.
--}}
@php
    $env = app()->environment();
    $color = $env === 'production' ? 'gray' : 'warning';
@endphp

<span class="hidden md:inline-flex" style="margin-inline-start: 1.75rem;" title="You are on the {{ strtoupper($env) }} environment">
    <x-filament::badge :color="$color" class="uppercase">
        {{ $env }}
    </x-filament::badge>
</span>
