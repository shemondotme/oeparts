{{--
  Boots the admin device-alerts script (Web Push, chime, install prompt, badge).
  Only rendered for a signed-in admin. All server-provided values go through
  json_encode with the HEX flags so none can break out of the <script> block.
--}}
@php
    $admin = auth('admin')->user();
@endphp
@if ($admin)
@php
    $pref = \App\Models\AdminPushPreference::forAdmin($admin);

    try {
        $settingsUrl = \App\Filament\Pages\System\AlertPreferences::getUrl();
    } catch (\Throwable) {
        $settingsUrl = url('/admin');
    }

    $config = [
        'publicKey' => app(\App\Services\Push\VapidKeys::class)->publicKey(),
        'csrf' => csrf_token(),
        'soundEnabled' => (bool) $pref->sound_enabled,
        'soundUrl' => asset('admin-push/alert.wav'),
        'urls' => [
            'sw' => route('admin.push.sw'),
            'subscribe' => route('admin.push.subscribe'),
            'unsubscribe' => route('admin.push.unsubscribe'),
            'test' => route('admin.push.test'),
            'poll' => route('admin.push.poll'),
            'settings' => $settingsUrl,
        ],
        'i18n' => [
            'title' => __('push.title'),
            'enable' => __('push.enable'),
            'disable' => __('push.disable'),
            'sendTest' => __('push.send_test'),
            'soundOn' => __('push.sound_on'),
            'soundOff' => __('push.sound_off'),
            'installApp' => __('push.install_app'),
            'settings' => __('push.settings'),
            'iosInstall' => __('push.ios_install'),
            'denied' => __('push.denied'),
            'unsupported' => __('push.unsupported'),
            'statusOn' => __('push.status_on'),
            'statusMuted' => __('push.status_muted'),
            'enabledOk' => __('push.enabled_ok'),
            'enableFailed' => __('push.enable_failed'),
            'testFailed' => __('push.test_failed_generic'),
            'soundOffInProfile' => __('push.sound_off_in_profile'),
        ],
    ];
@endphp
<script nonce="{{ csp_nonce() }}">window.OEPUSH = {!! json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!};</script>
<script nonce="{{ csp_nonce() }}" src="{{ asset('admin-push/admin-push.js') }}?v={{ @filemtime(public_path('admin-push/admin-push.js')) }}" defer></script>
@endif
