<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Route middleware (`can:ORDER:WRITE`) is the actual authorization
     * boundary for this request - no permission check is re-implemented
     * here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No `total` rule here - it is never accepted as client input, it is
     * computed server-side in `OrderService::placeOrder()` from the
     * product's actual current `unit_price`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
