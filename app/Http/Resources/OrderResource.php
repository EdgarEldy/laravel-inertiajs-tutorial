<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'total' => $this->total,
            // `->resolve()` is required on each nested resource - a bare
            // `new CustomerResource(...)`/`new ProductResource(...)` left
            // unresolved caused a real, silently-blank-field bug on
            // `feature/products` (`ProductResource`'s nested category).
            'customer' => $this->whenLoaded('customer', fn () => (new CustomerResource($this->customer))->resolve()),
            'product' => $this->whenLoaded('product', fn () => (new ProductResource($this->product))->resolve()),
            'created_at' => $this->created_at,
        ];
    }
}
