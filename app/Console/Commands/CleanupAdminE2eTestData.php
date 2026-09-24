<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Language;
use App\Models\Manufacturer;
use App\Models\Page;
use App\Models\Product;
use App\Models\Section;
use App\Models\Testimonial;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Sweeps up "E2E ..." rows the admin CRUD e2e suite (crud-create.spec.js,
 * crud-edit.spec.js) leaves behind on the customer-facing tables.
 *
 * That suite creates a throwaway record for every one of ~27 Filament
 * resources on every run and never deletes any of them — confirmed live:
 * this dev DB had accumulated 34 leaked "E2E ..." rows from prior runs
 * across exactly these 9 tables (found via a manual sweep during a
 * frontend/UX audit — they were showing up as real content on /brands,
 * /blog, the language switcher, and the homepage testimonials/FAQ
 * sections). Scoped to the customer-facing tables specifically, not all
 * ~27 resources the suite touches — the rest (Admin, Coupon, Order,
 * ShippingMethod, etc.) are admin-panel-only and don't leak onto a page
 * a real visitor sees, so they're lower-priority clutter rather than a
 * visible bug.
 *
 * Every fill() in crud-create.spec.js prefixes its display-name field
 * with a literal "E2E " (with the space) specifically so a sweep like
 * this one can find them unambiguously — real content never starts that
 * way. Run standalone (`php artisan oeparts:e2e:cleanup-crud-leftovers`)
 * or wired into crud-create.spec.js's own afterAll so the suite cleans up
 * after itself going forward instead of only being swept manually.
 *
 * NEVER deletes a row named "E2E Edited ...": that prefix belongs to
 * crud-edit.spec.js, which temporarily renames the lowest-id REAL row of
 * each resource and reverts it at the end of the test. If a run is
 * interrupted between the rename and the revert (a timeout, a killed
 * process, Docker going down), the real row is left wearing that name — and
 * a "delete everything named E2E ..." sweep would then permanently delete
 * genuine data. Confirmed live 2026-09-24: this deleted the real "en"
 * Language row, which 404'd every /en/... URL on the storefront (the whole
 * site) and made ~430 e2e tests fail for a reason that had nothing to do
 * with the code under test. Renamed-real rows are reported for manual
 * restore instead. The default language is additionally exempt regardless
 * of its name — it is the routing/hreflang/sitemap anchor.
 */
class CleanupAdminE2eTestData extends Command
{
    /** crud-edit.spec.js's temporary rename of a REAL row — never a leaked, created one. */
    private const EDITED_PREFIX = 'E2E Edited ';

    protected $signature = 'oeparts:e2e:cleanup-crud-leftovers';

    protected $description = 'Delete "E2E ..." rows left behind by the admin CRUD e2e suite on customer-facing tables';

    public function handle(): int
    {
        $deleted = 0;
        $deleted += $this->sweep(Language::class, 'name');
        $deleted += $this->sweep(Manufacturer::class, 'name->en');
        $deleted += $this->sweep(BlogPost::class, 'title->en');
        $deleted += $this->sweep(Page::class, 'title->en');
        $deleted += $this->sweep(Category::class, 'name->en');
        $deleted += $this->sweep(Testimonial::class, 'name');
        $deleted += $this->sweep(Product::class, 'name->en');
        $deleted += $this->sweep(Faq::class, 'question->en');
        $deleted += $this->sweep(Section::class, 'title->en');
        $deleted += $this->sweep(Carrier::class, 'name');

        // Role uses its own naming convention (crud-create.spec.js fills it
        // as lowercase `e2e_role_{suffix}`, not "E2E ...") and needs its own
        // guard: NEVER delete a role literally named "super_admin", no
        // matter what — confirmed live this session that role is the
        // Gate::before trust anchor for every admin permission check, and a
        // prior test run renaming it away from that name broke admin
        // access panel-wide until manually restored.
        $roles = Role::where(function ($q) {
            $q->where('name', 'like', 'e2e_role_%')
                ->orWhere('name', 'like', 'E2E %');
        })
            ->where('name', '!=', 'super_admin')
            ->where('name', 'not like', self::EDITED_PREFIX.'%')
            ->get();
        if ($roles->isNotEmpty()) {
            $roles->each(fn ($role) => $role->delete());
            $this->line('  '.Role::class.': deleted '.$roles->count());
            $deleted += $roles->count();
        }

        $this->reportRenamedRealRows(Role::class, 'name');

        $this->info("Deleted {$deleted} leaked E2E test row(s).");

        return self::SUCCESS;
    }

    private function sweep(string $modelClass, string $column): int
    {
        $query = $modelClass::where($column, 'like', 'E2E %')
            ->where($column, 'not like', self::EDITED_PREFIX.'%');

        if ($modelClass === Language::class) {
            $query->where('is_default', false);
        }

        $rows = $query->get();
        $count = $rows->count();

        if ($count > 0) {
            $rows->each(fn ($row) => $row->delete());
            $this->line("  {$modelClass}: deleted {$count}");
        }

        $this->reportRenamedRealRows($modelClass, $column);

        return $count;
    }

    private function reportRenamedRealRows(string $modelClass, string $column): void
    {
        foreach ($modelClass::where($column, 'like', self::EDITED_PREFIX.'%')->get() as $row) {
            $this->warn("  {$modelClass} #{$row->getKey()} is still named \"{$row->getAttribute($column)}\" — an interrupted crud-edit run left a REAL row renamed. NOT deleted; restore its original name manually.");
        }
    }
}
