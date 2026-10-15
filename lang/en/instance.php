<?php

/*
|--------------------------------------------------------------------------
| Site mode (System > Site mode) - English text
|--------------------------------------------------------------------------
|
| The words of the page that shows what this installation is (the real shop, a testing copy, or a copy
| that is not trusted yet), runs the launch checks, and lets an admin release it or take it live.
| A separate file on purpose: lang/en/migration.php has other writers. The de/es/fr/lt files arrive with
| the translations of Phase 12. Laravel falls back to this file until then.
|
| The text of the checks themselves (what was looked at, what was found, what to do) comes from the
| readiness services, which write plain English; only the frame around it is here.
|
*/

return [

    'page' => [
        'title' => 'Site mode',
        'subheading' => 'Is this the real shop, a testing copy, or a copy that is not trusted yet? Run the launch checks, and release or take the site live when nothing blocks it.',
        'run_checks' => 'Run checks',
        'run_checks_tip' => 'Look at the whole site again. It can take a minute on a big shop; you can leave this page open.',
        'invalid_title' => 'The mode in .env is not valid',
        'invalid_text' => 'OE_MODE says ":value", which is not production, staging or quarantine. The site is being run as quarantine until that is fixed. Releasing it from quarantine writes a valid mode.',
    ],

    'tabs' => [
        'overview' => 'Overview',
        'readiness' => 'Readiness',
        'safe_mode' => 'Safe mode',
    ],

    'mode' => [
        'production' => 'Production',
        'staging' => 'Staging',
        'quarantine' => 'Quarantine',
        'production_text' => 'This is the real shop. Nothing is held back.',
        'staging_text' => 'This is a testing copy. No real mail or payments leave it, and search engines are told to stay away. When you are happy with it, you can take it live.',
        'quarantine_text' => 'This copy was just moved in and is not trusted yet. It sends nothing and runs only the work that keeps it healthy. Run the checks; when nothing blocks it, release it from quarantine.',
    ],

    'overview' => [
        'this_site' => 'This site',
        'mode' => 'Mode',
        'instance_id' => 'Instance id',
        'none' => '(none)',
        'site_address' => 'Site address',
        'held_back' => 'Held back',
        'held_nothing' => 'nothing',
        'ledger' => 'Ledger (the record of mode changes)',
        'ledger_value' => 'last recorded mode :mode',
        'drift_title' => 'The record and .env disagree',
        'next_title' => 'What to do next',
        'next_quarantine' => 'Press "Run checks". When nothing blocks the launch, press "Release from quarantine" and choose whether this is the real shop or a testing copy.',
        'next_staging' => 'Test the copy as much as you like. When you want it to be the real shop, press "Go live": you will type the site\'s host to confirm.',
        'next_production' => 'Nothing to do here. The checks can still be run at any time.',
        'last_checks' => 'Last check',
        'never_checked' => 'The checks have not been run on this site yet.',
        'checked_when' => 'Run :when',
        'unfinished' => 'The last run did not finish. Press "Run checks" to start again.',
        'counts' => ':pass passed, :warn to look at, :fail failed, :pending not yet known, :skip skipped.',
        'clear' => 'Nothing blocks the launch.',
        'blocked' => 'Something blocks the launch. See the Readiness tab.',
        'changes_title' => 'Recent changes to .env',
        'changes_empty' => 'No change has been recorded yet.',
        'change_line' => ':when by :who changed :keys',
    ],

    'readiness' => [
        'heading' => 'Launch checks',
        'description' => 'What was looked at, what was found, and what to do about it. A failure or a check that is still waiting blocks the launch.',
        'col_status' => 'Result',
        'col_check' => 'Check',
        'col_group' => 'Area',
        'col_fix' => 'What to do',
        'waivable' => 'can be waived with a reason',
        'from_server' => 'The same checks can be run on the server: php artisan oeparts:instance:readiness',
        'empty_heading' => 'No checks have run yet',
        'empty_text' => 'Press "Run checks" at the top of the page.',
        'unfinished' => 'This run did not finish: :done checks are in so far.',
        'status' => [
            'pass' => 'Passed',
            'warn' => 'Look at this',
            'fail' => 'Failed',
            'pending' => 'Waiting',
            'skip' => 'Skipped',
        ],
        'groups' => [
            'data' => 'Data',
            'identity' => 'Identity',
            'secrets' => 'Secrets',
            'mode' => 'Mode',
            'runtime' => 'Running site',
            'domain' => 'Addresses',
        ],
    ],

    'run' => [
        'title' => 'Checking the site',
        'progress' => ':done of :total checks done',
        'last' => 'Last one: :label',
        'keep_open' => 'You can leave this page open. If you close it, the checks stop here and "Run checks" starts them again.',
        'started' => 'Checks started',
        'finished_title' => 'The checks are done',
        'finished_clear' => 'Nothing blocks the launch.',
        'finished_blocked' => 'Something blocks the launch. See the Readiness tab.',
        'failed_title' => 'The checks could not run',
        'failed_body' => 'Something went wrong while checking. The reason is in the log. Try again.',
    ],

    'safe' => [
        'production_title' => 'Safe mode holds nothing back',
        'production_text' => 'This is the real shop, so mail, payments, search engines and scheduled work all behave as normal.',
        'holds_title' => 'What this copy holds back',
        'holds_text' => 'The stored settings are never changed. This copy only reads them as switched off, so going live brings the real ones back at once.',
        'mail' => 'Mail',
        'mail_production' => 'delivered as normal',
        'mail_quarantine' => 'dropped',
        'mail_staging_none' => 'dropped (the allow-list is empty)',
        'mail_staging_list' => 'dropped, except to the allow-list (:count); subjects start with [STAGING]',
        'schedule' => 'Scheduled work',
        'schedule_value' => 'keeping the site healthy: :always; housekeeping: :internal; reaching customers: :customer',
        'yes' => 'runs',
        'no' => 'held',
        'web_cron' => 'Web cron',
        'web_cron_on' => 'allowed',
        'web_cron_off' => 'off (set OE_NONPROD_HTTP_CRON=true in .env to allow it)',
        'numbers' => 'Order and invoice numbers',
        'numbers_value' => 'start with :prefix so a test number is never mistaken for a real one',
        'search' => 'Search engines',
        'search_value' => 'told to stay away (robots.txt disallows everything)',
        'search_open' => 'not held back',
        'masking' => 'Settings read as switched off',
        'masking_value' => ':count',
        'masked_heading' => 'Settings this copy reads as switched off',
        'masked_description' => 'The value stored in the database is untouched. This is what the copy sees instead.',
        'col_group' => 'Group',
        'col_setting' => 'Setting',
        'col_shows' => 'This copy sees',
        'shows_off' => 'off',
        'shows_on' => 'on',
        'shows_empty' => '(empty)',
        'masked_empty' => 'Nothing is masked',
    ],

    'allowlist' => [
        'title' => 'Mail allow-list',
        'description' => 'A staging copy drops every mail except to these addresses. Add an exact address (tester@example.com) or a whole domain (@example.com). Mail to anyone else is dropped. A site in quarantine drops everything, so the list starts to work once the copy is released as a testing copy. The real shop ignores it.',
        'field' => 'Addresses and domains',
        'field_help' => 'Type one and press Enter. At most :max entries; letters, digits and . _ + - only.',
        'save' => 'Save allow-list',
        'saved' => 'Allow-list saved',
        'saved_restarted' => 'The queue workers were told to restart so they use the new list.',
        'saved_plain' => 'It applies to the next mail.',
        'unchanged' => 'Nothing changed',
        'unchanged_text' => 'The allow-list is already like that.',
        'not_a_copy' => 'The real shop delivers all mail, so there is no allow-list to edit.',
        'too_many' => 'Use at most :max entries.',
        'bad_entry' => '":entry" is not an address like tester@example.com or a domain like @example.com.',
        'failed' => 'The allow-list could not be saved',
        'failed_body' => 'The .env file could not be written. The reason is in the log. Nothing was changed.',
    ],

    'release' => [
        'action' => 'Release from quarantine',
        'heading' => 'Release this site from quarantine',
        'description' => 'Every launch check is run again first. If nothing blocks it, the site leaves quarantine and the queue workers are restarted so they follow the new mode. This can take a minute.',
        'to' => 'What is this site?',
        'to_production' => 'The real, live shop',
        'to_staging' => 'A testing copy',
        'to_help' => 'A testing copy keeps mail, payments and search engines switched off. The real shop does not.',
        'submit' => 'Release',
        'done_title' => 'The site is released',
        'done_body' => 'It is now :mode.',
        'no_report' => 'No complete check has been run yet. Press "Run checks" first to see what blocks the release.',
    ],

    'go_live' => [
        'action' => 'Go live',
        'heading' => 'Take this copy live as the real shop',
        'description' => 'Mail will be delivered, real card payments can be taken, search engines are let in and scheduled work runs. Every launch check is run again first, and the queue workers are restarted so they follow the new mode. This can take a minute.',
        'expected' => 'The host of this site is :host',
        'host' => 'Type the host of this site to confirm',
        'host_help' => 'Type :host exactly.',
        'host_wrong' => 'That is not the host of this site.',
        'activity_title' => 'This copy has been used since it was made',
        'activity_text' => ':list. Going live would carry that into the real shop.',
        'discard' => 'Delete that activity before going live',
        'discard_help' => 'Deletes only what was created on this copy, and puts the order and invoice numbers back, so the real shop has no gap.',
        'payments_title' => 'Real card payments',
        'payments' => 'I understand the card gateway is set to LIVE and real money will be taken',
        'submit' => 'Go live',
        'done_title' => 'The site is live',
        'done_body' => 'It is now the real shop.',
    ],

    'confirm' => [
        'password' => 'Your password',
        'password_help' => 'Type your password again to confirm.',
        'password_incorrect' => 'Your password is incorrect.',
        'waive_label' => 'Waive: :label',
        'waive_help' => 'Why is it fine to launch with this? At least ten characters. Leave empty to waive nothing.',
        'waive_short' => 'Give a reason of at least ten characters.',
    ],

    'blocked' => [
        'default' => 'The launch was stopped',
        'not_quarantine' => 'This site is not in quarantine',
        'not_staging' => 'This site is not a staging copy',
        'host_not_typed' => 'The host does not match',
        'activity_present' => 'This copy has been used',
        'payments_unconfirmed' => 'Real payments are not confirmed',
        'waiver_refused' => 'That check cannot be waived',
        'readiness_blocked' => 'The launch checks are not all clear',
        'workers_stale' => 'A queue worker kept the old mode',
        'unexpected_title' => 'The change could not be made',
        'unexpected_body' => 'Something went wrong. The reason is in the log. Look at the Overview tab to see which mode the site is in now.',
    ],

];
