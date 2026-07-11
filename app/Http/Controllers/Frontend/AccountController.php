<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserAddress;
use App\Services\InvoiceService;
use App\Enums\OrderStatus;
use App\Models\RefundRequest;
use App\Jobs\SendRefundStatusEmail;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Show the account dashboard.
     */
    public function cancelOrder(Request $request, string $lang, Order $order)
    {
        $user = Auth::guard('web')->user();
        if ($order->user_id !== $user->id) {
            abort(404);
        }
        if (!$order->status->canBeCancelled()) {
            return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
                ->with('error', __('account.cancel_order_not_cancellable'));
        }

        // Operator-configured grace period (OrdersSettings). 0 = status-only,
        // no time limit — the knob previously existed but was never checked.
        $windowHours = (int) settings('orders.customer_cancel_window_hours', 0);
        if ($windowHours > 0 && $order->created_at->addHours($windowHours)->isPast()) {
            return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
                ->with('error', __('account.cancel_window_passed', ['hours' => $windowHours]));
        }

        app(OrderService::class)->transitionStatus($order, OrderStatus::Cancelled, 'Cancelled by customer.');

        return redirect()->route('frontend.account.orders', ['lang' => $lang])
            ->with('success', __('account.order_cancelled_success'));
    }

    public function dashboard(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();
        $recentOrders = Order::where('user_id', $user->id)
            ->withCount('items') // dashboard renders the item count only
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('frontend.account.dashboard', compact('user', 'recentOrders'));
    }

    /**
     * List all orders.
     */
    public function orders(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();
        $orders = Order::where('user_id', $user->id)
            ->withCount('items') // list renders the item count only
            ->orderBy('created_at', 'desc')
            ->paginate(settings('general.pagination_per_page', 10));

        return view('frontend.account.orders', compact('orders'));
    }

    /**
     * Show a single order.
     */
    public function orderDetail(Request $request, string $lang, Order $order)
    {
        $user = Auth::guard('web')->user();
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        // The item ledger renders each line's product name; eager-load to avoid
        // an N+1 (OEM/manufacturer/condition are snapshot columns, not relations).
        $order->load('items.product');

        return view('frontend.account.order-detail', compact('order'));
    }

    /**
     * Download invoice PDF for an order.
     */
    public function downloadInvoice(Request $request, string $lang, Order $order)
    {
        $user = Auth::guard('web')->user();
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        return app(InvoiceService::class)->generate($order, true);
    }

    /**
     * List user addresses.
     */
    public function addresses(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();
        $addresses = $user->addresses()->orderBy('is_default', 'desc')->get();

        return view('frontend.account.addresses', compact('addresses'));
    }

    /**
     * Show form to create/edit address.
     */
    public function addressForm(Request $request, string $lang, ?UserAddress $address = null)
    {
        $user = Auth::guard('web')->user();
        if ($address && $address->user_id !== $user->id) {
            abort(404);
        }

        return view('frontend.account.address-form', compact('address'));
    }

    /**
     * Save address.
     */
    public function saveAddress(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $validated = $request->validate([
            'id'               => 'nullable|exists:user_addresses,id,user_id,' . $user->id,
            'first_name'       => 'required|string|max:100',
            'last_name'        => 'required|string|max:100',
            'company'          => 'nullable|string|max:200',
            'address_line_1'   => 'required|string|max:200',
            'address_line_2'   => 'nullable|string|max:200',
            'city'             => 'required|string|max:100',
            'state'            => 'required|string|max:100',
            'postal_code'      => 'required|string|max:20',
            'country_code'     => 'required|string|size:2',
            'phone'            => 'nullable|string|max:30',
            'is_default'       => 'boolean',
        ]);

        if (isset($validated['id'])) {
            $address = UserAddress::where('user_id', $user->id)->findOrFail($validated['id']);
            $address->update($validated);
        } else {
            $address = new UserAddress($validated);
            $address->user_id = $user->id;
            $address->save();
        }

        // If this address is set as default, unset others
        if ($address->is_default) {
            UserAddress::where('user_id', $user->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('frontend.account.addresses', ['lang' => $lang])
            ->with('success', __('account.address_saved'));
    }

    /**
     * Delete address.
     */
    public function deleteAddress(Request $request, string $lang, UserAddress $address)
    {
        $user = Auth::guard('web')->user();
        if ($address->user_id !== $user->id) {
            abort(404);
        }

        $address->delete();

        return redirect()->route('frontend.account.addresses', ['lang' => $lang])
            ->with('success', __('account.address_deleted'));
    }

    /**
     * Show account settings.
     */
    public function settings(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();
        return view('frontend.account.settings', compact('user'));
    }

    /**
     * Update account settings.
     */
    public function updateSettings(\App\Http\Requests\Frontend\AccountSettingsRequest $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $validated = $request->validated();

        // Update basic info
        $user->name = $validated['name'];
        $user->phone = $validated['phone'];
        $user->email = $validated['email'];

        // Change password if provided
        if (!empty($validated['current_password']) && !empty($validated['new_password'])) {
            if (!\Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => __('account.current_password_incorrect')]);
            }
            $user->password = \Hash::make($validated['new_password']);
        }

        $user->save();

        return redirect()->route('frontend.account.settings', ['lang' => $lang])
            ->with('success', __('account.settings_updated'));
    }

    /**
     * Update password only (separate form from profile fields).
     */
    public function updatePassword(\App\Http\Requests\Frontend\AccountPasswordRequest $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $validated = $request->validated();

        if (! \Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => __('account.current_password_incorrect')]);
        }

        $user->password = \Hash::make($validated['new_password']);
        $user->save();

        return redirect()->route('frontend.account.settings', ['lang' => $lang])
            ->with('success', __('account.password_updated'));
    }

    /**
     * Update notification toggles.
     */
    public function updateNotifications(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $request->validate([
            'notifications' => 'nullable|array',
        ]);

        $user->prefers_order_notifications = $request->boolean('notifications.order_updates');
        $user->prefers_email_notifications = $request->boolean('notifications.email_notifications');
        $user->prefers_promotional_emails = $request->boolean('notifications.promotional_emails');
        $user->save();

        return redirect()->route('frontend.account.settings', ['lang' => $lang])
            ->with('success', __('account.notification_prefs_updated'));
    }

    /**
     * Update preferred language and timezone.
     */
    public function updateLanguage(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $validated = $request->validate([
            'language' => 'required|string|in:en,de,lt,fr,es',
            'timezone' => 'nullable|string|max:100',
        ]);

        $user->preferred_locale = $validated['language'];
        $user->timezone = $validated['timezone'] ?? null;
        $user->save();

        return redirect()->route('frontend.account.settings', ['lang' => $validated['language']])
            ->with('success', __('account.language_prefs_updated'));
    }

    /**
     * Soft-delete account and anonymize PII (GDPR-style).
     */
    public function destroy(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();

        $user->email = 'deleted_'.$user->id.'@oeparts.invalid';
        $user->name = 'Deleted User';
        $user->phone = null;
        $user->save();
        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('frontend.home', ['lang' => $lang])
            ->with('success', __('account.account_deleted'));
    }

    /**
     * Show refund request form.
     */
    public function refundForm(Request $request, string $lang, Order $order)
    {
        $user = Auth::guard('web')->user();
        if ($order->user_id !== $user->id) {
            abort(404);
        }
        if ($order->status !== OrderStatus::Delivered) {
            return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
                ->with('error', __('account.refund_only_delivered'));
        }
        // orders.* is the group OrdersSettings manages — this read previously
        // used a 'refund' group no page edited, so the knob did nothing.
        $windowDays = settings('orders.refund_window_days', 14);
        if (Carbon::parse($order->updated_at)->diffInDays(now()) > $windowDays) {
            return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
                ->with('error', __('account.refund_window_passed', ['days' => $windowDays]));
        }
        if ($order->refundRequest()->exists()) {
            return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
                ->with('error', __('account.refund_already_submitted'));
        }
        return view('frontend.account.refund-form', compact('order'));
    }

    /**
     * Submit refund request.
     */
    public function requestRefund(Request $request, string $lang, Order $order)
    {
        $user = Auth::guard('web')->user();
        if ($order->user_id !== $user->id) {
            abort(404);
        }
        if ($order->status !== OrderStatus::Delivered) {
            abort(403);
        }
        // orders.* is the group OrdersSettings manages — this read previously
        // used a 'refund' group no page edited, so the knob did nothing.
        $windowDays = settings('orders.refund_window_days', 14);
        if (Carbon::parse($order->updated_at)->diffInDays(now()) > $windowDays) {
            abort(403);
        }
        if ($order->refundRequest()->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'reason'        => 'required|string|min:20|max:2000',
            'return_images' => 'nullable|array|max:5',
            'return_images.*' => 'image|mimes:jpeg,png|max:2048',
            'website'       => 'size:0',  // honeypot
        ]);

        // Store uploaded images
        $imagePaths = [];
        if ($request->hasFile('return_images')) {
            foreach ($request->file('return_images') as $file) {
                $imagePaths[] = $file->store('refund-images', 'public');
            }
        }

        app(OrderService::class)->transitionStatus(
            $order,
            OrderStatus::RefundRequested,
            'Customer submitted refund request.',
            null,
            notifyCustomer: false,
        );

        $refund = RefundRequest::create([
            'order_id'         => $order->id,
            'user_id'          => $user->id,
            'reason'           => $validated['reason'],
            'return_images'    => $imagePaths ?: null,
            'amount_requested' => $order->grand_total,
            'status'           => \App\Enums\RefundStatus::Pending,
        ]);

        dispatch(new SendRefundStatusEmail(
            $refund,
            \App\Enums\RefundStatus::Pending,
            \App\Enums\RefundStatus::Pending
        ))->onQueue('critical');

        return redirect()->route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])
            ->with('success', __('account.refund_submitted_success'));
    }

    /**
     * Show all refund requests for the authenticated user.
     */
    public function refunds(Request $request, string $lang)
    {
        $user = Auth::guard('web')->user();
        $refunds = RefundRequest::whereHas('order', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->with('order')
        ->orderByDesc('created_at')
        ->paginate(settings('general.pagination_per_page', 10));

        return view('frontend.account.refunds', compact('refunds'));
    }
}