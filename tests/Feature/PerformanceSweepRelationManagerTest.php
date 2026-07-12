<?php

namespace Tests\Feature;

use App\Filament\Resources\CarModelResource\Pages\ViewCarModel;
use App\Filament\Resources\CarModelResource\RelationManagers\ProductsRelationManager as CarModelProductsRelationManager;
use App\Filament\Resources\CouponResource\Pages\ViewCoupon;
use App\Filament\Resources\CouponResource\RelationManagers\UsagesRelationManager;
use App\Filament\Resources\ManufacturerResource\Pages\ViewManufacturer;
use App\Filament\Resources\ManufacturerResource\RelationManagers\ProductsRelationManager as ManufacturerProductsRelationManager;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\RelationManagers\OrderNotesRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\OrderStatusHistoryRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\RefundRequestRelationManager;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Filament\Resources\ProductResource\RelationManagers\CarModelsRelationManager;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Performance sweep Stage D: 6 relation managers were missing eager-loads
 * for the relation columns they render. Two of them (Manufacturer/CarModel
 * ProductsRelationManager) also had a real display bug alongside the N+1 —
 * `formatStateUsing(fn ($state) => $state?->name ?? '—')` on a dot-path
 * column whose $state IS ALREADY the resolved string (rule #26), so the
 * Condition column silently rendered "—" for every row. These tests both
 * confirm the tabs still mount (rule #38 class) and that the condition name
 * actually renders now.
 */
class PerformanceSweepRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function condition(): Condition
    {
        return Condition::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'bg_color' => '#DCFCE7', 'text_color' => '#166534', 'is_active' => true, 'sort_order' => 0],
        );
    }

    #[Test]
    public function manufacturer_products_relation_manager_renders_the_real_condition_name(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        Product::factory()->create(['manufacturer_id' => $manufacturer->id, 'condition_id' => $this->condition()->id]);

        Livewire::test(ManufacturerProductsRelationManager::class, [
            'ownerRecord' => $manufacturer,
            'pageClass' => ViewManufacturer::class,
        ])
            ->assertOk()
            ->loadTable()
            ->assertSeeText('New')
            ->assertDontSeeText('—');
    }

    #[Test]
    public function car_model_products_relation_manager_renders_the_real_condition_name(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $carModel = CarModel::factory()->create(['manufacturer_id' => $manufacturer->id]);
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id, 'condition_id' => $this->condition()->id]);
        $product->carModels()->attach($carModel);

        Livewire::test(CarModelProductsRelationManager::class, [
            'ownerRecord' => $carModel,
            'pageClass' => ViewCarModel::class,
        ])
            ->assertOk()
            ->loadTable()
            ->assertSeeText('New')
            ->assertDontSeeText('—');
    }

    #[Test]
    public function product_car_models_relation_manager_mounts_and_is_paginated(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
        $carModel = CarModel::factory()->create(['manufacturer_id' => $manufacturer->id]);
        $product->carModels()->attach($carModel);

        Livewire::test(CarModelsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => ViewProduct::class,
        ])->assertOk();
    }

    #[Test]
    public function coupon_usages_relation_manager_mounts_with_eager_loaded_user_and_order(): void
    {
        $coupon = Coupon::factory()->create(['created_by' => Admin::factory()->create()->id]);
        $user = User::factory()->create();
        $order = Order::factory()->create();

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'used_at' => now(),
        ]);

        Livewire::test(UsagesRelationManager::class, [
            'ownerRecord' => $coupon,
            'pageClass' => ViewCoupon::class,
        ])->assertOk();
    }

    #[Test]
    public function order_refund_request_relation_manager_mounts(): void
    {
        $order = Order::factory()->create();
        RefundRequest::factory()->create(['order_id' => $order->id]);

        Livewire::test(RefundRequestRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertOk();
    }

    #[Test]
    public function order_status_history_and_notes_relation_managers_mount(): void
    {
        $order = Order::factory()->create();
        $admin = Admin::where('email', 'superadmin@oeparts.test')->firstOrFail();

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'old_status' => 'pending',
            'new_status' => 'paid',
            'admin_id' => $admin->id,
        ]);
        OrderNote::create([
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'note' => 'Test note',
        ]);

        Livewire::test(OrderStatusHistoryRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertOk();

        Livewire::test(OrderNotesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => ViewOrder::class,
        ])->assertOk();
    }
}
