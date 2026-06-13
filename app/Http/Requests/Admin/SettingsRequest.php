<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:50'],
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string|max:10000'],
        ];
    }
}
