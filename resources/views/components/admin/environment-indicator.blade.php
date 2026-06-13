@props([])

@php
    $env = app()->environment();
    $isProduction = $env === 'production';
@endphp

<div
    class="fi-env-indicator flex items-center gap-1.5 px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest font-mono transition-all duration-200"
    title="{{ ucfirst($env) }} environment"
    @style([
        'background: var(--color-accent-50); color: var(--color-accent-700); border: 1px solid color-mix(in srgb, var(--color-accent-200), transparent 40%);' => $env === 'local' || $env === 'development',
        'background: var(--color-warning-50); color: var(--color-warning-700); border: 1px solid color-mix(in srgb, var(--color-warning-200), transparent 40%);' => $env === 'staging',
        'background: var(--color-success-50); color: var(--color-success-700); border: 1px solid color-mix(in srgb, var(--color-success-200), transparent 40%);' => $isProduction,
    ])
>
    <span class="w-1.5 h-1.5 rounded-full"
          @style([
              'background: var(--color-accent-500);' => $env === 'local' || $env === 'development',
              'background: var(--color-warning-500);' => $env === 'staging',
              'background: var(--color-success-500);' => $isProduction,
          ])
    ></span>
    <span>{{ strtoupper($env) }}</span>
</div>
