<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.product_variant_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'governorate' => 'required|string',
            'address' => 'required|string',
            'delivery_phone' => 'nullable|string',
            'note' => 'nullable|string',
            'shipping' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'عناصر الطلب مطلوبة.',
            'items.array' => 'عناصر الطلب يجب أن تكون مصفوفة.',
            'items.min' => 'يجب إضافة عنصر واحد على الأقل للطلب.',
            'items.*.product_id.required' => 'المنتج مطلوب.',
            'items.*.product_id.integer' => 'معرف المنتج يجب أن يكون رقمًا صحيحًا.',
            'items.*.product_variant_id.integer' => 'معرف خيار المنتج يجب أن يكون رقمًا صحيحًا.',
            'items.*.quantity.required' => 'الكمية مطلوبة.',
            'items.*.quantity.integer' => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'items.*.quantity.min' => 'الكمية يجب أن تكون أكبر من صفر.',
            'governorate.required' => 'المحافظة مطلوبة.',
            'governorate.string' => 'المحافظة يجب أن تكون نصًا.',
            'address.required' => 'العنوان مطلوب.',
            'address.string' => 'العنوان يجب أن يكون نصًا.',
            'delivery_phone.string' => 'رقم هاتف التوصيل يجب أن يكون نصًا.',
            'note.string' => 'الملاحظة يجب أن تكون نصًا.',
            'shipping.numeric' => 'قيمة الشحن يجب أن تكون رقمًا صحيحًا.',
            'shipping.min' => 'قيمة الشحن يجب ألا تقل عن صفر.',
        ];
    }
}
