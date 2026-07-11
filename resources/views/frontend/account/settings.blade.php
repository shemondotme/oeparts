@extends('layouts.app')

@section('title', ui_copy('account_settings_title', 'account.settings_title') . ' — ' . settings('general.site_name', 'OeParts'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="settings"
    eyebrow="{{ ui_copy('account_settings_eyebrow', 'account.settings_eyebrow') }}"
    title="{{ ui_copy('account_account_settings_heading', 'account.account_settings_heading') }}"
    :subtitle="ui_copy('account_settings_subtitle', 'account.settings_subtitle')"
    docId="DOC · SETTINGS · REV. {{ now()->format('Y.m.d') }}"
    :breadcrumb="[['label' => ui_copy('account_nav_settings', 'account.nav_settings')]]"
>
    <div x-data="{ tab: '{{ session('settings_tab', 'profile') }}' }" class="space-y-6">

        {{-- Tab nav --}}
        <nav class="border border-ink bg-paper flex overflow-x-auto"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            @php
                $tabs = [
                    ['key' => 'profile',       'num' => '01', 'label' => ui_copy('account_tab_profile', 'account.tab_profile'),             'icon' => 'heroicon-o-user'],
                    ['key' => 'security',      'num' => '02', 'label' => ui_copy('account_tab_security', 'account.tab_security'),           'icon' => 'heroicon-o-lock-closed'],
                    ['key' => 'notifications', 'num' => '03', 'label' => ui_copy('account_tab_notifications', 'account.tab_notifications'), 'icon' => 'heroicon-o-bell'],
                    ['key' => 'language',      'num' => '04', 'label' => ui_copy('account_tab_language', 'account.tab_language'),           'icon' => 'heroicon-o-globe-alt'],
                    ['key' => 'danger',        'num' => '05', 'label' => ui_copy('account_tab_danger', 'account.tab_danger'),               'icon' => 'heroicon-o-exclamation-triangle'],
                ];
            @endphp
            @foreach($tabs as $i => $t)
                <button type="button"
                        @click="tab='{{ $t['key'] }}'"
                        :class="tab==='{{ $t['key'] }}' ? 'bg-ink text-ivory' : 'bg-paper text-ink hover:bg-ivory-alt'"
                        class="flex-1 min-w-[150px] flex items-center justify-center gap-2 px-4 py-4 transition-colors
                               {{ $i > 0 ? 'border-l border-ink' : '' }}
                               {{ $t['key'] === 'danger' ? 'text-red-700' : '' }}">
                    <span class="font-mono text-[10px] tabular-nums tracking-[0.18em] uppercase"
                          :class="tab==='{{ $t['key'] }}' ? 'text-amber' : 'text-ink-muted'">
                        {{ $t['num'] }}
                    </span>
                    <x-dynamic-component :component="$t['icon']" class="w-4 h-4" />
                    <span class="font-mono text-[11px] font-bold tracking-[0.22em] uppercase">{{ $t['label'] }}</span>
                </button>
            @endforeach
        </nav>

        {{-- ── Profile tab ──────────────────────────────────────────── --}}
        <section x-show="tab === 'profile'" x-cloak
                 class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-user class="w-3.5 h-3.5" />
                    {{ ui_copy('account_profile_info_eyebrow', 'account.profile_info_eyebrow') }}
                </span>
                <span class="bp-spec-mono">{{ ui_copy('account_required_note', 'account.required_note') }}</span>
            </header>

            <form method="POST"
                  action="{{ route('frontend.account.settings.update', ['lang' => $lang]) }}"
                  class="p-6 sm:p-8 space-y-5" novalidate>
                @csrf
                @method('PUT')

                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <label for="first_name" class="bp-spec block mb-2 text-ink">
                            {{ ui_copy('account_first_name', 'account.first_name') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="first_name" name="first_name" required
                               value="{{ old('first_name', $user->first_name ?? '') }}"
                               class="bp-input w-full">
                    </div>
                    <div>
                        <label for="last_name" class="bp-spec block mb-2 text-ink">
                            {{ ui_copy('account_last_name', 'account.last_name') }} <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="last_name" name="last_name" required
                               value="{{ old('last_name', $user->last_name ?? '') }}"
                               class="bp-input w-full">
                    </div>
                </div>

                <div>
                    <label for="email" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_email_address', 'account.email_address') }} <span class="text-red-600">*</span>
                    </label>
                    <input type="email" id="email" name="email" required
                           value="{{ old('email', $user->email ?? '') }}"
                           class="bp-input w-full font-mono">
                </div>

                <div>
                    <label for="phone" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_phone', 'account.phone') }}
                        <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">{{ ui_copy('account_optional', 'account.optional') }}</span>
                    </label>
                    <input type="tel" id="phone" name="phone"
                           value="{{ old('phone', $user->phone ?? '') }}"
                           class="bp-input w-full">
                </div>

                <div class="flex justify-end pt-4 border-t border-dotted border-rule-strong">
                    <button type="submit" class="bp-btn-primary">
                        <x-heroicon-s-check class="w-5 h-5" />
                        <span>{{ ui_copy('account_save_changes', 'account.save_changes') }}</span>
                        <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Security tab ─────────────────────────────────────────── --}}
        <section x-show="tab === 'security'" x-cloak
                 class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />
                    {{ ui_copy('account_password_credentials_eyebrow', 'account.password_credentials_eyebrow') }}
                </span>
                <span class="bp-spec-mono">{{ ui_copy('account_tls_encrypted', 'account.tls_encrypted') }}</span>
            </header>

            <form method="POST"
                  action="{{ route('frontend.account.password.update', ['lang' => $lang]) }}"
                  class="p-6 sm:p-8 space-y-5" novalidate x-data="{ curr: false, np: false, npc: false }">
                @csrf

                <div>
                    <label for="current_password" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_current_password', 'account.current_password') }} <span class="text-red-600">*</span>
                    </label>
                    <div class="relative">
                        <input :type="curr ? 'text' : 'password'" id="current_password" name="current_password" required
                               class="bp-input w-full pr-11 font-mono">
                        <button type="button" @click="curr = !curr" tabindex="-1"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 border border-rule-strong bg-paper
                                       flex items-center justify-center text-ink-muted hover:text-ink hover:border-ink transition-colors">
                            <x-heroicon-s-eye x-show="!curr" class="w-3.5 h-3.5" />
                            <x-heroicon-s-eye-slash x-show="curr" x-cloak class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <div>
                    <label for="new_password" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_new_password', 'account.new_password') }} <span class="text-red-600">*</span>
                    </label>
                    <div class="relative">
                        <input :type="np ? 'text' : 'password'" id="new_password" name="new_password" required minlength="8"
                               class="bp-input w-full pr-11 font-mono">
                        <button type="button" @click="np = !np" tabindex="-1"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 border border-rule-strong bg-paper
                                       flex items-center justify-center text-ink-muted hover:text-ink hover:border-ink transition-colors">
                            <x-heroicon-s-eye x-show="!np" class="w-3.5 h-3.5" />
                            <x-heroicon-s-eye-slash x-show="np" x-cloak class="w-3.5 h-3.5" />
                        </button>
                    </div>
                    <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                        {{ ui_copy('account_new_password_note', 'account.new_password_note') }}
                    </p>
                </div>

                <div>
                    <label for="new_password_confirmation" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_confirm_new_password', 'account.confirm_new_password') }} <span class="text-red-600">*</span>
                    </label>
                    <div class="relative">
                        <input :type="npc ? 'text' : 'password'" id="new_password_confirmation"
                               name="new_password_confirmation" required minlength="8"
                               class="bp-input w-full pr-11 font-mono">
                        <button type="button" @click="npc = !npc" tabindex="-1"
                                class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 border border-rule-strong bg-paper
                                       flex items-center justify-center text-ink-muted hover:text-ink hover:border-ink transition-colors">
                            <x-heroicon-s-eye x-show="!npc" class="w-3.5 h-3.5" />
                            <x-heroicon-s-eye-slash x-show="npc" x-cloak class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-dotted border-rule-strong">
                    <button type="submit" class="bp-btn-primary">
                        <x-heroicon-s-lock-closed class="w-5 h-5" />
                        <span>{{ ui_copy('account_update_password', 'account.update_password') }}</span>
                        <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Notifications tab ────────────────────────────────────── --}}
        <section x-show="tab === 'notifications'" x-cloak
                 class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-bell class="w-3.5 h-3.5" />
                    {{ ui_copy('account_notification_prefs_eyebrow', 'account.notification_prefs_eyebrow') }}
                </span>
            </header>

            <form method="POST"
                  action="{{ route('frontend.account.notifications.update', ['lang' => $lang]) }}"
                  class="p-6 sm:p-8 space-y-4">
                @csrf

                @php
                    $notifOptions = [
                        ['key' => 'order_updates',      'icon' => 'heroicon-o-shopping-bag', 'label' => ui_copy('account_order_updates', 'account.order_updates'),           'desc' => ui_copy('account_order_updates_desc', 'account.order_updates_desc'), 'default' => true],
                        ['key' => 'email_notifications','icon' => 'heroicon-o-envelope',     'label' => ui_copy('account_email_notifications', 'account.email_notifications'), 'desc' => ui_copy('account_email_notifications_desc', 'account.email_notifications_desc'), 'default' => true],
                        ['key' => 'promotional_emails', 'icon' => 'heroicon-o-megaphone',    'label' => ui_copy('account_promotional_emails', 'account.promotional_emails'),   'desc' => ui_copy('account_promotional_emails_desc', 'account.promotional_emails_desc'), 'default' => false],
                    ];
                @endphp

                @foreach($notifOptions as $i => $n)
                    @php
                        $attr = 'prefers_' . $n['key'];
                        $checked = old('notifications.' . $n['key'], $user->{$attr} ?? $n['default']);
                    @endphp
                    <label class="flex items-center justify-between gap-4 p-4 border border-rule-strong bg-ivory-alt
                                  hover:border-ink transition-colors cursor-pointer">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <span class="font-mono text-[10px] tabular-nums tracking-[0.22em] uppercase text-ink-muted w-6 pt-0.5">
                                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div class="w-10 h-10 border border-ink bg-paper flex items-center justify-center shrink-0">
                                <x-dynamic-component :component="$n['icon']" class="w-4 h-4 text-ink" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-display text-sm font-bold text-ink tracking-[-0.01em]">
                                    {{ $n['label'] }}
                                </p>
                                <p class="mt-0.5 text-xs text-ink-muted">{{ $n['desc'] }}</p>
                            </div>
                        </div>
                        {{-- Switch --}}
                        <span class="relative inline-flex shrink-0">
                            <input type="checkbox" name="notifications[{{ $n['key'] }}]" value="1"
                                   {{ $checked ? 'checked' : '' }}
                                   class="sr-only peer">
                            <span class="w-11 h-6 bg-ink-tint border border-ink peer-checked:bg-amber
                                         relative transition-colors
                                         after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                         after:w-4 after:h-4 after:bg-paper after:border after:border-ink after:transition-transform
                                         peer-checked:after:translate-x-5"></span>
                        </span>
                    </label>
                @endforeach

                <div class="flex justify-end pt-4 border-t border-dotted border-rule-strong">
                    <button type="submit" class="bp-btn-primary">
                        <x-heroicon-s-check class="w-5 h-5" />
                        <span>{{ ui_copy('account_save_preferences', 'account.save_preferences') }}</span>
                        <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Language tab ─────────────────────────────────────────── --}}
        <section x-show="tab === 'language'" x-cloak
                 class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
            <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                <span class="bp-spec text-amber-ink flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="w-3.5 h-3.5" />
                    {{ ui_copy('account_language_region_eyebrow', 'account.language_region_eyebrow') }}
                </span>
            </header>

            <form method="POST"
                  action="{{ route('frontend.account.language.update', ['lang' => $lang]) }}"
                  class="p-6 sm:p-8 space-y-5">
                @csrf

                <div>
                    <label for="language" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_preferred_language', 'account.preferred_language') }}
                    </label>
                    @php $prefLang = old('language', $user->preferred_locale ?? $lang); @endphp
                    <select id="language" name="language" class="bp-input w-full">
                        <option value="en" {{ $prefLang === 'en' ? 'selected' : '' }}>English</option>
                        <option value="de" {{ $prefLang === 'de' ? 'selected' : '' }}>Deutsch (German)</option>
                        <option value="lt" {{ $prefLang === 'lt' ? 'selected' : '' }}>Lietuvių (Lithuanian)</option>
                        <option value="fr" {{ $prefLang === 'fr' ? 'selected' : '' }}>Français (French)</option>
                        <option value="es" {{ $prefLang === 'es' ? 'selected' : '' }}>Español (Spanish)</option>
                    </select>
                </div>

                <div>
                    <label for="timezone" class="bp-spec block mb-2 text-ink">
                        {{ ui_copy('account_timezone', 'account.timezone') }}
                    </label>
                    @php $tz = old('timezone', $user->timezone ?? 'UTC'); @endphp
                    <select id="timezone" name="timezone" class="bp-input w-full">
                        <option value="UTC"                 {{ $tz === 'UTC' ? 'selected' : '' }}>UTC</option>
                        <option value="Europe/London"       {{ $tz === 'Europe/London' ? 'selected' : '' }}>Europe / London (GMT)</option>
                        <option value="Europe/Berlin"       {{ $tz === 'Europe/Berlin' ? 'selected' : '' }}>Europe / Berlin (CET)</option>
                        <option value="Europe/Paris"        {{ $tz === 'Europe/Paris' ? 'selected' : '' }}>Europe / Paris (CET)</option>
                        <option value="Europe/Madrid"       {{ $tz === 'Europe/Madrid' ? 'selected' : '' }}>Europe / Madrid (CET)</option>
                        <option value="Europe/Vilnius"      {{ $tz === 'Europe/Vilnius' ? 'selected' : '' }}>Europe / Vilnius (EET)</option>
                        <option value="America/New_York"    {{ $tz === 'America/New_York' ? 'selected' : '' }}>America / New York (ET)</option>
                        <option value="America/Los_Angeles" {{ $tz === 'America/Los_Angeles' ? 'selected' : '' }}>America / Los Angeles (PT)</option>
                        <option value="Asia/Tokyo"          {{ $tz === 'Asia/Tokyo' ? 'selected' : '' }}>Asia / Tokyo (JST)</option>
                    </select>
                </div>

                <div class="flex justify-end pt-4 border-t border-dotted border-rule-strong">
                    <button type="submit" class="bp-btn-primary">
                        <x-heroicon-s-check class="w-5 h-5" />
                        <span>{{ ui_copy('account_save_preferences', 'account.save_preferences') }}</span>
                        <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Danger zone tab ──────────────────────────────────────── --}}
        <section x-show="tab === 'danger'" x-cloak
                 class="border border-red-600 bg-paper" style="box-shadow: 6px 6px 0 rgba(185,28,28,1);">
            <header class="flex items-center justify-between px-5 py-3 border-b border-red-600 bg-red-600 text-ivory">
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase font-bold flex items-center gap-2">
                    <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5" />
                    {{ ui_copy('account_danger_zone_eyebrow', 'account.danger_zone_eyebrow') }}
                </span>
                <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/80">{{ ui_copy('account_irreversible', 'account.irreversible') }}</span>
            </header>

            <div class="p-6 sm:p-8 space-y-5">
                <div class="border border-red-600 bg-red-50 p-5">
                    <p class="bp-spec text-red-700 mb-2">{{ ui_copy('account_delete_account_eyebrow', 'account.delete_account_eyebrow') }}</p>
                    <p class="text-sm text-red-900 leading-relaxed">
                        {{ ui_copy('account_delete_account_note', 'account.delete_account_note') }}
                    </p>
                </div>

                <form id="account-delete-form" action="{{ route('frontend.account.delete', ['lang' => $lang]) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="button"
                            x-on:click="if(confirm('{{ addslashes(ui_copy('account_delete_account_confirm', 'account.delete_account_confirm')) }}')) document.getElementById('account-delete-form').submit();"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-red-600 text-ivory border border-red-600
                                   font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                                   hover:bg-red-700 hover:border-red-700 transition-colors"
                            style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                        <x-heroicon-s-trash class="w-4 h-4" />
                        {{ ui_copy('account_permanently_delete_account', 'account.permanently_delete_account') }}
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-account.shell>
@endsection
