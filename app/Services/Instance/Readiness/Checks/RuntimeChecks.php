<?php

namespace App\Services\Instance\Readiness\Checks;

use App\Services\Instance\Readiness\ReadinessCheck as C;
use App\Services\Instance\Readiness\ReadinessContext;
use App\Services\Instance\Readiness\ReadinessProbe;
use App\Services\Migration\Import\ImportAdminPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Is the running site in working order? Someone can sign in, the scheduler ticks, uploaded files are reachable, files belong to
 * the web user, the address is secure, and the automatic things the import switched off are reported so nobody forgets them.
 */
final class RuntimeChecks
{
    private const GROUP = 'runtime';

    /** Minutes after an import or install during which a silent scheduler is "not yet" rather than "broken". */
    private const SCHEDULER_GRACE_MINUTES = 3;

    /** @return array<int, ReadinessProbe> */
    public function probes(): array
    {
        return [
            new ReadinessProbe('admin_login', self::GROUP, $this->adminLogin(...)),
            new ReadinessProbe('scheduler_heartbeat', self::GROUP, $this->schedulerHeartbeat(...)),
            new ReadinessProbe('storage_link', self::GROUP, $this->storageLink(...)),
            new ReadinessProbe('permissions_ownership', self::GROUP, $this->ownership(...)),
            new ReadinessProbe('scheme_security', self::GROUP, $this->schemeSecurity(...)),
            new ReadinessProbe('origin_switches', self::GROUP, $this->originSwitches(...)),
        ];
    }

    public function adminLogin(ReadinessContext $c): C
    {
        $label = 'Someone can sign in to the admin panel';
        $usable = app(ImportAdminPolicy::class)->usable();

        return $usable > 0
            ? $c->check('admin_login', $label, self::GROUP, C::PASS, "{$usable} administrator(s) can sign in.")
            : $c->check('admin_login', $label, self::GROUP, C::FAIL, 'No active super administrator with a password exists: nobody could sign in.', fix: 'Create an administrator from the command line (oeparts:admin:create) or restore the import.');
    }

    /** The scheduler leaves a heartbeat every minute; right after an import or install there may not have been one yet. */
    public function schedulerHeartbeat(ReadinessContext $c): C
    {
        $label = 'The scheduler is running';

        if (Cache::get('scheduler_heartbeat') !== null) {
            return $c->check('scheduler_heartbeat', $label, self::GROUP, C::PASS, 'The scheduler left a heartbeat in the last two minutes.');
        }

        $import = $c->import();
        $since = $import === null ? null : ($import->finishedAt ?? $import->createdAt);

        if ($since !== null && now()->lt(Carbon::parse($since)->addMinutes(self::SCHEDULER_GRACE_MINUTES))) {
            return $c->check('scheduler_heartbeat', $label, self::GROUP, C::PENDING, 'No heartbeat yet: the scheduler has had less than '.self::SCHEDULER_GRACE_MINUTES.' minutes since the import. Run the checks again in a minute.');
        }

        return $c->check('scheduler_heartbeat', $label, self::GROUP, C::WARN, 'The scheduler has not left a heartbeat in the last two minutes: scheduled tasks (reminders, backups, the sitemap) are not running.', fix: 'Make sure the "php artisan schedule:run" cron entry exists on this server.');
    }

    /** Uploaded files are served through public/storage, a link to storage/app/public. */
    public function storageLink(ReadinessContext $c): C
    {
        $label = 'Uploaded files are reachable';
        $public = public_path('storage');

        return is_link($public)
            ? $c->check('storage_link', $label, self::GROUP, C::PASS, 'public/storage links to the uploaded files.')
            : $c->check('storage_link', $label, self::GROUP, C::FAIL, is_dir($public) ? 'public/storage is a real folder, not a link to storage/app/public.' : 'public/storage does not exist.', fix: 'Run "php artisan storage:link".', overridable: true);
    }

    /** Files created while root was running (a command run as root) cannot be written by the web server later. */
    public function ownership(ReadinessContext $c): C
    {
        $label = 'The storage folders belong to the web user';

        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            return $c->check('permissions_ownership', $label, self::GROUP, C::SKIP, 'Checked when the report runs as the web user: as root every file looks owned by someone else.');
        }

        $rootOwned = [];

        foreach (['app', 'framework', 'logs'] as $folder) {
            $path = storage_path($folder);

            if (is_dir($path) && fileowner($path) === 0) {
                $rootOwned[] = 'storage/'.$folder;
            }
        }

        return $rootOwned === []
            ? $c->check('permissions_ownership', $label, self::GROUP, C::PASS, 'The storage folders are not owned by root.')
            : $c->check('permissions_ownership', $label, self::GROUP, C::WARN, implode(', ', $rootOwned).' belong to root: the web server may not be able to write there.', ['folders' => $rootOwned], 'Give them back to the web user (chown -R).');
    }

    /** A real address should be served over https. */
    public function schemeSecurity(ReadinessContext $c): C
    {
        $label = 'The address is served securely';
        $url = (string) config('app.url');
        $host = $c->appHost();
        $local = $host === '' || $host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.local') || filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($local || str_starts_with($url, 'https://')) {
            return $c->check('scheme_security', $label, self::GROUP, C::PASS, $local ? 'A local address needs no certificate.' : 'The address uses https.');
        }

        return $c->check('scheme_security', $label, self::GROUP, C::WARN, 'APP_URL starts with http://: visitors\' sign-ins and payments would travel unencrypted.', fix: 'Install a certificate and set APP_URL to the https address.');
    }

    /** The import switched off what arms itself; the report keeps saying so until the owner has switched each one on again. */
    public function originSwitches(ReadinessContext $c): C
    {
        $label = 'Automatic features are back on';
        $switched = (array) ($c->ledger->read()['import']['switched_off'] ?? []);

        if ($switched === []) {
            return $c->check('origin_switches', $label, self::GROUP, C::PASS, 'The import switched nothing off.');
        }

        $stillOff = [];

        foreach ($switched as $qualified) {
            [$group, $key] = array_pad(explode('.', (string) $qualified, 2), 2, '');

            if ((string) DB::table('settings')->where('group', $group)->where('key', $key)->value('value') !== '1') {
                $stillOff[] = (string) $qualified;
            }
        }

        return $stillOff === []
            ? $c->check('origin_switches', $label, self::GROUP, C::PASS, 'Everything the import switched off has been switched on again.')
            : $c->check('origin_switches', $label, self::GROUP, C::WARN, count($stillOff).' automatic setting(s) are still off after the import (for example scheduled backups): '.implode(', ', array_slice($stillOff, 0, 5)).(count($stillOff) > 5 ? ', ...' : '').'.', ['settings' => $stillOff], 'Switch each one on in the admin panel when you are ready.');
    }
}
