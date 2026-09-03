<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;

/**
 * Like `CategoryService`/`ProductService`/`CustomerService`, this is not
 * one of the RBAC services and does not use `LogsAuditEvents` - audit
 * logging in this project is scoped to RBAC mutations only. Orders are a
 * create-only resource (no update/delete route exists), so this service
 * only ever needs the one method.
 */
class OrderService
{
    /**
     * Loads the product referenced by `product_id` and computes `total`
     * from its actual current `unit_price` - the client never supplies
     * `total` directly, so a stale or manipulated price on the client side
     * can never affect what gets persisted.
     */
    public function placeOrder(array $data): Order
    {
        $product = Product::findOrFail($data['product_id']);

        return Order::create([
            'customer_id' => $data['customer_id'],
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
            'total' => $data['quantity'] * $product->unit_price,
        ]);
    }
}
