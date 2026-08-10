<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Matches AuthController::register()/ResetPasswordController's use of
        // the same setting — this form previously hardcoded 8, silently
        // ignoring an operator-raised minimum (e.g. 12) for every OTHER
        // password entry point.
        $pwMin = (int) settings('auth.customer_password_min', 8);

        return [
            'current_password' => 'required|string',
            'new_password'     => ['required', 'string', 'confirmed', Password::min($pwMin)->mixedCase()->numbers()->symbols()->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => trans('account.validation_current_password_required'),
            'new_password.required'     => trans('account.validation_new_password_required'),
            'new_password.confirmed'    => trans('account.validation_new_password_confirmed'),
        ];
    }
}
