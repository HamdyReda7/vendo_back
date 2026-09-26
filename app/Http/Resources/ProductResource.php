<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $discountPercentage = null;

        if ($this->old_price !== null && (float) $this->old_price > 0) {
            if ((float) $this->old_price <= (float) $this->price) {
                $discountPercentage = '0%';
            } else {
                $discountPercentage = round(
                    (((float) $this->old_price - (float) $this->price) / (float) $this->old_price) * 100
                ) . '%';
            }
        }
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'price' => (float) $this->price,
            'old_price' => $this->old_price !== null ? (float) $this->old_price : null,
            'discount_percentage' => $discountPercentage,
            'has_variants' => (bool) $this->has_variants,
            'quantity' => $this->quantity !== null ? (int) $this->quantity : null,
            'status' => (bool) $this->status,
            'categories' => CategoryResource::collection($this->relationLoaded('categories') ? $this->categories : $this->categories()->get()),
            'images' => ProductImageResource::collection($this->relationLoaded('images') ? $this->images : $this->images()->get()),
            'variants' => $this->has_variants
                ? ProductVariantResource::collection($this->relationLoaded('variants') ? $this->variants : $this->variants()->with(['color', 'size'])->get())
                : [],
            'reviews' => ReviewResource::collection(
                $this->relationLoaded('reviews')
                    ? $this->reviews->where('status', true)->sortByDesc('created_at')->values()
                    : []
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
