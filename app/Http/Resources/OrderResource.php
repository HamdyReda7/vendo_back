<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userData = $this->user ? [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ] : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'user' => $userData,
            'customer' => $userData,
            'subtotal' => (float) $this->subtotal,
            'shipping' => $this->shipping !== null ? (float) $this->shipping : 0.0,
            'total' => (float) $this->total,
            'governorate' => $this->governorate,
            'address' => $this->address,
            'delivery_phone' => $this->delivery_phone,
            'note' => $this->note,
            'status' => $this->status,
            'items' => OrderItemResource::collection($this->relationLoaded('orderItems') ? $this->orderItems : $this->orderItems()->get()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
