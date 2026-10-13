<?php

namespace App\Console\Concerns;

use App\Services\Migration\ExitCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Ux\MigrationText;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The two output modes of a migration command.
 *
 * Normally the command talks to a person. With --json it talks to a script: standard output
 * carries ONLY newline-delimited JSON events (progress, warning, error) and one final
 * {"event":"result", ...} line, and the human text moves to standard error, so a script can pipe
 * stdout straight into jq. Commands using this declare a --json option.
 *
 * Event payloads must never carry a passphrase, a key or a setting value: callers pass counts,
 * names and codes only.
 */
trait EmitsJson
{
    protected function jsonOutput(): bool
    {
        return (bool) $this->option('json');
    }

    /** Text for a person: standard output normally, standard error under --json. */
    protected function human(string $text): void
    {
        $this->humanOutput()->writeln($text);
    }

    protected function humanWarn(string $text): void
    {
        $this->humanOutput()->writeln('<comment>'.$text.'</comment>');

        if ($this->jsonOutput()) {
            $this->event('warning', ['message' => $text]);
        }
    }

    /** One NDJSON line on standard output; nothing is written outside --json. */
    protected function event(string $event, array $data = []): void
    {
        if (! $this->jsonOutput()) {
            return;
        }

        $line = json_encode(['event' => $event] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $this->output->writeln($line === false ? '{"event":"error","code":"json_encode_failed"}' : $line, OutputInterface::OUTPUT_RAW);
    }

    /**
     * The three plain-language texts of an error, shown to a person and emitted as an event.
     */
    protected function reportError(MigrationException $e): void
    {
        $text = app(MigrationText::class)->forException($e);

        $this->humanOutput()->writeln('<error>'.$text['title'].'</error>');
        $this->humanOutput()->writeln($text['message']);
        $this->humanOutput()->writeln('What to do: '.$text['fix']);

        $this->event('error', ['code' => $e->errorCode->value] + $text);
    }

    /** A refusal of the package reader, reported in plain language; returns the exit code to use. */
    protected function reportPackageError(PackageException $e): ExitCode
    {
        $this->reportError($e->toMigrationException());

        return $e instanceof WrongPassphraseException ? ExitCode::WrongPassphrase : ExitCode::PackageUnreadable;
    }

    /** A usage mistake (missing or refused option): plain text, never a stack trace. */
    protected function reportUsage(string $message): ExitCode
    {
        $this->humanOutput()->writeln('<error>'.$message.'</error>');
        $this->event('error', ['code' => 'usage', 'title' => 'Usage', 'message' => $message, 'fix' => '']);

        return ExitCode::Usage;
    }

    /**
     * The last line of a run. Returns the process exit code so a command can `return $this->finish(...)`.
     *
     * @param  array<string, mixed>  $fields  run_id, report, and anything else the command reports
     * @param  array<int, string>  $warnings
     */
    protected function finish(ExitCode $exit, array $fields = [], array $warnings = []): int
    {
        $this->event('result', array_merge([
            'ok' => $exit === ExitCode::Ok,
            'exit_code' => $exit->value,
            'run_id' => null,
            'report' => null,
            'warnings' => $warnings,
        ], $fields));

        return $exit->value;
    }

    /** Standard error under --json (or none when the output has no separate error stream), else the normal output. */
    private function humanOutput(): OutputInterface
    {
        if (! $this->jsonOutput()) {
            return $this->output;
        }

        if ($this->output instanceof ConsoleOutputInterface) {
            return $this->output->getErrorOutput();
        }

        return new NullOutput;
    }
}
