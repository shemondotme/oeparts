<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->route('step') ?? request('step', 1);

        return match ((int) $step) {
            1 => [
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:50',
                'otp'   => 'nullable|string',
            ],
            2 => [
                'shipping_name'         => 'required|string|max:200',
                'shipping_address_line1'=> 'required|string|max:255',
                'shipping_city'         => 'required|string|max:100',
                'shipping_postal_code'  => 'required|string|max:20',
                'shipping_country_code' => 'required|string|size:2',
            ],
            3 => [
                'shipping_method_id' => 'required|exists:shipping_methods,id',
            ],
            4 => [
                'payment_method' => 'required|in:card,bank_transfer',
                'company_name'   => 'nullable|string|max:200',
                'vat_number'     => 'nullable|string|max:50',
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'email.required'                => 'Please enter your email address.',
            'email.email'                   => 'Please enter a valid email address.',
            'shipping_name.required'        => 'Please enter your full name.',
            'shipping_address_line1.required' => 'Please enter your street address.',
            'shipping_city.required'        => 'Please enter your city.',
            'shipping_postal_code.required' => 'Please enter your postal code.',
            'shipping_country_code.required'=> 'Please select your country.',
            'shipping_method_id.required'   => 'Please select a shipping method.',
            'payment_method.required'       => 'Please select a payment method.',
        ];
    }
}
