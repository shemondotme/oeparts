<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendOrderStatusEmail;
use App\Jobs\SendTrackingUpdateEmail;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Display a paginated list of orders with filters.
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product.manufacturer'])
            ->latest('created_at');

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by order number
        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%' . $request->order_number . '%');
        }

        // Filter by customer email
        if ($request->filled('customer_email')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('email', 'like', '%' . $request->customer_email . '%');
                })->orWhere('guest_email', 'like', '%' . $request->customer_email . '%');
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by urgent processing (Task 9c)
        $query->when($request->boolean('urgent'), fn($q) => $q->where('urgent_processing', true));

        $orders = $query->paginate(25)->withQueryString();

        $statuses = OrderStatus::cases();
        $carriers = Carrier::where('is_active', true)->get();

        return view('admin.orders.index', compact('orders', 'statuses', 'carriers'));
    }

    /**
     * Display order details.
     */
    public function show(Order $order)
    {
        $order->load([
            'user',
            'items.product.manufacturer',
            'items.product.crossReferences',
            'statusHistory' => function ($query) {
                $query->latest();
            },
            'notes' => function ($query) {
                $query->latest();
            },
            'refundRequest' => function ($query) {
                $query->latest();
            },
            'payment',
        ]);

        $statuses = OrderStatus::cases();
        $carriers = Carrier::where('is_active', true)->get();

        return view('admin.orders.show', compact('order', 'statuses', 'carriers'));
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:50'],
            'notify_customer' => ['boolean'],
        ]);

        $oldStatus = $order->status;
        $newStatus = OrderStatus::from($validated['status']);

        // Update order
        $order->status = $newStatus;
        if (isset($validated['tracking_number'])) {
            $order->tracking_number = $validated['tracking_number'];
        }
        if (isset($validated['carrier'])) {
            $order->carrier = $validated['carrier'];
        }
        $order->save();

        // Log status change
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'admin_id' => auth('admin')->id(),
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'note' => $validated['note'] ?? null,
        ]);

        // Add note if provided
        if (!empty($validated['note'])) {
            OrderNote::create([
                'order_id' => $order->id,
                'admin_id' => auth('admin')->id(),
                'note' => $validated['note'],
            ]);
        }

        // Send email notification if requested
        if ($request->boolean('notify_customer') && $order->user_id) {
            dispatch(new SendOrderStatusEmail($order, $oldStatus, $newStatus));
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('Order status updated successfully.'));
    }

    /**
     * Add internal note to order.
     */
    public function addNote(Request $request, Order $order)
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        OrderNote::create([
            'order_id' => $order->id,
            'admin_id' => auth('admin')->id(),
            'note' => $validated['note'],
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('Note added successfully.'));
    }

    /**
     * Update tracking information.
     */
    public function updateTracking(Request $request, Order $order)
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'carrier' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'notify_customer' => ['boolean'],
        ]);

        $oldTracking = $order->tracking_number;
        $oldCarrier = $order->carrier;

        $order->tracking_number = $validated['tracking_number'];
        $order->carrier = $validated['carrier'];
        $order->save();

        // Add note
        if (!empty($validated['note'])) {
            OrderNote::create([
                'order_id' => $order->id,
                'admin_id' => auth('admin')->id(),
                'note' => 'Tracking updated: ' . $validated['note'],
            ]);
        }

        // Send email notification if requested
        if ($request->boolean('notify_customer') && $order->user_id) {
            dispatch(new SendTrackingUpdateEmail($order));
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('Tracking information updated successfully.'));
    }

    /**
     * Export orders to CSV.
     */
    public function export(Request $request)
    {
        $query = Order::with(['user', 'items'])
            ->latest('created_at');

        // Apply filters same as index
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->get();

        $csvData = [];
        $csvData[] = [
            'Order Number',
            'Date',
            'Customer Email',
            'Status',
            'Payment Method',
            'Payment Status',
            'Subtotal',
            'Shipping',
            'VAT',
            'Total',
            'Shipping Name',
            'Shipping Address',
            'Shipping City',
            'Shipping Country',
            'Tracking Number',
            'Carrier',
        ];

        foreach ($orders as $order) {
            $customerEmail = $order->user_id ? $order->user->email : $order->guest_email;
            $csvData[] = [
                $order->order_number,
                $order->created_at->format('Y-m-d H:i:s'),
                $customerEmail,
                $order->status->label(),
                $order->payment_method->value,
                $order->payment_status->value,
                $order->subtotal,
                $order->shipping_cost,
                $order->vat_amount,
                $order->grand_total,
                $order->shipping_name,
                $order->shipping_address_line1,
                $order->shipping_city,
                $order->shipping_country_code,
                $order->tracking_number ?? '',
                $order->carrier ?? '',
            ];
        }

        $filename = 'orders_' . date('Y-m-d_H-i') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkStatus(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'status'      => ['required', \Illuminate\Validation\Rule::in(
                array_column(\App\Enums\OrderStatus::cases(), 'value')
            )],
        ]);

        $newStatus = \App\Enums\OrderStatus::from($validated['status']);
        $admin     = auth('admin')->user();
        $count     = 0;

        foreach (\App\Models\Order::whereIn('id', $validated['order_ids'])->get() as $order) {
            $oldStatus = $order->status;  // capture BEFORE update
            $order->update(['status' => $newStatus]);

            \App\Models\OrderStatusHistory::create([
                'order_id'   => $order->id,
                'admin_id'   => $admin->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => 'Bulk status update.',
            ]);

            $count++;
        }

        return redirect()->route('admin.orders.index')
            ->with('success', "{$count} order(s) updated to {$newStatus->label()}.");
    }

    public function packingSlip(\App\Models\Order $order): \Illuminate\View\View
    {
        $order->load(['items', 'shippingMethod']);
        return view('admin.orders.packing-slip', compact('order'));
    }
}