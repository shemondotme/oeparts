<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class ProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:150'],
            'comment' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'website' => ['max:0'], // honeypot — same convention as PartInquiryRequest
        ];
    }

    public function messages(): array
    {
        return [
            'reviewer_name.required' => trans('search.review_validation_name_required'),
            'comment.required' => trans('search.review_validation_comment_required'),
            'rating.required' => trans('search.review_validation_rating_required'),
            'rating.between' => trans('search.review_validation_rating_required'),
        ];
    }
}
