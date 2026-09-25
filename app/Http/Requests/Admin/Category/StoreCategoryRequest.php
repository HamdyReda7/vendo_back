<?php

namespace App\Http\Requests\Admin\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name_ar.required' => 'الاسم بالعربي مطلوب.',
            'name_ar.string' => 'الاسم بالعربي يجب أن يكون نصًا.',
            'name_ar.max' => 'الاسم بالعربي يجب ألا يتجاوز 255 حرفًا.',
            'name_en.required' => 'الاسم بالإنجليزي مطلوب.',
            'name_en.string' => 'الاسم بالإنجليزي يجب أن يكون نصًا.',
            'name_en.max' => 'الاسم بالإنجليزي يجب ألا يتجاوز 255 حرفًا.',
            'image.image' => 'يجب أن يكون الملف صورة صحيحة.',
            'image.mimes' => 'أنواع الصور المسموح بها: jpg, jpeg, png, webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت.',
            'status.boolean' => 'حالة القسم يجب أن تكون صحيحة أو غير صحيحة.',
        ];
    }
}
