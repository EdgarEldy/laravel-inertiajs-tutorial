<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with ORDER:READ can view the orders index', function () {
    $user = $this->userWithPermissions([['ORDER', 'READ']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->get('/orders');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Orders/Index'));
});

test('a user without ORDER:READ is denied the orders index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/orders');

    $response->assertForbidden();
});

test('a guest is redirected away from the orders index', function () {
    $response = $this->get('/orders');

    $response->assertRedirect('/login');
});

test('the orders index props eager load the customer and product names, not just ids, and include the full customers/products lists for the create form', function () {
    $user = $this->userWithPermissions([['ORDER', 'READ']]);
    $customer = Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
    $product = Product::factory()->create(['product_name' => 'Laptop']);
    Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->get('/orders');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.customer_id', $customer->id)
        ->where('orders.data.0.product_id', $product->id)
        ->where('orders.data.0.customer.first_name', 'Jane')
        ->where('orders.data.0.customer.last_name', 'Doe')
        ->where('orders.data.0.product.product_name', 'Laptop')
        ->has('customers')
        ->has('products')
    );
});

test('a user with ORDER:WRITE can place an order and the total is computed correctly from quantity and the product unit_price', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['unit_price' => 19.99]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $response->assertRedirect(route('orders.index'));
    $order = Order::first();
    expect($order)->not->toBeNull();
    expect((float) $order->total)->toBe(59.97);
    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'total' => 59.97,
    ]);
});

test('the computed total ignores any total value the client attempts to submit', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['unit_price' => 10]);

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'total' => 999999,
    ]);

    $response->assertRedirect(route('orders.index'));
    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'total' => 20,
    ]);
    $this->assertDatabaseMissing('orders', ['total' => 999999]);
});

test('a user without ORDER:WRITE cannot place an order', function () {
    $user = $this->userWithPermissions([['ORDER', 'READ']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response->assertForbidden();
    expect(Order::count())->toBe(0);
});

test('a guest cannot place an order', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response->assertRedirect('/login');
    expect(Order::count())->toBe(0);
});

test('placing an order without a customer_id fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => '',
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('customer_id');
    expect(Order::count())->toBe(0);
});

test('placing an order without a product_id fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => '',
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('product_id');
    expect(Order::count())->toBe(0);
});

test('placing an order without a quantity fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => '',
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(Order::count())->toBe(0);
});

test('placing an order with a customer_id pointing at a nonexistent customer fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => 999999,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('customer_id');
    expect(Order::count())->toBe(0);
});

test('placing an order with a product_id pointing at a nonexistent product fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => 999999,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('product_id');
    expect(Order::count())->toBe(0);
});

test('placing an order with a quantity of 0 fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 0,
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(Order::count())->toBe(0);
});

test('placing an order with a negative quantity fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => -5,
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(Order::count())->toBe(0);
});

test('placing an order with a non-integer quantity fails validation', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->post('/orders', [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 'not-a-number',
    ]);

    $response->assertSessionHasErrors('quantity');
    expect(Order::count())->toBe(0);
});

test('there is no update route for orders', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->put("/orders/{$order->id}", [
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    $response->assertNotFound();
});

test('there is no delete route for orders', function () {
    $user = $this->userWithPermissions([['ORDER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->delete("/orders/{$order->id}");

    $response->assertNotFound();
    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

test('the same category also seeds and reuses category/product/customer eager loading correctly when an order references a category-backed product', function () {
    $user = $this->userWithPermissions([['ORDER', 'READ']]);
    $category = Category::factory()->create(['category_name' => 'Electronics']);
    $product = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Laptop']);
    $customer = Customer::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->get('/orders');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Orders/Index')
        ->where('orders.data.0.product.product_name', 'Laptop')
    );
});
