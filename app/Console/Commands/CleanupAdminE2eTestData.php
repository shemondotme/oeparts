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
 */
class CleanupAdminE2eTestData extends Command
{
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
        $roles = Role::where('name', 'like', 'e2e_role_%')
            ->orWhere('name', 'like', 'E2E %')
            ->where('name', '!=', 'super_admin')
            ->get();
        if ($roles->isNotEmpty()) {
            $roles->each(fn ($role) => $role->delete());
            $this->line('  '.Role::class.': deleted '.$roles->count());
            $deleted += $roles->count();
        }

        $this->info("Deleted {$deleted} leaked E2E test row(s).");

        return self::SUCCESS;
    }

    private function sweep(string $modelClass, string $column): int
    {
        $rows = $modelClass::where($column, 'like', 'E2E %')->get();
        $count = $rows->count();

        if ($count > 0) {
            $rows->each(fn ($row) => $row->delete());
            $this->line("  {$modelClass}: deleted {$count}");
        }

        return $count;
    }
}
