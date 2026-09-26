<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'required|string|max:1000',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'التقييم مطلوب.',
            'rating.integer' => 'التقييم يجب أن يكون رقمًا صحيحًا.',
            'rating.between' => 'التقييم يجب أن يكون بين 1 و 5 نجوم.',
            'comment.required' => 'التعليق مطلوب.',
            'comment.string' => 'التعليق يجب أن يكون نصًا.',
            'comment.max' => 'التعليق يجب ألا يتجاوز 1000 حرف.',
        ];
    }
}
