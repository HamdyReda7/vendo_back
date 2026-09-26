<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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

        return [
            'id' => $this->id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'image' => $userImage,
            ] : null,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
        ];
    }
}
