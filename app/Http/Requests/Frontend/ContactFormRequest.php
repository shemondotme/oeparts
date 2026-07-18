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
            'website' => 'max:0', // honeypot
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => trans('contact.validation_email_required'),
            'email.email' => trans('contact.validation_email_invalid'),
            'name.required' => trans('contact.validation_name_required'),
            'subject_type.required' => trans('contact.validation_subject_required'),
            'message.required' => trans('contact.validation_message_required'),
            'message.min' => trans('contact.validation_message_min'),
            'message.max' => trans('contact.validation_message_max'),
        ];
    }
}
