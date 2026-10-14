<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Support\EncryptedSettingValue;
use Illuminate\Support\Facades\DB;

/**
 * Can this site open the secrets it holds? A stored password or API key that the key of this site cannot decrypt would look
 * configured and never work.
 */
final class SecretChecks
{
    private const GROUP = 'secrets';

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [new ReadinessProbe('secrets_reentry', self::GROUP, $this->secrets(...))];
    }

    public function secrets(ReadinessContext $c): C
    {
        $label = 'Every stored secret can be opened';
        $unreadable = [];
        $count = 0;

        foreach (DB::table('settings')->where('is_encrypted', true)->whereNotNull('value')->where('value', '<>', '')->get(['group', 'key', 'value']) as $row) {
            $count++;

            if (! EncryptedSettingValue::reveal((string) $row->value, $row->group.'.'.$row->key, true)->ok) {
                $unreadable[] = $row->group.'.'.$row->key;
            }
        }

        if ($unreadable !== []) {
            sort($unreadable);

            return $c->check('secrets_reentry', $label, self::GROUP, C::FAIL, count($unreadable).' stored secret(s) cannot be opened by this site: '.implode(', ', $unreadable).'.', ['settings' => $unreadable], 'Enter these settings again in the admin panel.');
        }

        return $c->check('secrets_reentry', $label, self::GROUP, C::PASS, $count === 0 ? 'No secret is stored.' : "{$count} stored secret(s) open correctly.");
    }
}
