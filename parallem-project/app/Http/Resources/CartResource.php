<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total_price' => (string) $this->total_price,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'updated_at' => $this->updated_at,
        ];
    }
}

