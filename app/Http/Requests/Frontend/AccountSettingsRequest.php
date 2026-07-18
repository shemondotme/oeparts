<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'phone'      => 'nullable|string|max:30',
            'email'      => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => trans('account.validation_first_name_required'),
            'last_name.required'  => trans('account.validation_last_name_required'),
            'email.required'      => trans('account.validation_email_required'),
            'email.email'         => trans('account.validation_email_invalid'),
            'email.unique'        => trans('account.validation_email_unique'),
        ];
    }
}
