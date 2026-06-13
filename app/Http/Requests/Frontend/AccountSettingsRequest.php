<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user('web');

        return [
            'name'             => 'required|string|max:200',
            'phone'            => 'nullable|string|max:30',
            'email'            => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'nullable|string|min:8',
            'new_password'     => ['nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Please enter your name.',
            'email.required'   => 'Please enter your email address.',
            'email.email'      => 'Please enter a valid email address.',
            'email.unique'     => 'This email is already in use by another account.',
        ];
    }
}
