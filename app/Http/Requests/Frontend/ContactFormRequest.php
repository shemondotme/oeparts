<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:200',
            'subject_type' => 'required|in:general_inquiry,part_not_found,order_issue,shipping_question,return_refund,b2b_partnership,other',
            'order_number' => 'nullable|string|max:50',
            'oem_number' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'car_model' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:10',
            'vin_number' => 'nullable|string|max:50',
            'message' => 'required|string|min:10|max:5000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'name.required' => 'Please enter your name.',
            'subject_type.required' => 'Please select a subject.',
            'message.required' => 'Please enter your message.',
            'message.min' => 'Your message must be at least 10 characters.',
            'message.max' => 'Your message cannot exceed 5000 characters.',
        ];
    }
}
