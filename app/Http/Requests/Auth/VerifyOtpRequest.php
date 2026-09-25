<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'email' => 'required|email',

            'otp' => 'required|digits:6',

        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'otp.required' => 'رمز التحقق مطلوب.',
            'otp.digits' => 'يجب أن يتكون رمز التحقق من 6 أرقام.',
        ];
    }
}
