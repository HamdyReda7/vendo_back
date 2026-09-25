<?php

namespace App\Http\Requests\Admin\Color;

use Illuminate\Foundation\Http\FormRequest;

class StoreColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required' => 'اسم اللون باللغة العربية مطلوب.',
            'name_ar.string' => 'اسم اللون باللغة العربية يجب أن يكون نصًا.',
            'name_ar.max' => 'اسم اللون باللغة العربية يجب ألا يتجاوز 255 حرفًا.',
            'name_en.required' => 'اسم اللون باللغة الإنجليزية مطلوب.',
            'name_en.string' => 'اسم اللون باللغة الإنجليزية يجب أن يكون نصًا.',
            'name_en.max' => 'اسم اللون باللغة الإنجليزية يجب ألا يتجاوز 255 حرفًا.',
            'status.boolean' => 'حالة اللون يجب أن تكون قيمة صحيحة.',
        ];
    }
}
