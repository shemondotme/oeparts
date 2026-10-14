<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Transport\TransferLimits;
use Illuminate\Console\Command;

/** Shows the numbers a package download works within, and the room left on the disk it lands on. */
class MigrateLimits extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:limits {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Show the limits that apply to downloading a package from a link';

    public function handle(TransferLimits $limits): int
    {
        $values = $limits->describe();
        $this->event('limits', $values);

        foreach ($values as $name => $value) {
            $this->human(str_pad(str_replace('_', ' ', $name), 28).' '.($value ?? 'unknown'));
        }

        return $this->finish(ExitCode::Ok, ['limits' => $values]);
    }
}
