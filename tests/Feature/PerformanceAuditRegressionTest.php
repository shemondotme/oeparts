<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Resources\MenuResource\RelationManagers\MenuItemRelationManager;
use App\Jobs\GenerateInvoicePdf;
use App\Jobs\ProcessAirwallexWebhook;
use App\Jobs\RunBackupJob;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendOtpEmail;
use App\Jobs\SendRefundProcessedEmail;
use App\Models\Admin;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PerformanceAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
            \Database\Seeders\SequencesSeeder::class,
            \Database\Seeders\CarriersSeeder::class,
            \Database\Seeders\SectionsSeeder::class,
        ]);

        $this->admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');
    }

    private function makeMenuWithItems(Menu $menu, int $childCount): void
    {
        $parent = MenuItem::create([
            'menu_id'    => $menu->id,
            'label'      => ['en' => 'Parent'],
            'type'       => 'url',
            'url'        => '/parent',
            'sort_order' => 0,
            'target'     => '_self',
        ]);

        for ($i = 0; $i < $childCount; $i++) {
            MenuItem::create([
                'menu_id'    => $menu->id,
                'parent_id'  => $parent->id,
                'label'      => ['en' => "Child {$i}"],
                'type'       => 'url',
                'url'        => "/child-{$i}",
                'sort_order' => $i + 1,
                'target'     => '_self',
            ]);
        }
    }

    #[Test]
    public function menu_item_relation_manager_does_not_n_plus_one_on_parent(): void
    {
        $menuA = Menu::create(['name' => 'Menu A', 'location' => 'header', 'lang' => 'en', 'is_active' => true]);
        $this->makeMenuWithItems($menuA, 2);

        $menuB = Menu::create(['name' => 'Menu B', 'location' => 'footer', 'lang' => 'en', 'is_active' => true]);
        $this->makeMenuWithItems($menuB, 8);

        // Warm up one-time framework/permission-cache queries (locale, settings,
        // roles) outside the measured window so they don't confound the count.
        Livewire::test(MenuItemRelationManager::class, [
            'ownerRecord' => $menuA,
            'pageClass'   => EditMenu::class,
        ]);

        DB::enableQueryLog();
        Livewire::test(MenuItemRelationManager::class, [
            'ownerRecord' => $menuA,
            'pageClass'   => EditMenu::class,
        ]);
        $queryCountSmall = count(DB::getQueryLog());
        DB::flushQueryLog();

        Livewire::test(MenuItemRelationManager::class, [
            'ownerRecord' => $menuB,
            'pageClass'   => EditMenu::class,
        ]);
        $queryCountLarge = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queryCountSmall,
            $queryCountLarge,
            "Query count scaled with row count (3 items: {$queryCountSmall} queries, 9 items: {$queryCountLarge} queries) — parent relation is not eager-loaded."
        );
    }

    #[Test]
    public function customer_last_order_column_shows_the_correct_date(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(3),
        ]);

        Livewire::test(ListCustomers::class)
            ->assertTableColumnStateSet(
                'last_order_date',
                $order->created_at->diffForHumans(),
                record: $user->refresh(),
            );
    }

    #[Test]
    public function redis_queue_retry_after_exceeds_the_longest_job_timeout(): void
    {
        $retryAfter = config('queue.connections.redis.retry_after');

        $this->assertSame(3700, $retryAfter);
        $this->assertGreaterThan((new RunBackupJob())->timeout, $retryAfter);
    }

    #[Test]
    public function critical_jobs_have_explicit_retry_and_backoff(): void
    {
        foreach ([
            SendOrderConfirmationEmail::class,
            SendOtpEmail::class,
            GenerateInvoicePdf::class,
            SendRefundProcessedEmail::class,
        ] as $jobClass) {
            $reflection = new \ReflectionClass($jobClass);

            $this->assertTrue($reflection->hasProperty('tries'), "{$jobClass} is missing \$tries");
            $this->assertTrue($reflection->hasProperty('backoff'), "{$jobClass} is missing \$backoff");

            $defaults = $reflection->getDefaultProperties();

            $this->assertSame(3, $defaults['tries'], "{$jobClass}::\$tries mismatch");
            $this->assertSame([60, 180, 600], $defaults['backoff'], "{$jobClass}::\$backoff mismatch");
        }
    }

    #[Test]
    public function airwallex_webhook_job_has_no_dead_backoff_property(): void
    {
        $reflection = new \ReflectionClass(ProcessAirwallexWebhook::class);

        $this->assertFalse(
            $reflection->hasProperty('backoff'),
            'ProcessAirwallexWebhook still has a $backoff property — it is shadowed by its own backoff() method and should be removed.'
        );
        $this->assertTrue($reflection->hasMethod('backoff'));
    }
}
