<?php

namespace App\Support;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Event;

/**
 * Schedule::command('sitemap:generate') stores its raw shell invocation on
 * Event::$command — e.g. "'/usr/bin/php8.4' 'artisan' sitemap:generate" —
 * not the bare command name. Anything displaying or re-invoking a scheduled
 * task (ScheduledTasksPage, cron logging) needs the bare name back out.
 */
class ScheduleCommandName
{
    public static function for(Event $event): string
    {
        if (is_string($event->description) && $event->description !== '') {
            return $event->description;
        }

        $prefix = ConsoleApplication::phpBinary() . ' ' . ConsoleApplication::artisanBinary() . ' ';

        return str_starts_with($event->command, $prefix)
            ? substr($event->command, strlen($prefix))
            : $event->command;
    }
}
