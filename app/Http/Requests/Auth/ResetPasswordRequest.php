<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_password') && !$this->has('password')) {
            $this->merge([
                'password' => $this->new_password,
                'password_confirmation' => $this->new_password_confirmation,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.digits' => 'يجب أن يتكون رمز التحقق من 6 أرقام.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ];
    }
}
