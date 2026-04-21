<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendRefundProcessedEmail;
use App\Jobs\SendRefundStatusEmail;
use App\Models\OrderStatusHistory;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    /**
     * Display a paginated list of refund requests with filters.
     */
    public function index(Request $request)
    {
        $query = RefundRequest::with(['order', 'user'])
            ->latest('created_at');

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by order number
        if ($request->filled('order_number')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->order_number . '%');
            });
        }

        // Filter by customer email
        if ($request->filled('customer_email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', 'like', '%' . $request->customer_email . '%');
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $refunds = $query->paginate(25)->withQueryString();
        $statuses = RefundStatus::cases();

        return view('admin.refunds.index', compact('refunds', 'statuses'));
    }

    /**
     * Display refund request details.
     */
    public function show(RefundRequest $refund)
    {
        $refund->load(['order.items.product.manufacturer', 'user']);

        $statuses = RefundStatus::cases();

        return view('admin.refunds.show', compact('refund', 'statuses'));
    }

    /**
     * Update refund request status.
     */
    public function updateStatus(Request $request, RefundRequest $refund)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(RefundStatus::class)],
            'admin_note' => ['required', 'string', 'max:1000'],
            'notify_customer' => ['boolean'],
        ]);

        $oldStatus = $refund->status;
        $newStatus = RefundStatus::from($validated['status']);

        // Update refund request
        $refund->status = $newStatus;
        $refund->admin_note = $validated['admin_note'];
        if ($newStatus === RefundStatus::Processed) {
            $refund->processed_at = now();
        }
        $refund->save();

        // Update order status if needed
        $order = $refund->order;
        if ($newStatus === RefundStatus::Approved) {
            $oldOrderStatus = $order->status;
            $order->status = \App\Enums\OrderStatus::RefundRequested;
            $order->save();

            // Log order status change
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'admin_id' => auth('admin')->id(),
                'old_status' => $oldOrderStatus->value,
                'new_status' => \App\Enums\OrderStatus::RefundRequested->value,
                'note' => 'Refund request approved',
            ]);
        }

        // Send email notification if requested
        if ($request->boolean('notify_customer') && $refund->user_id) {
            dispatch(new SendRefundStatusEmail($refund, $oldStatus, $newStatus));
        }

        return redirect()
            ->route('admin.refunds.show', $refund)
            ->with('success', __('Refund request status updated successfully.'));
    }

    /**
     * Process refund (mark as processed and update order).
     */
    public function process(Request $request, RefundRequest $refund)
    {
        // Ensure refund is approved before processing
        if ($refund->status !== RefundStatus::Approved) {
            return redirect()
                ->route('admin.refunds.show', $refund)
                ->with('error', __('Refund must be approved before processing.'));
        }

        $validated = $request->validate([
            'amount'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processed_note' => ['required', 'string', 'max:500'],
            'notify_customer' => ['boolean'],
        ]);

        // If no amount was entered, fall back to order grand total
        $amount = isset($validated['amount']) && $validated['amount'] !== null
            ? number_format((float) $validated['amount'], 2, '.', '')
            : number_format((float) $refund->order->grand_total, 2, '.', '');

        $refund->amount_requested = $amount;
        $refund->status = RefundStatus::Processed;
        $refund->admin_note = $refund->admin_note . "\n\nProcessed: " . $validated['processed_note'];
        $refund->processed_at = now();
        $refund->save();

        // Update order status to Refunded
        $order = $refund->order;
        $oldOrderStatus = $order->status;
        $order->status = \App\Enums\OrderStatus::Refunded;
        $order->save();

        // Log order status change
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'admin_id' => auth('admin')->id(),
            'old_status' => $oldOrderStatus->value,
            'new_status' => \App\Enums\OrderStatus::Refunded->value,
            'note' => 'Refund processed: ' . $validated['processed_note'],
        ]);

        // Send email notification if requested
        if ($request->boolean('notify_customer') && $refund->user_id) {
            dispatch(new SendRefundProcessedEmail($refund));
        }

        return redirect()
            ->route('admin.refunds.show', $refund)
            ->with('success', __('Refund processed successfully.'));
    }

    /**
     * Export refund requests to CSV.
     */
    public function export(Request $request)
    {
        $query = RefundRequest::with(['order', 'user'])
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

        $refunds = $query->get();

        $csvData = [];
        $csvData[] = [
            'Refund ID',
            'Date',
            'Order Number',
            'Customer Email',
            'Reason',
            'Amount Requested',
            'Status',
            'Admin Note',
            'Processed At',
        ];

        foreach ($refunds as $refund) {
            $csvData[] = [
                $refund->id,
                $refund->created_at->format('Y-m-d H:i:s'),
                $refund->order->order_number,
                $refund->user->email ?? 'N/A',
                $refund->reason,
                $refund->amount_requested,
                $refund->status->value,
                $refund->admin_note ?? '',
                $refund->processed_at ? $refund->processed_at->format('Y-m-d H:i:s') : '',
            ];
        }

        $filename = 'refunds_' . date('Y-m-d_H-i') . '.csv';
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
}