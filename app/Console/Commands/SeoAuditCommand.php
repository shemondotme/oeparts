<?php

namespace App\Console\Commands;

use App\Services\SeoAuditMonitor;
use Illuminate\Console\Command;

class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit {--no-notify : Only report — do not alert the admins}';

    protected $description = 'Run the SEO audit and alert the admins when a critical problem appears (or stays open for a week)';

    public function handle(SeoAuditMonitor $monitor): int
    {
        $result = $monitor->run(notify: ! $this->option('no-notify'));

        $this->info("Critical problems: {$result['critical']}");

        if ($result['announced'] !== []) {
            $this->warn('Announced: '.implode(', ', $result['announced']).($result['notified'] > 0 ? " (sent to {$result['notified']} admin(s))" : ''));
        }

        if ($result['resolved'] !== []) {
            $this->info('Resolved since the last run: '.implode(', ', $result['resolved']));
        }

        return self::SUCCESS;
    }
}
