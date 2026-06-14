<?php

namespace Database\Seeders;

use App\Enums\ContactStatus;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PartInquiryStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\FailedSearchLog;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\PartInquiry;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Catalog verification: seed products and manufacturers if empty
        if (Product::count() === 0) {
            $this->command->info('No products found. Seeding catalog via DemoManufacturersAndPartsSeeder...');
            $this->call(DemoManufacturersAndPartsSeeder::class);
        }

        // 2. Idempotent guard: skip heavy transactional inserts if orders > 50
        if (Order::count() > 50) {
            $this->command->info('Order count is already > 50. Skipping heavy demo transactional inserts.');
            return;
        }

        $this->command->info('Populating high-fidelity dashboard demo data...');

        // 3. Ensure we have users
        if (User::count() < 40) {
            User::factory()->count(40)->create();
        }
        $users = User::all();
        $products = Product::all();

        if ($products->isEmpty() || $users->isEmpty()) {
            $this->command->error('Cannot seed without users or products. Ensure catalog seeder ran.');
            return;
        }

        // Ensure we have an admin for created_by
        $admin = Admin::first();
        if (!$admin) {
            $admin = Admin::create([
                'name' => 'System Admin',
                'email' => 'system@oeparts.test',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'is_active' => true,
            ]);
        }
        $adminId = $admin->id;

        // 4. Create Coupons
        $coupons = [
            Coupon::firstOrCreate(
                ['code' => 'WELCOME10'],
                [
                    'name' => 'Welcome Discount',
                    'discount_type' => DiscountType::Percentage,
                    'discount_value' => '10.00',
                    'min_order_amount' => '50.00',
                    'usage_limit' => 100,
                    'usage_limit_per_user' => 1,
                    'expires_at' => now()->addYear(),
                    'is_active' => true,
                    'created_by' => $adminId,
                ]
            ),
            Coupon::firstOrCreate(
                ['code' => 'B2B50OFF'],
                [
                    'name' => 'B2B Special Offer',
                    'discount_type' => DiscountType::Fixed,
                    'discount_value' => '50.00',
                    'min_order_amount' => '300.00',
                    'usage_limit' => 50,
                    'usage_limit_per_user' => 2,
                    'expires_at' => now()->addMonth(),
                    'is_active' => true,
                    'created_by' => $adminId,
                ]
            ),
        ];

        // 5. Create Orders & OrderItems spread over 90 days
        $statuses = [
            OrderStatus::Pending,
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
            OrderStatus::RefundRequested,
            OrderStatus::Refunded,
        ];
        $paymentMethods = [
            PaymentMethod::Card,
            PaymentMethod::BankTransfer,
        ];
        $countries = ['DE', 'FR', 'IT', 'LT', 'ES', 'PL', 'NL', 'BE', 'AT', 'SE'];

        $startDate = now()->subDays(90);

        for ($i = 0; $i < 65; $i++) {
            $createdAt = (clone $startDate)->addMinutes(rand(0, 90 * 24 * 60));
            $user = $users->random();
            $status = fake()->randomElement($statuses);
            $paymentMethod = fake()->randomElement($paymentMethods);

            // Align payment status with order status
            if (in_array($status, [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered], true)) {
                $paymentStatus = PaymentStatus::Paid;
            } elseif ($status === OrderStatus::Cancelled) {
                $paymentStatus = fake()->randomElement([PaymentStatus::Pending, PaymentStatus::Failed]);
            } elseif ($status === OrderStatus::Refunded) {
                $paymentStatus = PaymentStatus::Refunded;
            } else {
                $paymentStatus = PaymentStatus::Pending;
            }

            $subtotal = '0.00';
            $itemsCount = rand(1, 3);
            $itemsData = [];

            for ($j = 0; $j < $itemsCount; $j++) {
                $product = $products->random();
                $qty = rand(1, 2);
                $price = $product->price ?? '49.99';
                $itemTotal = bcmul((string) $price, (string) $qty, 2);
                $subtotal = bcadd($subtotal, $itemTotal, 2);

                $mfgName = 'Unknown';
                if ($product->manufacturer) {
                    $mfgName = is_array($product->manufacturer->name)
                        ? ($product->manufacturer->name['en'] ?? reset($product->manufacturer->name))
                        : $product->manufacturer->name;
                }

                $itemsData[] = [
                    'product_id' => $product->id,
                    'oem_number_snapshot' => $product->oem_number ?? strtoupper(fake()->bothify('??####??')),
                    'manufacturer_snapshot' => $mfgName,
                    'condition_snapshot' => $product->condition?->slug ?? 'new',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $itemTotal,
                ];
            }

            // Coupon discount
            $discountAmount = '0.00';
            $coupon = null;
            if (fake()->boolean(30)) {
                $coupon = fake()->randomElement($coupons);
                if (bccomp($subtotal, $coupon->min_order_amount, 2) >= 0) {
                    if ($coupon->discount_type === DiscountType::Percentage) {
                        $discountAmount = bcdiv(bcmul($subtotal, $coupon->discount_value, 4), '100.00', 2);
                    } else {
                        $discountAmount = $coupon->discount_value;
                    }
                }
            }

            // VAT (19% standard EU)
            $vatRate = '0.19';
            $discountedSubtotal = bcsub($subtotal, $discountAmount, 2);
            if (bccomp($discountedSubtotal, '0.00', 2) < 0) {
                $discountedSubtotal = '0.00';
            }
            $vatAmount = bcmul($discountedSubtotal, $vatRate, 2);
            $shippingCost = '15.00';

            // Grand Total
            $grandTotal = bcadd($discountedSubtotal, $shippingCost, 2);
            $grandTotal = bcadd($grandTotal, $vatAmount, 2);

            $order = Order::create([
                'order_number' => 'ORD-' . fake()->unique()->numberBetween(100000, 999999),
                'user_id' => $user->id,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_reference' => $paymentStatus === PaymentStatus::Paid ? 'pi_' . fake()->unique()->numerify('####################') : null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_cost' => $shippingCost,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
                'coupon_id' => $coupon?->id,
                'shipping_method_name_snapshot' => 'Standard Shipping',
                'shipping_estimated_days_min' => 3,
                'shipping_estimated_days_max' => 5,
                'shipping_name' => $user->name,
                'shipping_address_line1' => fake()->streetAddress(),
                'shipping_city' => fake()->city(),
                'shipping_postal_code' => fake()->postcode(),
                'shipping_country_code' => fake()->randomElement($countries),
                'is_b2b' => fake()->boolean(20),
                'ip_address' => fake()->ipv4(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            foreach ($itemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // Create refund request for RefundRequested / Refunded orders
            if ($status === OrderStatus::RefundRequested || $status === OrderStatus::Refunded) {
                RefundRequest::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'reason' => 'Customer requested cancel/return: ' . fake()->sentence(),
                    'amount_requested' => $grandTotal,
                    'status' => $status === OrderStatus::Refunded ? RefundStatus::Approved : RefundStatus::Pending,
                    'return_images' => [],
                    'created_at' => (clone $createdAt)->addHours(4),
                ]);
            }
        }

        // 6. Create Abandoned Carts
        for ($i = 0; $i < 8; $i++) {
            $user = fake()->boolean(60) ? $users->random() : null;
            $cart = Cart::create([
                'user_id' => $user?->id,
                'guest_token' => $user ? null : Str::random(40),
                'expires_at' => now()->addDays(7),
                'created_at' => now()->subHours(rand(3, 48)),
                'updated_at' => now()->subHours(rand(3, 48)),
            ]);

            $itemsCount = rand(1, 3);
            for ($j = 0; $j < $itemsCount; $j++) {
                $product = $products->random();
                $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => rand(1, 2),
                    'price_at_add' => $product->price ?? '99.00',
                ]);
            }
        }

        // 7. Create Search Logs & Failed Search Logs
        for ($i = 0; $i < 100; $i++) {
            $createdAt = (clone $startDate)->addMinutes(rand(0, 90 * 24 * 60));
            $query = strtoupper(fake()->bothify('??#####??'));
            $normalized = preg_replace('/[^A-Z0-9]/', '', $query);

            SearchLog::create([
                'search_query' => $query,
                'normalized_query' => $normalized,
                'result_count' => rand(1, 15),
                'lang' => fake()->randomElement(['en', 'de', 'lt']),
                'user_id' => fake()->boolean(40) ? $users->random()->id : null,
                'ip_address' => fake()->ipv4(),
                'created_at' => $createdAt,
            ]);
        }

        for ($i = 0; $i < 20; $i++) {
            $createdAt = (clone $startDate)->addMinutes(rand(0, 90 * 24 * 60));
            $query = strtoupper(fake()->bothify('??####XX'));
            $normalized = preg_replace('/[^A-Z0-9]/', '', $query);

            $log = FailedSearchLog::create([
                'search_query' => $query,
                'normalized_query' => $normalized,
                'lang' => fake()->randomElement(['en', 'de', 'lt']),
                'user_id' => fake()->boolean(40) ? $users->random()->id : null,
                'ip_address' => fake()->ipv4(),
                'inquiry_submitted' => fake()->boolean(40),
                'created_at' => $createdAt,
            ]);

            if ($log->inquiry_submitted) {
                PartInquiry::create([
                    'failed_search_log_id' => $log->id,
                    'email' => fake()->safeEmail(),
                    'phone' => fake()->phoneNumber(),
                    'oem_number' => $query,
                    'manufacturer' => fake()->randomElement(['Audi', 'BMW', 'Opel', 'Toyota']),
                    'car_model' => fake()->word() . ' ' . rand(2010, 2023),
                    'year' => rand(2010, 2023),
                    'quantity' => rand(1, 4),
                    'urgency' => fake()->randomElement(['normal', 'soon', 'urgent']),
                    'notes' => fake()->sentence(),
                    'status' => fake()->randomElement([PartInquiryStatus::New, PartInquiryStatus::Reviewing, PartInquiryStatus::Sourced]),
                    'ip_address' => $log->ip_address,
                    'created_at' => $createdAt->addMinutes(10),
                ]);
            }
        }

        // 8. Contact messages
        for ($i = 0; $i < 10; $i++) {
            ContactMessage::create([
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'subject' => 'Parts inquiry / wholesale: ' . fake()->word(),
                'message' => fake()->paragraph(),
                'status' => fake()->randomElement([ContactStatus::Unread, ContactStatus::Read, ContactStatus::Resolved]),
                'ip_address' => fake()->ipv4(),
                'created_at' => (clone $startDate)->addMinutes(rand(0, 90 * 24 * 60)),
            ]);
        }

        // 9. Newsletter subscribers
        for ($i = 0; $i < 30; $i++) {
            NewsletterSubscriber::create([
                'email' => fake()->unique()->safeEmail(),
                'lang' => fake()->randomElement(['en', 'de', 'fr']),
                'is_active' => fake()->boolean(90),
                'subscribed_at' => (clone $startDate)->addMinutes(rand(0, 90 * 24 * 60)),
                'ip_address' => fake()->ipv4(),
            ]);
        }

        // 10. Audit trail logs (ActivityLog)
        $admins = Admin::all();
        if ($admins->isNotEmpty()) {
            $actions = [
                'Product updated', 'Order marked as paid', 'Refund request approved',
                'Settings updated', 'B2B customer verified', 'Bulk price import completed'
            ];
            for ($i = 0; $i < 8; $i++) {
                ActivityLog::create([
                    'admin_id' => $admins->random()->id,
                    'action' => fake()->randomElement($actions),
                    'ip_address' => fake()->ipv4(),
                    'created_at' => now()->subMinutes(rand(5, 1200)),
                ]);
            }
        }

        $this->command->info('Seeding finished successfully.');
    }
}
