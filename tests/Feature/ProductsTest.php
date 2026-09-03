<?php

use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with PRODUCT:READ can view the products index', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $category = Category::factory()->create(['category_name' => 'Electronics']);
    Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Laptop']);

    $response = $this->actingAs($user)->get('/products');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Products/Index'));
});

test('a user without PRODUCT:READ is denied the products index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/products');

    $response->assertForbidden();
});

test('a guest is redirected away from the products index', function () {
    $response = $this->get('/products');

    $response->assertRedirect('/login');
});

test('the products index props include the paginated products (eager loaded with category) and the full categories list for the filter dropdown', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $electronics = Category::factory()->create(['category_name' => 'Electronics']);
    $furniture = Category::factory()->create(['category_name' => 'Furniture']);
    Product::factory()->create(['category_id' => $electronics->id, 'product_name' => 'Laptop']);

    $response = $this->actingAs($user)->get('/products');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Index')
        ->has('products.data', 1)
        ->where('products.data.0.product_name', 'Laptop')
        ->where('products.data.0.category.category_name', 'Electronics')
        ->has('categories', 2)
        ->where('filters.search', '')
        ->where('filters.category', null)
    );
});

test('the products index search filters by product_name', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Laptop']);
    Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Desk']);

    $response = $this->actingAs($user)->get('/products?search=Lap');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Index')
        ->has('products.data', 1)
        ->where('products.data.0.product_name', 'Laptop')
        ->where('filters.search', 'Lap')
    );
});

test('the products index filters by category via ?category=id', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $electronics = Category::factory()->create(['category_name' => 'Electronics']);
    $furniture = Category::factory()->create(['category_name' => 'Furniture']);
    Product::factory()->create(['category_id' => $electronics->id, 'product_name' => 'Laptop']);
    Product::factory()->create(['category_id' => $furniture->id, 'product_name' => 'Desk']);

    $response = $this->actingAs($user)->get("/products?category={$electronics->id}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Products/Index')
        ->has('products.data', 1)
        ->where('products.data.0.product_name', 'Laptop')
        ->where('filters.category', $electronics->id)
    );
});

test('a user with PRODUCT:WRITE can create a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => 999.99,
    ]);

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => 999.99,
    ]);
});

test('creating a product without a category_id fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $productsBefore = Product::count();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => '',
        'product_name' => 'Laptop',
        'unit_price' => 999.99,
    ]);

    $response->assertSessionHasErrors('category_id');
    expect(Product::count())->toBe($productsBefore);
});

test('creating a product with a category_id pointing at a nonexistent category fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => 999999,
        'product_name' => 'Laptop',
        'unit_price' => 999.99,
    ]);

    $response->assertSessionHasErrors('category_id');
    $this->assertDatabaseMissing('products', ['product_name' => 'Laptop']);
});

test('creating a product without a product_name fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => '',
        'unit_price' => 999.99,
    ]);

    $response->assertSessionHasErrors('product_name');
});

test('creating a product without a unit_price fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => '',
    ]);

    $response->assertSessionHasErrors('unit_price');
});

test('creating a product with a negative unit_price fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => -5,
    ]);

    $response->assertSessionHasErrors('unit_price');
});

test('creating a product with a product_name that already exists in the same category fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'product_name' => 'Laptop']);

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => 500,
    ]);

    $response->assertSessionHasErrors('product_name');
    expect(Product::where('category_id', $category->id)->count())->toBe(1);
});

test('the same product_name is allowed across two different categories', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $electronics = Category::factory()->create();
    $furniture = Category::factory()->create();
    Product::factory()->create(['category_id' => $electronics->id, 'product_name' => 'Small']);

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $furniture->id,
        'product_name' => 'Small',
        'unit_price' => 50,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', ['category_id' => $furniture->id, 'product_name' => 'Small']);
});

test('a user without PRODUCT:WRITE cannot create a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $category = Category::factory()->create();

    $response = $this->actingAs($user)->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Laptop',
        'unit_price' => 999.99,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('products', ['product_name' => 'Laptop']);
});

test('a user with PRODUCT:WRITE can update a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'product_name' => 'OLD_NAME',
        'unit_price' => 10,
    ]);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => $category->id,
        'product_name' => 'NEW_NAME',
        'unit_price' => 20,
    ]);

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'product_name' => 'NEW_NAME',
        'unit_price' => 20,
    ]);
});

test('updating a product to a category_id pointing at a nonexistent category fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => 999999,
        'product_name' => $product->product_name,
        'unit_price' => $product->unit_price,
    ]);

    $response->assertSessionHasErrors('category_id');
});

test('updating a product to a product_name already used by another product in the same category fails validation', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'product_name' => 'TAKEN']);
    $product = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'ORIGINAL']);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => $category->id,
        'product_name' => 'TAKEN',
        'unit_price' => $product->unit_price,
    ]);

    $response->assertSessionHasErrors('product_name');
    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'ORIGINAL']);
});

test('updating a product to its own current product_name (same category) does not fail the unique rule', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'SAME_NAME']);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => $category->id,
        'product_name' => 'SAME_NAME',
        'unit_price' => $product->unit_price,
    ]);

    $response->assertSessionHasNoErrors();
});

test('updating a product with the same product_name as another category\'s product does not fail the unique rule', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $electronics = Category::factory()->create();
    $furniture = Category::factory()->create();
    Product::factory()->create(['category_id' => $furniture->id, 'product_name' => 'Small']);
    $product = Product::factory()->create(['category_id' => $electronics->id, 'product_name' => 'Big']);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => $electronics->id,
        'product_name' => 'Small',
        'unit_price' => $product->unit_price,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'Small']);
});

test('a user without PRODUCT:WRITE cannot update a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/products/{$product->id}", [
        'category_id' => $category->id,
        'product_name' => 'NEW_NAME',
        'unit_price' => 20,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'OLD_NAME']);
});

test('a user with PRODUCT:WRITE can delete a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'WRITE']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($user)->delete("/products/{$product->id}");

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('a user without PRODUCT:WRITE cannot delete a product', function () {
    $user = $this->userWithPermissions([['PRODUCT', 'READ']]);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($user)->delete("/products/{$product->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

test('a guest cannot create, update, or delete a product', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'product_name' => 'UNUSED']);

    $this->post('/products', [
        'category_id' => $category->id,
        'product_name' => 'Furniture',
        'unit_price' => 10,
    ])->assertRedirect('/login');
    $this->put("/products/{$product->id}", [
        'category_id' => $category->id,
        'product_name' => 'NEW_NAME',
        'unit_price' => 10,
    ])->assertRedirect('/login');
    $this->delete("/products/{$product->id}")->assertRedirect('/login');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => 'UNUSED']);
});
