<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Instance\InstanceCacheReset;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use App\Services\SettingsService;
use App\Support\DatabaseTranslationLoader;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stage 20 (a failure here is a warning, never the end of the run). Everything the application remembered about the OLD data is
 * forgotten, and the caches really use the NEW prefix:
 *
 *   - the language list, every translation override (language x group), every settings group, the permission cache;
 *   - the stores that were already resolved in this process keep the old prefix, so they are dropped and resolved again;
 *   - the stores that belong to this installation alone (file, array, database) are emptied; Redis and Memcached are NEVER
 *     flushed (they may be shared with other sites; the new prefix already gives this site a clean view of them);
 *   - the framework's own caches (config, routes, views, events) are cleared and rebuilt, as an update does.
 */
final class FinCachesStage extends FinalisingStage
{
    public function __construct(ImportMarker $marker, private readonly InstanceCacheReset $stores, private readonly SettingsService $settings)
    {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_caches';
    }

    protected function after(): string
    {
        return 'fin.sequences.done';
    }

    protected function flag(): string
    {
        return 'fin.caches.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('The caches are fresh.');
        }

        $this->guard($state);

        $this->attempt($state, 'the language list', fn () => LocaleRegistry::forget());
        $this->attempt($state, 'the translation overrides', fn () => $this->forgetTranslations());
        $this->attempt($state, 'the settings', fn () => $this->forgetSettings());
        $this->attempt($state, 'the permissions', fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        $this->attempt($state, 'the resolved cache stores', function (): void {
            app('cache')->forgetDriver();

            if (app()->bound('redis')) {
                app('redis')->purge();
            }
        });
        $this->attempt($state, 'the cache stores of this site', function () use ($state): void {
            $result = $this->stores->reset();

            // Only the store this site really uses is worth a word: the others are configured but idle.
            foreach ($result['skipped'] as $skipped) {
                if ($skipped['store'] === (string) config('cache.default')) {
                    $this->warn($state, 'The '.$skipped['store'].' cache was not emptied: '.$skipped['reason'].'. The new cache prefix keeps the keys of this site apart.');
                }
            }
        });

        foreach (['config', 'route', 'view', 'event'] as $cache) {
            $this->attempt($state, "the {$cache} cache", fn () => Artisan::call($cache.':clear'));
        }

        if ((bool) config('migration.import.rebuild_framework_cache', true)) {
            foreach (['config', 'route', 'view', 'event'] as $cache) {
                $this->attempt($state, "rebuilding the {$cache} cache", fn () => Artisan::call($cache.':cache'));
            }
        }

        $state->setFlag($this->flag());

        return ImportStepResult::done('The caches are fresh.');
    }

    private function attempt(ImportState $state, string $what, \Closure $do): void
    {
        try {
            $do();
        } catch (\Throwable $e) {
            $this->warnFailure($state, "Could not clear {$what}.", $e);
        }
    }

    private function forgetTranslations(): void
    {
        $locales = Schema::hasTable('languages') ? DB::table('languages')->pluck('code')->map(fn ($c): string => (string) $c)->all() : [];
        $groups = Schema::hasTable('language_strings') ? DB::table('language_strings')->distinct()->pluck('group')->map(fn ($g): string => (string) $g)->all() : [];

        foreach (glob(lang_path('en/*.php')) ?: [] as $file) {
            $groups[] = basename($file, '.php');
        }

        foreach ($locales as $locale) {
            foreach (array_unique($groups) as $group) {
                DatabaseTranslationLoader::forget($locale, $group);
            }
        }
    }

    private function forgetSettings(): void
    {
        $groups = Schema::hasTable('settings') ? DB::table('settings')->distinct()->pluck('group')->map(fn ($g): string => (string) $g)->all() : [];

        foreach ($groups as $group) {
            $this->settings->forget($group);
        }
    }
}
