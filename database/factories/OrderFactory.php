<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);

        // Created eagerly (not the lazy Product::factory() other factories
        // use for a belongsTo) specifically so total below can be computed
        // from this product's own actual unit_price, keeping the same
        // quantity * unit_price invariant OrderService::placeOrder() itself
        // guarantees - a factory-generated order should never disagree with
        // the product it references.
        $product = Product::factory()->create();

        return [
            'customer_id' => Customer::factory(),
            'product_id' => $product->id,
            'quantity' => $quantity,
            'total' => $quantity * $product->unit_price,
        ];
    }
}
