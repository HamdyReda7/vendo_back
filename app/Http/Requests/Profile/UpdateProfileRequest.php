<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'البريد الإلكتروني مستخدم بالفعل.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.unique' => 'رقم الهاتف مستخدم بالفعل.',
            'image.image' => 'يجب أن تكون الصورة ملف صورة صالحًا.',
            'image.mimes' => 'أنواع الصور المسموح بها: jpg, jpeg, png, webp.',
            'image.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت.',
        ];
    }
}
