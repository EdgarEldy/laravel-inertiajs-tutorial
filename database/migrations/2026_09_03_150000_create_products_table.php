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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // `restrict`, not `cascade` - a product must never silently
            // disappear because its category was deleted. The application
            // layer (`CategoryService::deleteCategory()`) also rejects the
            // deletion before it ever reaches the database, but the
            // database constraint is kept as the real, non-bypassable
            // guarantee behind it.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            // Unique per category rather than globally: unlike
            // `Category`/`Role`/`Permission`'s single identifying field,
            // a product's natural scope is its own category (two different
            // categories can reasonably each have a "Small" product), but
            // the same category listing the same name twice is still a
            // real duplicate.
            $table->unique(['category_id', 'product_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
