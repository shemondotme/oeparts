<?php

namespace App\Console\Commands\Instance;

use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use App\Services\Instance\SystemGate;
use Illuminate\Console\Command;

/**
 * Says what this installation is and what that means right now: its mode, why it is held (if it is), what it is
 * hiding from itself, how its mail is handled, and whether the ledger and .env agree. Read only. Shows names and
 * counts, never a setting value, a key or an address.
 */
class InstanceStatus extends Command
{
    protected $signature = 'oeparts:instance:status
        {--json : Print one JSON document instead of text}';

    protected $description = 'Show this installation\'s mode, what it holds back, its ledger and any drift';

    public function handle(SafeMode $safe, SystemGate $gate, InstanceState $state): int
    {
        $mode = $safe->mode();
        $ledger = $state->read();
        $warnings = [];

        if ($safe->hasInvalidValue()) {
            $warnings[] = 'OE_MODE in .env is "'.trim((string) config('instance.mode')).'", which is not production, staging or quarantine. This site is being run as quarantine until that is fixed.';
        }

        $drift = $state->drift($mode);
        if ($drift !== null) {
            $warnings[] = $drift;
        }

        $overrides = $safe->activeOverrides();

        $report = [
            'mode' => $mode->value,
            'configured_value' => $safe->hasInvalidValue() ? trim((string) config('instance.mode')) : null,
            'instance_id' => config('instance.id') ?: null,
            'app_url' => config('app.url'),
            'held' => ['level' => $gate->level()->name, 'reasons' => $gate->reasons()],
            'mail' => $this->mailPolicy($safe),
            'web_cron_allowed' => $safe->httpCronAllowed(),
            'schedule' => [
                'always' => $safe->scheduledAllowed(SystemGate::ALWAYS),
                'internal' => $safe->scheduledAllowed(SystemGate::INTERNAL),
                'customer' => $safe->scheduledAllowed(SystemGate::CUSTOMER),
            ],
            'sequence_example' => $safe->sequencePrefix('ORD'),
            'masked_settings' => count($overrides),
            'overrides' => array_map(static fn (array $o): string => $o['group'].'.'.$o['key'], $overrides),
            'ledger' => [
                'recorded_mode' => $state->recordedMode(),
                'drift' => $drift,
                'watermarks' => $ledger['watermarks'],
                'recent_changes' => array_map(
                    static fn (array $c): array => ['at' => $c['at'] ?? null, 'by' => $c['by'] ?? null, 'keys' => array_keys((array) ($c['changes'] ?? []))],
                    array_slice((array) $ledger['env_history'], -5),
                ),
            ],
            'warnings' => $warnings,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->render($report);

        return self::SUCCESS;
    }

    /** @return array{what: string, allowlist_entries: int} */
    private function mailPolicy(SafeMode $safe): array
    {
        $what = match ($safe->mode()->value) {
            'production' => 'delivered as normal',
            'staging' => $safe->mailAllowList() === [] ? 'dropped (no allow-list)' : 'dropped except to the allow-list, subjects marked [STAGING]',
            default => 'dropped',
        };

        return ['what' => $what, 'allowlist_entries' => count($safe->mailAllowList())];
    }

    /** @param  array<string, mixed>  $r */
    private function render(array $r): void
    {
        $this->line('Mode:           '.strtoupper($r['mode']).($r['configured_value'] !== null ? '  (OE_MODE says "'.$r['configured_value'].'")' : ''));
        $this->line('Instance id:    '.($r['instance_id'] ?? '(none)'));
        $this->line('Site URL:       '.$r['app_url']);
        $this->line('Held back:      '.($r['held']['reasons'] === [] ? 'nothing' : implode('; ', $r['held']['reasons']).' ('.$r['held']['level'].')'));
        $this->line('Mail:           '.$r['mail']['what']);
        $this->line('Web cron:       '.($r['web_cron_allowed'] ? 'allowed' : 'off'));
        $this->line('Scheduled work: healthy-keeping '.$this->yn($r['schedule']['always']).', internal '.$this->yn($r['schedule']['internal']).', customer-facing '.$this->yn($r['schedule']['customer']));
        $this->line('Order numbers:  '.$r['sequence_example'].'-...');
        $this->line('Masked:         '.$r['masked_settings'].' setting(s) read as switched off'.($this->output->isVerbose() ? ':' : ' (run with -v to list them)'));

        if ($this->output->isVerbose()) {
            foreach ($r['overrides'] as $name) {
                $this->line('                - '.$name);
            }
        }

        $this->line('Ledger:         last recorded mode '.($r['ledger']['recorded_mode'] ?? '(none)'));

        foreach ($r['ledger']['recent_changes'] as $change) {
            $this->line('                '.$change['at'].'  '.$change['by'].'  changed '.implode(', ', $change['keys']));
        }

        if ($r['ledger']['watermarks'] !== []) {
            $this->line('Watermarks:     '.json_encode($r['ledger']['watermarks']));
        }

        foreach ($r['warnings'] as $warning) {
            $this->warn($warning);
        }
    }

    private function yn(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
