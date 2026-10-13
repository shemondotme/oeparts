{{--
  Shown on a staging or quarantine copy only (see SafeMode): a fixed strip so nobody mistakes a test shop for the real one.
  Inline styles on purpose, so it needs no stylesheet build and cannot be hidden by a theme.
--}}
@php($instanceMode = app(\App\Services\Instance\SafeMode::class)->mode())
@if ($instanceMode->isCopy())
    <div data-instance-ribbon="{{ $instanceMode->value }}" role="status"
         style="position:fixed;left:0;right:0;bottom:0;z-index:2147483000;padding:6px 12px;text-align:center;font:600 12px/1.4 system-ui,sans-serif;letter-spacing:.04em;color:#fff;background:{{ $instanceMode === \App\Services\Instance\InstanceMode::Staging ? '#b45309' : '#b91c1c' }};pointer-events:none">
        {{ strtoupper($instanceMode->value) }} COPY &mdash; not the real shop. No real mail, no real payments, hidden from search engines.
    </div>
@endif
