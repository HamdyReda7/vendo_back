<?php

namespace App\Http\Requests\Admin\Size;

use Illuminate\Foundation\Http\FormRequest;

class StoreSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المقاس مطلوب.',
            'name.string' => 'اسم المقاس يجب أن يكون نصًا.',
            'name.max' => 'اسم المقاس يجب ألا يتجاوز 255 حرفًا.',
            'status.boolean' => 'حالة المقاس يجب أن تكون قيمة صحيحة.',
        ];
    }
}
