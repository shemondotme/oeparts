@php
    /** @var string $title  @var string $description  @var string $url  @var array $titleStatus  @var array $descStatus */
    $parts = parse_url($url) ?: [];
    $host = $parts['host'] ?? '';
    $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
    $crumbs = $host.($segments ? ' › '.implode(' › ', $segments) : '');
    $colors = ['good' => '#15803d', 'warn' => '#b45309', 'bad' => '#b91c1c', 'muted' => '#6b7280'];
@endphp

<style>
    .oc-serp { border: 1px solid rgba(128,128,128,.35); border-radius: .5rem; padding: .9rem 1rem; background: #fff; color: #202124; font-family: Arial, Roboto, sans-serif; }
    .dark .oc-serp { background: #202124; color: #bdc1c6; }
    .oc-serp-wrap { max-width: 600px; transition: max-width .15s; }
    .oc-serp-wrap.is-mobile { max-width: 360px; }
    .oc-serp-url { font-size: 12px; line-height: 1.4; color: #4d5156; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dark .oc-serp-url { color: #9aa0a6; }
    .oc-serp-title { font-size: 20px; line-height: 1.3; color: #1a0dab; margin: .15rem 0 .2rem; }
    .dark .oc-serp-title { color: #8ab4f8; }
    .oc-serp-desc { font-size: 14px; line-height: 1.58; color: #4d5156; }
    .dark .oc-serp-desc { color: #bdc1c6; }
    .oc-serp-meters { display: flex; gap: 1.25rem; flex-wrap: wrap; margin-top: .6rem; font-size: 12px; }
    .oc-serp-bar { height: 4px; width: 90px; background: rgba(128,128,128,.25); border-radius: 2px; overflow: hidden; margin-top: 3px; }
    .oc-serp-bar > span { display: block; height: 100%; }
    .oc-serp-toggle { font-size: 12px; margin-bottom: .4rem; display: inline-flex; gap: .35rem; }
    .oc-serp-toggle button { padding: .1rem .55rem; border: 1px solid rgba(128,128,128,.4); border-radius: 999px; background: transparent; cursor: pointer; color: inherit; }
    .oc-serp-toggle button[aria-pressed="true"] { background: rgba(245,158,11,.2); border-color: #d97706; }
</style>

<div x-data="{ mobile: false }">
    <div class="oc-serp-toggle" role="group" aria-label="Preview device">
        <button type="button" x-on:click="mobile = false" x-bind:aria-pressed="(!mobile).toString()">Desktop</button>
        <button type="button" x-on:click="mobile = true" x-bind:aria-pressed="mobile.toString()">Mobile</button>
    </div>

    <div class="oc-serp-wrap" x-bind:class="{ 'is-mobile': mobile }">
        <div class="oc-serp">
            <div class="oc-serp-url">{{ $crumbs }}</div>
            <div class="oc-serp-title">{{ \Illuminate\Support\Str::limit($title, 60, '…') ?: '—' }}</div>
            <div class="oc-serp-desc">{{ \Illuminate\Support\Str::limit($description, 160, '…') ?: '—' }}</div>
        </div>
    </div>

    <div class="oc-serp-meters">
        <div>
            <span style="color: {{ $colors[$titleStatus['color']] }}; font-weight: 600;">Title: {{ $titleStatus['length'] }} / 60 — {{ $titleStatus['label'] }}</span>
            <div class="oc-serp-bar"><span style="width: {{ min(100, (int) round(100 * $titleStatus['length'] / 60)) }}%; background: {{ $colors[$titleStatus['color']] }};"></span></div>
        </div>
        <div>
            <span style="color: {{ $colors[$descStatus['color']] }}; font-weight: 600;">Description: {{ $descStatus['length'] }} / 160 — {{ $descStatus['label'] }}</span>
            <div class="oc-serp-bar"><span style="width: {{ min(100, (int) round(100 * $descStatus['length'] / 160)) }}%; background: {{ $colors[$descStatus['color']] }};"></span></div>
        </div>
    </div>
</div>
