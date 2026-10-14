<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageReader;
use App\Support\EncryptedSettingValue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Stage 14. Secrets and the key they are locked with.
 *
 * Settings that hold a secret are stored encrypted with THIS site's key (APP_KEY), which is not the old site's. So:
 *
 *   - if the package carries its secrets (they were exported on purpose, locked separately) and the person asked for them,
 *     each is written into its row encrypted with the key of this site;
 *   - always, as the last word: any stored secret that this site's key cannot open is emptied and its name is listed, so the
 *     owner is told to enter it again instead of finding a "configured" password that never worked.
 *
 * Only names are kept in the run's state, never a value. Writing the same secret twice is harmless, so a resumed stage simply
 * does it again.
 */
final class FinSecretsStage extends FinalisingStage
{
    public function __construct(
        ImportMarker $marker,
        private readonly Kdf $kdf,
    ) {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_secrets';
    }

    protected function after(): string
    {
        return 'files.done';
    }

    protected function flag(): string
    {
        return 'fin.secrets.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The secrets are in order.');
        }

        $this->guard($state);

        $written = 0;
        $missing = [];

        if (($state->package['export']['secrets_included'] ?? false) === true && ($state->options['include_secrets'] ?? false) === true) {
            $keys = $context->escrow->get(ImportContext::escrowId($state->id));

            if ($keys === null) {
                return ImportStepResult::needsPassphrase('The key for this package has expired: type the passphrase again.');
            }

            [$written, $missing] = $this->writeSecrets((string) $state->package['path'], $keys->keys);
        }

        $reenter = $this->emptyWhatCannotBeOpened();

        $state->package['secrets'] = ['written' => $written, 'not_found' => $missing, 'reenter' => $reenter];
        $state->setFlag($this->flag());

        return ImportStepResult::done($written.' secret(s) written, '.count($reenter).' to enter again.');
    }

    /**
     * @return array{0: int, 1: array<int, string>} how many were written, and the names of those with no row to go into
     */
    private function writeSecrets(string $path, DerivedKeys $keys): array
    {
        try {
            $reader = PackageReader::openWithKeys($path, $keys, $this->kdf);
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        }

        $written = 0;
        $missing = [];

        try {
            if (! $reader->hasSecrets()) {
                return [0, []];
            }

            $reader->unlockSecrets();

            foreach (explode("\n", $reader->readSecrets()) as $line) {
                if ($line === '') {
                    continue;
                }

                $secret = json_decode($line, true);

                if (! is_array($secret) || ! is_string($secret['group'] ?? null) || ! is_string($secret['key'] ?? null) || ! is_string($secret['value'] ?? null)) {
                    throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['part' => 'secrets']);
                }

                $affected = DB::table('settings')->where('group', $secret['group'])->where('key', $secret['key'])->where('is_encrypted', true)
                    ->update(['value' => Crypt::encryptString($secret['value']), 'updated_at' => now()]);

                if ($affected === 0) {
                    $missing[] = $secret['group'].'.'.$secret['key'];
                } else {
                    $written++;
                }
            }
        } catch (PackageException $e) {
            throw $e->toMigrationException();
        } finally {
            $reader->lockSecrets();
            $reader->close();
        }

        return [$written, $missing];
    }

    /** @return array<int, string> the names of the secrets this site's key could not open, now emptied */
    private function emptyWhatCannotBeOpened(): array
    {
        $names = [];

        foreach (DB::table('settings')->where('is_encrypted', true)->whereNotNull('value')->where('value', '<>', '')->get(['id', 'group', 'key', 'value']) as $row) {
            if (! EncryptedSettingValue::reveal((string) $row->value, $row->group.'.'.$row->key, true)->ok) {
                DB::table('settings')->where('id', $row->id)->update(['value' => null, 'updated_at' => now()]);
                $names[] = $row->group.'.'.$row->key;
            }
        }

        sort($names);

        return $names;
    }
}
