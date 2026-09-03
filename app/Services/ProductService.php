<?php

namespace App\Services;

use App\Models\Product;

/**
 * CRUD for products. Like `CategoryService`, this is not one of the RBAC
 * services and does not use `LogsAuditEvents` - audit logging in this
 * project is scoped to RBAC mutations only.
 */
class ProductService
{
    public function createProduct(array $data): Product
    {
        return Product::create([
            'category_id' => $data['category_id'],
            'product_name' => $data['product_name'],
            'unit_price' => $data['unit_price'],
        ]);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update([
            'category_id' => $data['category_id'],
            'product_name' => $data['product_name'],
            'unit_price' => $data['unit_price'],
        ]);

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $product->delete();
    }
}
