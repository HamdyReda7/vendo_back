<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userImage = null;
        if ($this->user && $this->user->image) {
            $userImage = str_starts_with($this->user->image, 'http')
                ? $this->user->image
                : asset('img/users/' . $this->user->image);
        }

        $productImage = null;
        if ($this->product) {
            $firstImage = $this->product->relationLoaded('images')
                ? $this->product->images->first()
                : $this->product->images()->first();

            if ($firstImage && $firstImage->image) {
                $productImage = str_starts_with($firstImage->image, 'http')
                    ? $firstImage->image
                    : asset('img/products/' . $firstImage->image);
            }
        }

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'image' => $userImage,
            ] : null,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name_ar' => $this->product->name_ar,
                'name_en' => $this->product->name_en,
                'image' => $productImage,
                'product_image' => $productImage,
            ] : null,
        ];
    }
}
