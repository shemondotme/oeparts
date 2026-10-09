<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Events\OrderPlaced;
use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\OrderResource;
use App\Listeners\UpdateInventory;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\AdminOrderCalculator;
use App\Services\CouponService;
use App\Services\SequenceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_number'] = app(SequenceService::class)->nextOrderNumber();
        $data['ip_address'] = request()->ip();

        return OrderResource::normalizeBilling($data);
    }

    /**
     * Create the order, its items and (unless the admin chose to type totals by
     * hand) the totals themselves in one transaction. The totals are ALWAYS
     * recomputed here from the saved items: the figures in the browser are only
     * a live preview, never trusted.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $lines = (array) ($data['line_items'] ?? []);
        $manual = (bool) ($data['manual_totals'] ?? false);
        $sendConfirmation = (bool) ($data['send_confirmation'] ?? false);
        unset($data['line_items'], $data['manual_totals'], $data['send_confirmation'], $data['calc_note']);

        $coupon = null;

        if (! $manual) {
            $result = app(AdminOrderCalculator::class)->compute(
                $lines,
                $data['shipping_country_code'] ?? null,
                ! empty($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
                ! empty($data['coupon_id']) ? (int) $data['coupon_id'] : null,
                ! empty($data['user_id']) ? (int) $data['user_id'] : null,
                $data['guest_email'] ?? null,
                (bool) ($data['vat_exempt'] ?? false),
                (string) ($data['urgent_processing_fee'] ?? '0'),
            );

            if ($result['shipping_error'] !== null) {
                throw ValidationException::withMessages(['data.shipping_method_id' => $result['shipping_error']]);
            }

            foreach (['subtotal', 'discount_amount', 'shipping_cost', 'vat_amount', 'grand_total'] as $field) {
                $data[$field] = $result[$field];
            }

            // A coupon the calculator rejected must not stay attached to the order.
            if (! empty($data['coupon_id'])) {
                if (bccomp($result['discount_amount'], '0.00', 2) > 0) {
                    $coupon = Coupon::find($data['coupon_id']);
                } else {
                    $data['coupon_id'] = null;
                }
            }
        }

        // Same snapshot the storefront checkout takes, so the customer's emails and the invoice
        // can name the shipping method and its delivery estimate.
        if (! empty($data['shipping_method_id']) && ($shippingMethod = ShippingMethod::find($data['shipping_method_id']))) {
            $data['shipping_method_name_snapshot'] = trans_field($shippingMethod->name);
            $data['shipping_estimated_days_min'] = $shippingMethod->estimated_days_min;
            $data['shipping_estimated_days_max'] = $shippingMethod->estimated_days_max;
        }

        /** @var Order $order */
        $order = DB::transaction(function () use ($data, $lines): Order {
            $order = Order::create($data);

            foreach ($lines as $line) {
                $product = Product::with(['manufacturer', 'condition'])->find($line['product_id'] ?? null);

                if (! $product) {
                    continue;
                }

                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = bcadd(str_replace(',', '.', (string) ($line['unit_price'] ?? $product->price)), '0', 2);

                /** @var Manufacturer|null $manufacturer */
                $manufacturer = $product->manufacturer;
                /** @var Condition|null $condition */
                $condition = $product->condition;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'oem_number_snapshot' => $product->oem_number,
                    'manufacturer_snapshot' => $manufacturer
                        ? (trans_field($manufacturer->name) ?: 'Unknown')
                        : 'Unknown',
                    'condition_snapshot' => $condition ? $condition->slug : '',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => bcmul((string) $quantity, $unitPrice, 2),
                    'cost_price' => is_numeric($line['cost_price'] ?? null) ? number_format((float) $line['cost_price'], 2, '.', '') : null,
                ]);
            }

            return $order;
        });

        if ($coupon) {
            app(CouponService::class)->apply($coupon, $order);
        }

        // Each part is a single physical item: once sold it leaves the shop, exactly as
        // for a storefront order. The confirmation email is optional and sent with it.
        $order->load('items.product');
        if ($sendConfirmation) {
            OrderPlaced::dispatch($order);
        } else {
            app(UpdateInventory::class)->handle(new OrderPlaced($order));
        }

        return $order;
    }
}
