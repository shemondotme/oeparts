<?php

namespace App\Console\Concerns;

use function Laravel\Prompts\password;

/**
 * Where a migration command gets its passphrase from, shared by every command that needs one.
 *
 * In order: --passphrase-file=PATH, then the environment (OEPARTS_PASSPHRASE, then
 * OE_MIGRATE_PASSPHRASE), then a hidden prompt when the command is interactive. A passphrase given
 * as a command-line argument (--passphrase=VALUE) is refused: arguments are visible to every user
 * of the machine in the process list and are kept in shell history. The option is still declared
 * (see PASSPHRASE_SIGNATURE) so the refusal explains itself instead of saying the option is unknown.
 *
 * A command that uses this also uses EmitsJson (warnings go through it, so they stay off standard
 * output under --json) and declares:
 *     protected $signature = 'oeparts:migrate:... '.self::PASSPHRASE_SIGNATURE;
 */
trait ReadsPassphrase
{
    public const PASSPHRASE_SIGNATURE = '{--passphrase-file= : Read the passphrase from this file (one line)} {--passphrase= : NOT ACCEPTED: a passphrase on the command line is visible to other users}';

    /** Longest passphrase file we will read. */
    private const PASSPHRASE_FILE_MAX_BYTES = 1024;

    /** True when the last passphrase was typed at a prompt (and so may deserve a second typing to confirm). */
    private bool $passphraseWasTyped = false;

    /**
     * A passphrase being CHOSEN (an export): when it is typed at a prompt it is asked for twice, because a
     * typo here would lock the finished package for ever. From a file or the environment it is taken as it is.
     *
     * @throws PassphraseSourceException
     */
    protected function readNewPassphrase(string $label = 'New passphrase'): string
    {
        $passphrase = $this->readPassphrase($label);

        if ($this->passphraseWasTyped && password(label: 'Type it again to confirm', required: true) !== $passphrase) {
            throw new PassphraseSourceException('The two passphrases are not the same. Nothing was exported.');
        }

        return $passphrase;
    }

    /**
     * @throws PassphraseSourceException no source, an unusable one, or a refused one
     */
    protected function readPassphrase(string $label = 'Passphrase'): string
    {
        if ($this->option('passphrase') !== null) {
            throw new PassphraseSourceException(
                '--passphrase=VALUE is not accepted: command-line arguments are visible to every user of this machine and stay in shell history. '
                .'Use --passphrase-file=PATH, the OEPARTS_PASSPHRASE environment variable, or run the command interactively.'
            );
        }

        $file = $this->option('passphrase-file');
        if (is_string($file) && $file !== '') {
            return $this->passphraseFromFile($file);
        }

        foreach (['OEPARTS_PASSPHRASE', 'OE_MIGRATE_PASSPHRASE'] as $variable) {
            $value = getenv($variable);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if ($this->input->isInteractive()) {
            $this->passphraseWasTyped = true;

            return password(label: $label, required: true, hint: 'Typing is hidden.');
        }

        throw new PassphraseSourceException(
            'No passphrase source. Set OEPARTS_PASSPHRASE, give --passphrase-file=PATH, or run the command interactively.'
        );
    }

    private function passphraseFromFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new PassphraseSourceException("The passphrase file {$path} does not exist or cannot be read.");
        }

        $size = (int) filesize($path);
        if ($size > self::PASSPHRASE_FILE_MAX_BYTES) {
            throw new PassphraseSourceException("The passphrase file {$path} is larger than ".self::PASSPHRASE_FILE_MAX_BYTES.' bytes: it should hold the passphrase and nothing else.');
        }

        if (DIRECTORY_SEPARATOR === '/' && (fileperms($path) & 0077) !== 0) {
            $message = sprintf('The passphrase file %s can be read by other users (mode %04o). Run: chmod 600 %s', $path, fileperms($path) & 0777, $path);
            $this->humanWarn($message);
        }

        // Only the line ending an editor or `echo` adds is removed; every other character is the passphrase.
        $passphrase = preg_replace('/\r?\n\z/', '', (string) file_get_contents($path)) ?? '';

        if ($passphrase === '') {
            throw new PassphraseSourceException("The passphrase file {$path} is empty.");
        }

        return $passphrase;
    }
}
