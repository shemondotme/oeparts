{{--
  Topbar "device alerts" control: shows whether this browser/device receives
  push alerts and opens a small menu (enable/disable, mute sound, test, install,
  settings). Behaviour lives in public/admin-push/admin-push.js.
--}}
@if (auth('admin')->check())
<div class="oepush" data-oepush data-state="loading">
    <button type="button" class="oepush-btn" data-oepush-toggle aria-haspopup="true" aria-label="{{ __('push.title') }}" title="{{ __('push.title') }}">
        <svg class="oepush-icon oepush-icon-bell" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
        </svg>
        <svg class="oepush-icon oepush-icon-slash" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" />
        </svg>
        <span class="oepush-dot" aria-hidden="true"></span>
    </button>
    <div class="oepush-menu" data-oepush-menu hidden role="menu"></div>
</div>
<style>
    .oepush { position: relative; display: inline-flex; align-items: center; margin-inline-end: .25rem; }
    .oepush-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: .5rem; color: rgb(107 114 128); background: transparent; border: 0; cursor: pointer; }
    .oepush-btn:hover { background: rgba(107, 114, 128, .12); }
    .dark .oepush-btn { color: rgb(156 163 175); }
    .oepush-icon { width: 1.35rem; height: 1.35rem; }
    .oepush-icon-slash { display: none; }
        .oepush-dot { position: absolute; top: .4rem; inset-inline-end: .45rem; width: .5rem; height: .5rem; border-radius: 9999px; background: transparent; box-shadow: 0 0 0 2px transparent; }
    .oepush[data-state="on"] .oepush-dot { background: #10b981; }
    .oepush[data-state="off"] .oepush-dot,
    .oepush[data-state="ios-install"] .oepush-dot { background: #f59e0b; }
    .oepush[data-state="denied"] .oepush-dot { background: #ef4444; }
    .oepush-menu { position: absolute; inset-inline-end: 0; top: calc(100% + .4rem); z-index: 60; min-width: 15rem; max-width: 20rem; padding: .35rem; border-radius: .6rem; background: #fff; border: 1px solid rgba(107, 114, 128, .25); box-shadow: 0 10px 25px rgba(0, 0, 0, .15); }
    .dark .oepush-menu { background: #18181b; border-color: rgba(255, 255, 255, .12); }
    .oepush-menu[hidden] { display: none; }
    .oepush-item { display: block; width: 100%; text-align: start; padding: .5rem .65rem; border-radius: .4rem; font-size: .875rem; line-height: 1.25rem; color: rgb(55 65 81); background: transparent; border: 0; cursor: pointer; }
    .dark .oepush-item { color: rgb(229 231 235); }
    .oepush-item:hover { background: rgba(107, 114, 128, .12); }
    .oepush-item.is-primary { font-weight: 600; color: #b45309; }
    .dark .oepush-item.is-primary { color: #fbbf24; }
    .oepush-item.is-info { cursor: default; color: rgb(107 114 128); white-space: normal; }
</style>
@endif
