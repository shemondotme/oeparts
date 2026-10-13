<?php

/*
|--------------------------------------------------------------------------
| This installation's role, and what may run while it is held still
|--------------------------------------------------------------------------
|
| A site can be held still on purpose: a freeze export has put the shop in maintenance, an import is
| replacing the database, or (later) the installation is a staging copy that must not touch real
| customers. Something that holds the site still registers itself with App\Services\Instance\SystemGate,
| and the gate then decides, for every scheduled command, whether it may run.
|
| Every scheduled command belongs to exactly one class:
|
|   always    keeps the installation healthy and must run even when everything else is held:
|             reclaimers of abandoned runs and stale locks, the scheduler heartbeat, the migration
|             watchdog and janitor.
|   internal  housekeeping and monitoring that touches no customer: regenerating files, pruning logs,
|             snapshots, backups, cleanup. Held while a freeze or an import is under way (they would
|             change the data being copied, or fight for the same lock), but allowed on a staging copy.
|   customer  anything that reaches people or money: mail, newsletters, reminders, order
|             transitions, admin push, automatic updates. Held in every case; a staging copy must
|             never send a real customer anything.
|
| A scheduled command that is in none of the lists is "unknown" and is held whenever anything is holding
| the site (fail closed): a task added next month cannot slip through a freeze by not being classified.
| ScheduleGateTest fails the build until every scheduled command is listed here, and flags stale entries.
|
| Names are the bare command names, without arguments.
|
*/

return [

    'schedule' => [

        'always' => [
            'oeparts:backup:cleanup-stale',
            'oeparts:update:cleanup-stale',
            'oeparts:cleanup:cleanup-stale',
            'scheduler:heartbeat',
            'oeparts:migrate:watchdog',
            'oeparts:migrate:janitor',
        ],

        'internal' => [
            'sitemap:generate',
            'otp:clean',
            'cart:clean',
            'logs:clean',
            'oeparts:backup',
            'oeparts:cleanup:auto',
            'oeparts:cleanup:measure',
            'oeparts:update:check',
            'health:snapshot',
            'cache:snapshot',
            'cwv:snapshot',
            'notfound:snapshot',
            'oeparts:invoices:clean-cache',
            'oeparts:products:optimize-search-index',
        ],

        'customer' => [
            'oeparts:invoices:remind',
            'abandoned-cart:process',
            'oeparts:newsletter:send-due',
            'oeparts:orders:auto-complete',
            'oeparts:orders:expire-unpaid',
            'oeparts:update:auto-apply',
            'oeparts:refunds:clean-images',
            'oeparts:admin-push:flush',
        ],

    ],

];
