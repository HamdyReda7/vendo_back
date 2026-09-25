<?php

namespace App\Http\Requests\Admin\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_ar' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|required|string|max:255',
            'description_ar' => 'sometimes|nullable|string',
            'description_en' => 'sometimes|nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'old_price' => 'sometimes|nullable|numeric|min:0',
            'has_variants' => 'sometimes|required|boolean',
            'quantity' => 'sometimes|nullable|integer|min:0',
            'status' => 'sometimes|nullable|boolean',
            'category_ids' => 'sometimes|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'delete_image_ids' => 'sometimes|nullable|array',
            'delete_image_ids.*' => 'integer',
            'variants' => 'sometimes|nullable|array',
            'variants.*.id' => 'nullable|integer',
            'variants.*.color_id' => 'nullable|integer|exists:colors,id',
            'variants.*.size_id' => 'nullable|integer|exists:sizes,id',
            'variants.*.quantity' => 'required_with:variants|integer|min:0',
            'variants.*.status' => 'nullable|boolean',
            'delete_variant_ids' => 'sometimes|nullable|array',
            'delete_variant_ids.*' => 'integer',
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
            'delete_image_ids.array' => 'يجب أن تكون معرفات الصور المراد حذفها مصفوفة صحيحة.',
            'delete_image_ids.*.integer' => 'معرف الصورة يجب أن يكون رقمًا صحيحًا.',
            'variants.array' => 'يجب أن تكون المتغيرات مصفوفة صحيحة.',
            'variants.*.color_id.exists' => 'اللون المحدد غير موجود.',
            'variants.*.size_id.exists' => 'المقاس المحدد غير موجود.',
            'variants.*.quantity.required' => 'كمية الـ Variant مطلوبة.',
            'variants.*.quantity.required_with' => 'كمية الـ Variant مطلوبة.',
            'variants.*.quantity.integer' => 'كمية الـ Variant يجب أن تكون رقمًا صحيحًا أكبر من أو يساوي صفر.',
            'variants.*.quantity.min' => 'كمية الـ Variant يجب أن تكون رقمًا صحيحًا أكبر من أو يساوي صفر.',
            'variants.*.status.boolean' => 'حالة الـ Variant يجب أن تكون قيمة صحيحة.',
            'delete_variant_ids.array' => 'يجب أن تكون معرفات المتغيرات المراد حذفها مصفوفة صحيحة.',
            'delete_variant_ids.*.integer' => 'معرف الـ Variant يجب أن يكون رقمًا صحيحًا.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $productId = $this->route('id');
            $product = $productId ? Product::find($productId) : null;

            $hasVariants = $this->has('has_variants')
                ? $this->boolean('has_variants')
                : ($product ? (bool) $product->has_variants : false);

            $variants = $this->input('variants');

            if (!$hasVariants) {
                if ($this->has('variants') && !empty($variants)) {
                    $validator->errors()->add('variants', 'لا يمكن إضافة Variants لمنتج لا يدعم Variants.');
                }
            } else {
                // If product was false and is being switched to true
                if ($product && !$product->has_variants && $this->has('has_variants') && $this->boolean('has_variants')) {
                    if (empty($variants) || !is_array($variants)) {
                        $validator->errors()->add('variants', 'يجب إضافة Variant واحد على الأقل لهذا المنتج.');
                        return;
                    }
                }

                if (is_array($variants)) {
                    $seenCombos = [];

                    foreach ($variants as $index => $variant) {
                        $variantId = $variant['id'] ?? null;
                        $existingVariant = null;

                        if ($variantId) {
                            $existingVariant = ProductVariant::find($variantId);
                            if (!$existingVariant || ($product && $existingVariant->product_id != $product->id)) {
                                $validator->errors()->add("variants.{$index}", 'الـ Variant لا ينتمي إلى هذا المنتج.');
                                continue;
                            }
                        }

                        $colorId = array_key_exists('color_id', $variant)
                            ? (!empty($variant['color_id']) ? $variant['color_id'] : null)
                            : ($existingVariant ? $existingVariant->color_id : null);

                        $sizeId = array_key_exists('size_id', $variant)
                            ? (!empty($variant['size_id']) ? $variant['size_id'] : null)
                            : ($existingVariant ? $existingVariant->size_id : null);

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
            }
        });
    }
}
