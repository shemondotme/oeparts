<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('record')?->id;

        return [
            'oem_number' => ['required', 'string', 'max:100'],
            'normalized_oem' => ['nullable', 'string', 'max:100'],
            'manufacturer_id' => ['required', 'exists:manufacturers,id'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'condition' => ['required', 'string'],
            'is_in_stock' => ['boolean'],
            'is_active' => ['boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
