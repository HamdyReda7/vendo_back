<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'has_variants' => 'required|boolean',
            'quantity' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'variants' => 'nullable|array',
            'variants.*.color_id' => 'nullable|integer|exists:colors,id',
            'variants.*.size_id' => 'nullable|integer|exists:sizes,id',
            'variants.*.quantity' => 'required_with:variants|integer|min:0',
            'variants.*.status' => 'nullable|boolean',
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
            'description_ar.string' => 'الوصف بالعربي يجب أن يكون نصًا.',
            'description_en.string' => 'الوصف بالإنجليزي يجب أن يكون نصًا.',
            'price.required' => 'السعر مطلوب.',
            'price.numeric' => 'السعر يجب أن يكون رقمًا.',
            'price.min' => 'السعر يجب ألا يقل عن 0.',
            'old_price.numeric' => 'السعر القديم يجب أن يكون رقمًا.',
            'old_price.min' => 'السعر القديم يجب ألا يقل عن 0.',
            'has_variants.required' => 'حقل وجود متغيرات مطلوب.',
            'has_variants.boolean' => 'حقل وجود متغيرات يجب أن يكون صح أو خطأ.',
            'quantity.integer' => 'الكمية يجب أن تكون رقمًا صحيحًا.',
            'quantity.min' => 'الكمية يجب ألا تقل عن 0.',
            'status.boolean' => 'حالة المنتج يجب أن تكون صحيحة أو غير صحيحة.',
            'category_ids.required' => 'يجب اختيار قسم واحد على الأقل للمنتج.',
            'category_ids.array' => 'يجب أن تكون الأقسام مصفوفة صحيحة.',
            'category_ids.min' => 'يجب اختيار قسم واحد على الأقل للمنتج.',
            'category_ids.*.integer' => 'معرف القسم يجب أن يكون رقمًا صحيحًا.',
            'category_ids.*.exists' => 'القسم المحدد غير موجود.',
            'images.array' => 'يجب أن تكون الصور مصفوفة صحيحة.',
            'images.*.image' => 'يجب أن يكون الملف صورة بصيغة jpg أو jpeg أو png أو webp وبحجم لا يتجاوز 2 ميجابايت.',
            'images.*.mimes' => 'أنواع الصور المسموح بها: jpg, jpeg, png, webp.',
            'images.*.max' => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت.',
            'variants.array' => 'يجب أن تكون المتغيرات مصفوفة صحيحة.',
            'variants.*.color_id.exists' => 'اللون المحدد غير موجود.',
            'variants.*.size_id.exists' => 'المقاس المحدد غير موجود.',
            'variants.*.quantity.required' => 'كمية الـ Variant مطلوبة.',
            'variants.*.quantity.required_with' => 'كمية الـ Variant مطلوبة.',
            'variants.*.quantity.integer' => 'كمية الـ Variant يجب أن تكون رقمًا صحيحًا أكبر من أو يساوي صفر.',
            'variants.*.quantity.min' => 'كمية الـ Variant يجب أن تكون رقمًا صحيحًا أكبر من أو يساوي صفر.',
            'variants.*.status.boolean' => 'حالة الـ Variant يجب أن تكون قيمة صحيحة.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasVariants = $this->boolean('has_variants');
            $variants = $this->input('variants');

            if (!$hasVariants) {
                if ($this->has('variants') && !empty($variants)) {
                    $validator->errors()->add('variants', 'لا يمكن إضافة Variants لمنتج لا يدعم Variants.');
                }
            } else {
                if (empty($variants) || !is_array($variants)) {
                    $validator->errors()->add('variants', 'يجب إضافة Variant واحد على الأقل لهذا المنتج.');
                    return;
                }

                $seenCombos = [];

                foreach ($variants as $index => $variant) {
                    $colorId = !empty($variant['color_id']) ? $variant['color_id'] : null;
                    $sizeId = !empty($variant['size_id']) ? $variant['size_id'] : null;

                    if ($colorId === null && $sizeId === null) {
                        $validator->errors()->add("variants.{$index}", 'يجب أن يحتوي الـ Variant على لون أو مقاس واحد على الأقل.');
                    }

                    $comboKey = ($colorId ?? 'null') . '_' . ($sizeId ?? 'null');
                    if (isset($seenCombos[$comboKey])) {
                        $validator->errors()->add("variants.{$index}", 'لا يمكن تكرار نفس الـ Variant لهذا المنتج.');
                    } else {
                        $seenCombos[$comboKey] = true;
                    }
                }
            }
        });
    }
}
