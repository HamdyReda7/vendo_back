<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'color_id' => $this->color_id,
            'size_id' => $this->size_id,
            'quantity' => (int) $this->quantity,
            'status' => (bool) $this->status,
            'color' => $this->color ? [
                'id' => $this->color->id,
                'name_ar' => $this->color->name_ar,
                'name_en' => $this->color->name_en,
            ] : null,
            'size' => $this->size ? [
                'id' => $this->size->id,
                'name' => $this->size->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
