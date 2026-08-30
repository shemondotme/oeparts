<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\CarModel;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Faq;
use App\Models\IpBlocklist;
use App\Models\Language;
use App\Models\LanguageString;
use App\Models\Manufacturer;
use App\Models\Menu;
use App\Models\NewsletterCampaign;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Section;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Outputs the lowest (oldest, presumably-real-seed-data) existing id for
 * each resource crud-edit.spec.js exercises, as JSON.
 *
 * That file used to hardcode a specific numeric id per resource ("queried
 * fresh via tinker right before writing this file") on the theory that
 * these ids were "real rows this dev DB already has" and therefore stable.
 * Confirmed live during a frontend/UX audit that theory was wrong for
 * nearly half of them (CarModel, Category, Language, Manufacturer,
 * BlogPost, Faq, Page, Section, Testimonial, Product all pointed at rows
 * that no longer existed) — ids drift the moment ANY other test deletes a
 * row or an admin cleans up test data, which is exactly what happened
 * here. Resolving the lowest current id at run time instead means the
 * test always targets a real row without needing to hardcode which one.
 */
class ResolveE2eEditTargets extends Command
{
    protected $signature = 'oeparts:e2e:resolve-edit-targets';

    protected $description = 'Output the lowest existing id per resource used by crud-edit.spec.js, as JSON';

    public function handle(): int
    {
        $models = [
            'Admin' => Admin::class,
            'CarModel' => CarModel::class,
            'Carrier' => Carrier::class,
            'Category' => Category::class,
            'Condition' => Condition::class,
            'Coupon' => Coupon::class,
            'Customer' => User::class,
            'IpBlocklist' => IpBlocklist::class,
            'Language' => Language::class,
            'Manufacturer' => Manufacturer::class,
            'BlogPost' => BlogPost::class,
            'Faq' => Faq::class,
            'Menu' => Menu::class,
            'Page' => Page::class,
            'Section' => Section::class,
            'Testimonial' => Testimonial::class,
            'NewsletterCampaign' => NewsletterCampaign::class,
            'Redirect' => Redirect::class,
            'Role' => Role::class,
            'Product' => Product::class,
            'ShippingMethod' => ShippingMethod::class,
            'ShippingZone' => ShippingZone::class,
            'TaxRate' => TaxRate::class,
            'Translation' => LanguageString::class,
        ];

        $ids = [];
        foreach ($models as $name => $class) {
            $query = $class::orderBy('id');

            // RolePolicy::update() deliberately makes the "super_admin" role
            // immutable — it's the Gate::before trust anchor for every admin
            // permission check, so renaming it would break admin access
            // panel-wide. Its row is very likely id 1 (auto-incrementing,
            // seeded first), which is also the lowest id — never resolve to
            // it here; pick the next role instead so this command can't hand
            // the edit suite a protected record to begin with.
            if ($name === 'Role') {
                $query->where('name', '!=', 'super_admin');
            }

            $ids[$name] = $query->value('id');
        }

        $this->output->write(json_encode($ids));

        return self::SUCCESS;
    }
}
