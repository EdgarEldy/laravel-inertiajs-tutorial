<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'product_name' => $this->product_name,
            'unit_price' => $this->unit_price,
            'category' => $this->whenLoaded('category', fn () => (new CategoryResource($this->category))->resolve()),
            'created_at' => $this->created_at,
        ];
    }
}
