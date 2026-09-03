<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

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

    /**
     * Rejects deletion if the product still has any orders - same
     * referential-integrity pattern as
     * `CategoryService::deleteCategory()`: a `ValidationException`
     * surfaces as a redirect-back field error instead of letting the
     * database's own `restrictOnDelete()` foreign key raise a raw query
     * exception up to the user.
     */
    public function deleteProduct(Product $product): void
    {
        if ($product->orders()->exists()) {
            throw ValidationException::withMessages([
                'product' => 'This product still has orders and cannot be deleted.',
            ]);
        }

        $product->delete();
    }
}
