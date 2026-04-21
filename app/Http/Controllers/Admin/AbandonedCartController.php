<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbandonedCartController extends Controller
{
    /**
     * Display abandoned carts.
     */
    public function index(Request $request)
    {
        $query = AbandonedCart::with(['user'])
            ->orderBy('last_active_at', 'desc');

        if ($request->filled('email')) {
            $query->where('guest_email', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'recovered') {
                $query->where('recovery_email_sent', true);
            } else {
                $query->where('recovery_email_sent', false);
            }
        }

        $carts = $query->paginate(20);

        return view('admin.marketing.abandoned-carts', compact('carts'));
    }

    /**
     * Export abandoned carts to CSV.
     */
    public function export(Request $request)
    {
        $query = AbandonedCart::with(['user']);

        if ($request->filled('email')) {
            $query->where('guest_email', 'like', '%' . $request->email . '%');
        }

        $carts = $query->get();

        $csv = "ID,Email,User,Last Active,Recovery Sent,Cart Total\n";

        foreach ($carts as $cart) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $cart->id,
                $cart->guest_email ?? $cart->user?->email ?? 'N/A',
                $cart->user?->name ?? 'Guest',
                $cart->last_active_at->format('Y-m-d H:i'),
                $cart->recovery_email_sent ? 'Yes' : 'No',
                $cart->cart_snapshot['total'] ?? '0.00'
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="abandoned-carts-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Manually send recovery email for an abandoned cart.
     */
    public function sendRecovery(AbandonedCart $cart)
    {
        if ($cart->recovery_email_sent) {
            return back()->with('error', 'Recovery email already sent for this cart.');
        }

        $email = $cart->guest_email ?? $cart->user?->email;

        if (!$email) {
            return back()->with('error', 'No email address available for this cart.');
        }

        // Dispatch recovery email job
        dispatch(new \App\Jobs\SendAbandonedCartEmail($email, $cart->cart_snapshot));

        $cart->update(['recovery_email_sent' => true]);

        return back()->with('success', 'Recovery email sent successfully.');
    }
}
