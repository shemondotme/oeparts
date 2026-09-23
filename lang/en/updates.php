<?php

/*
|--------------------------------------------------------------------------
| System Updates Page UI Strings (Filament admin, App\Filament\Pages\System\
| SystemUpdates + resources/views/filament/pages/system/system-updates.blade.php)
|--------------------------------------------------------------------------
|
| Keys are machine-generated slugs of the original English text, same
| convention as lang/en/admin.php (see CLAUDE.md rule #60). Machine-
| translated into de/es/fr/lt; flagged for human review before relying on
| it for anything beyond internal staff use.
|
*/

return [
    // ---- Notifications (App\Filament\Pages\System\SystemUpdates) ----
    'update_settings_saved' => 'Update settings saved',
    'no_update_to_apply' => 'No update to apply',
    'update_cannot_start' => 'Update cannot start',
    'preflight_checks_failing' => 'Pre-flight checks are failing — see the details below.',
    'please_acknowledge_warnings' => 'Please acknowledge the warnings below before applying',
    'update_started' => 'Update started',
    'do_not_close_window' => 'Do not close this window.',
    'update_complete' => 'Update complete',
    'now_running' => 'Now running :version.',
    'update_did_not_complete' => 'Update did not complete',
    'see_update_history' => 'See the update history.',
    'could_not_reach_update_server' => 'Could not reach the update server',
    'please_try_again_later' => 'Please try again later.',
    'security_update_word' => 'Security update',
    'update_word' => 'Update',
    'x_available' => ':type available',
    'you_are_up_to_date' => 'You are up to date',
    'running_latest_version' => 'Running the latest version (:version).',
    'please_slow_down' => 'Please slow down',
    'too_many_checks' => 'Too many checks — try again in a moment.',
    'too_many_attempts' => 'Too many attempts — try again in a minute.',
    'your_password_is_incorrect' => 'Your password is incorrect.',

    // ---- Page (system-updates.blade.php) ----
    'system_updates' => 'System Updates',
    'page_intro' => "Check for new OeParts releases and review the changelog. When an update is available, apply it with one click below — a full backup runs first, and it's verified automatically.",
    'check_now' => 'Check now',
    'update_server_unreachable' => 'Update server unreachable',
    'security_update_available' => 'Security update available',
    'update_available' => 'Update available',
    'up_to_date' => 'Up to date',
    'installed_label' => 'Installed:',
    'latest_label' => 'Latest:',
    'channel_label' => 'Channel:',
    'readiness' => 'Readiness',
    'recovery_console_label' => 'Recovery console:',
    'armed_update_window_open' => 'Armed (update window open)',
    'not_armed' => 'Not armed',
    'apply_this_update' => 'Apply this update',
    'review_what_will_happen' => 'Review exactly what will happen before updating to :version — a full backup is taken first, the site enters maintenance mode, and the update is applied and verified automatically.',
    'review_and_apply_update' => 'Review & apply update',
    'confirm_update_label' => 'Confirm update:',
    'download_size' => 'Download size',
    'migrations' => 'Migrations',
    'est_time' => 'Est. time',
    'pre_flight' => 'Pre-flight',
    'fail_warn_counts' => ':fail fail / :warn warn',
    'breaking_changes' => 'Breaking changes',
    'your_password' => 'Your password',
    'acknowledge_warnings_checkbox' => "I've reviewed the warnings above and want to proceed anyway",
    'confirm_and_apply' => 'Confirm & apply',
    'cancel' => 'Cancel',
    'preflight_failing_resolve' => 'Pre-flight checks are failing — resolve the issues above before applying.',
    'close' => 'Close',
    'step_backup' => 'Backing up database & files',
    'step_download' => 'Downloading release',
    'step_extract' => 'Extracting release',
    'step_swap' => 'Swapping in new files',
    'step_git_checkout' => 'Pulling latest code (git)',
    'step_composer_install' => 'Installing dependencies (composer)',
    'step_finalize' => 'Running migrations',
    'step_verify' => 'Verifying the update',
    'step_of' => '(step :current of :total)',
    'keep_window_open' => "Keep this window open — the page reloads automatically when the update finishes. Long steps (backup, download) can take a while; the browser tab title won't change, but no action is needed from you.",
    'new_version' => 'New Version',
    'released' => 'Released',
    'db_migrations' => 'DB Migrations',
    'multi_step_upgrade' => 'This is a multi-step upgrade — apply in order:',
    'view_changelog' => 'View changelog',
    'download_release_manual' => 'Download release (manual install)',
    'recent_updates' => 'Recent Updates',
    'view_full_history' => 'View full history →',
    'version_col' => 'Version',
    'status_col' => 'Status',
    'when_col' => 'When',
    'update_settings' => 'Update Settings',
    'release_channel' => 'Release channel',
    'stable' => 'Stable',
    'beta' => 'Beta',
    'auto_apply_security_updates' => 'Auto-apply security updates',
    'save' => 'Save',
    'last_checked' => 'Last checked: :time',

    // ---- Deploy-freshness admin banner (build-freshness-check.blade.php) ----
    'new_admin_version_available' => 'A new version of the admin panel is available.',
    'refresh' => 'Refresh',
    'stale_session_title' => "This page's code was just updated on the server.",
    'stale_session_body' => 'Please refresh to continue — any unsaved changes on this page may be lost.',
    'refresh_now' => 'Refresh now',
];
