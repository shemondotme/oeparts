<?php

namespace App\Console\Commands;

use App\Services\ExternalLinkChecker;
use Illuminate\Console\Command;

class CheckExternalLinksCommand extends Command
{
    protected $signature = 'seo:check-external-links';

    protected $description = 'Check the links to other websites in pages and posts (weekly; the result shows in the SEO audit)';

    public function handle(ExternalLinkChecker $checker): int
    {
        $result = $checker->run();

        $this->info("Checked {$result['checked']} external link(s): ".count($result['broken']).' broken, '.$result['unverified'].' could not be verified.');
        foreach ($result['broken'] as $row) {
            $this->warn($row['href'].' — '.$row['reason']);
        }

        return self::SUCCESS;
    }
}
