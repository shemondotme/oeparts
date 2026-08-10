<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function showLinkRequestForm(Request $request, string $lang)
    {
        return view('auth.passwords.email');
    }

    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request, string $lang)
    {
        $request->validate([
            'email' => 'required|email',
            'website' => 'max:0',
        ]);

        // Always the same generic message regardless of what Password::
        // broker()->sendResetLink() actually resolved to (RESET_LINK_SENT
        // vs. INVALID_USER) and via the same 'status' flash channel either
        // way. The view only ever renders session('status') — the other
        // branch's ->withErrors() had no on-page effect at all, so a real
        // account got a visible confirmation while a nonexistent one got
        // silence, itself already an account-enumeration signal distinct
        // from the message text.
        Password::broker('users')->sendResetLink($request->only('email'));

        return back()->with('status', trans(Password::RESET_LINK_SENT));
    }
}
