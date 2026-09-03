<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // `restrict`, not `cascade` - same reasoning as
            // `products.category_id`: an order must never silently lose
            // the customer/product it references. `CustomerService::deleteCustomer()`
            // and `ProductService::deleteProduct()` also reject the
            // deletion at the application layer before it ever reaches the
            // database, but the database constraint remains the real,
            // non-bypassable guarantee behind it.
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // Same precision as `products.unit_price` - `total` is
            // `quantity * unit_price`, computed server-side in
            // `OrderService::placeOrder()`, never accepted as client input.
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
