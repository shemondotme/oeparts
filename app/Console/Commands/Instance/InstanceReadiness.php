<?php

namespace App\Console\Commands\Instance;

use App\Services\Instance\Readiness\ReadinessCheck;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessReport;
use App\Services\Instance\Readiness\ReadinessRunner;
use App\Services\Migration\ExitCode;
use Illuminate\Console\Command;

/**
 * Runs the launch-readiness checks of this installation and says, plainly, whether the site can be released or gone live: every
 * check with its result and what to do about it. Read only: it looks, it changes nothing.
 *
 * Exit code 0 = no failure and nothing pending (and, with --strict, no warning); 3 = something blocks the launch;
 * 9 = only warnings, and --strict was given.
 */
class InstanceReadiness extends Command
{
    protected $signature = 'oeparts:instance:readiness
        {--json : Print one JSON document instead of text}
        {--strict : Exit with a non-zero code when there are warnings too}';

    protected $description = 'Run the launch-readiness checks of this installation';

    public function handle(ReadinessRunner $runner): int
    {
        $report = $runner->run(ReadinessContext::make());

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->describe($report);
        }

        $counts = $report->counts();

        if (! $report->canRelease()) {
            return ExitCode::PreflightBlocked->value;
        }

        return $this->option('strict') && $counts[ReadinessCheck::WARN] > 0 ? ExitCode::FinishedWithWarnings->value : ExitCode::Ok->value;
    }

    private function describe(ReadinessReport $report): void
    {
        $group = null;

        foreach ($report->checks as $check) {
            if ($check->group !== $group) {
                $group = $check->group;
                $this->line('');
                $this->line('<options=bold>'.ucfirst($group).'</>');
            }

            $mark = match ($check->status) {
                ReadinessCheck::PASS => '<info>PASS</info>',
                ReadinessCheck::WARN => '<comment>WARN</comment>',
                ReadinessCheck::FAIL => '<error> FAIL </error>',
                ReadinessCheck::PENDING => '<comment>WAIT</comment>',
                default => 'skip',
            };

            $this->line("  {$mark}  {$check->label}".($check->message !== '' ? ' - '.$check->message : ''));

            if ($check->fix !== '' && in_array($check->status, [ReadinessCheck::FAIL, ReadinessCheck::WARN, ReadinessCheck::PENDING], true)) {
                $this->line("        <comment>Fix:</comment> {$check->fix}");
            }
        }

        $counts = $report->counts();
        $this->line('');
        $this->line("{$counts[ReadinessCheck::PASS]} passed, {$counts[ReadinessCheck::WARN]} to look at, {$counts[ReadinessCheck::FAIL]} failed, {$counts[ReadinessCheck::PENDING]} not yet known, {$counts[ReadinessCheck::SKIP]} skipped.");
        $this->line($report->canRelease() ? '<info>Nothing blocks the launch.</info>' : '<error>Something blocks the launch.</error>');
    }
}
