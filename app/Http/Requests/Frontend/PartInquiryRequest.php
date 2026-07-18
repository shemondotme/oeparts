<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class PartInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'oem_number'   => ['required', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'car_model'    => ['nullable', 'string', 'max:100'],
            'year'         => ['nullable', 'string', 'max:10', 'regex:/^\d{4}$/'],
            'vin_number'   => ['nullable', 'string', 'max:50'],
            'quantity'     => ['nullable', 'integer', 'min:1', 'max:99'],
            'urgency'      => ['nullable', 'string', 'in:normal,soon,urgent'],
            'notes'        => ['nullable', 'string', 'max:500'],
            'failed_search_log_id' => ['nullable', 'integer', 'exists:failed_search_logs,id'],
            'website'      => ['max:0'], // honeypot
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'      => trans('part_inquiry.validation_email_required'),
            'email.email'         => trans('part_inquiry.validation_email_invalid'),
            'oem_number.required' => trans('part_inquiry.validation_oem_required'),
            'year.regex'          => trans('part_inquiry.validation_year_invalid'),
            'urgency.in'          => trans('part_inquiry.validation_urgency_invalid'),
        ];
    }
}
