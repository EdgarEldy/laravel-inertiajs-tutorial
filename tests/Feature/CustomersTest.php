<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with CUSTOMER:READ can view the customers index', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->actingAs($user)->get('/customers');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Customers/Index'));
});

test('a user without CUSTOMER:READ is denied the customers index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/customers');

    $response->assertForbidden();
});

test('a guest is redirected away from the customers index', function () {
    $response = $this->get('/customers');

    $response->assertRedirect('/login');
});

test('the customers index search filters by first_name', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);
    Customer::factory()->create(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john@example.com']);

    $response = $this->actingAs($user)->get('/customers?search=Jane');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Customers/Index')
        ->has('customers.data', 1)
        ->where('customers.data.0.first_name', 'Jane')
        ->where('filters.search', 'Jane')
    );
});

test('the customers index search filters by last_name', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);
    Customer::factory()->create(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john@example.com']);

    $response = $this->actingAs($user)->get('/customers?search=Smith');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Customers/Index')
        ->has('customers.data', 1)
        ->where('customers.data.0.last_name', 'Smith')
    );
});

test('the customers index search filters by email', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    Customer::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);
    Customer::factory()->create(['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john@example.com']);

    $response = $this->actingAs($user)->get('/customers?search=john@example.com');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Customers/Index')
        ->has('customers.data', 1)
        ->where('customers.data.0.email', 'john@example.com')
    );
});

test('a user with CUSTOMER:WRITE can create a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);
});

test('a user without CUSTOMER:WRITE cannot create a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('customers', ['email' => 'jane@example.com']);
});

test('a guest cannot create a customer', function () {
    $response = $this->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertRedirect('/login');
    $this->assertDatabaseMissing('customers', ['email' => 'jane@example.com']);
});

test('creating a customer without a first_name fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => '',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('first_name');
});

test('creating a customer without a last_name fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => '',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('last_name');
});

test('creating a customer without a telephone fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('telephone');
});

test('creating a customer without an address fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '',
    ]);

    $response->assertSessionHasErrors('address');
});

test('creating a customer without an email fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => '',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('email');
});

test('creating a customer with an invalid email format fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'not-an-email',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('customers', ['first_name' => 'Jane', 'last_name' => 'Doe']);
});

test('creating a customer with an email that already exists fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    Customer::factory()->create(['email' => 'taken@example.com']);

    $response = $this->actingAs($user)->post('/customers', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'taken@example.com',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Customer::where('email', 'taken@example.com')->count())->toBe(1);
});

test('a user with CUSTOMER:WRITE can update a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'jane@example.com',
        'address' => '123 Main St',
    ]);

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => 'Janet',
        'last_name' => 'Doeson',
        'telephone' => '555-0199',
        'email' => 'janet@example.com',
        'address' => '456 Oak Ave',
    ]);

    $response->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'first_name' => 'Janet',
        'last_name' => 'Doeson',
        'telephone' => '555-0199',
        'email' => 'janet@example.com',
        'address' => '456 Oak Ave',
    ]);
});

test('a user without CUSTOMER:WRITE cannot update a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    $customer = Customer::factory()->create(['first_name' => 'Jane']);

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => 'Janet',
        'last_name' => 'Doeson',
        'telephone' => '555-0199',
        'email' => 'janet@example.com',
        'address' => '456 Oak Ave',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'Jane']);
});

test('a guest cannot update a customer', function () {
    $customer = Customer::factory()->create(['first_name' => 'Jane']);

    $response = $this->put("/customers/{$customer->id}", [
        'first_name' => 'Janet',
        'last_name' => 'Doeson',
        'telephone' => '555-0199',
        'email' => 'janet@example.com',
        'address' => '456 Oak Ave',
    ]);

    $response->assertRedirect('/login');
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'Jane']);
});

test('updating a customer without a first_name fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => '',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => $customer->email,
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('first_name');
});

test('updating a customer with an invalid email format fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'telephone' => '555-0100',
        'email' => 'not-an-email',
        'address' => '123 Main St',
    ]);

    $response->assertSessionHasErrors('email');
});

test('updating a customer to an email already used by another customer fails validation', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    Customer::factory()->create(['email' => 'taken@example.com']);
    $customer = Customer::factory()->create(['email' => 'original@example.com']);

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => $customer->first_name,
        'last_name' => $customer->last_name,
        'telephone' => $customer->telephone,
        'email' => 'taken@example.com',
        'address' => $customer->address,
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'email' => 'original@example.com']);
});

test('updating a customer while keeping its own unchanged email does not fail the unique rule', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create(['email' => 'same@example.com']);

    $response = $this->actingAs($user)->put("/customers/{$customer->id}", [
        'first_name' => 'Updated',
        'last_name' => $customer->last_name,
        'telephone' => $customer->telephone,
        'email' => 'same@example.com',
        'address' => $customer->address,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('customers.index'));
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'Updated', 'email' => 'same@example.com']);
});

test('a user with CUSTOMER:WRITE can delete a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

    $response->assertRedirect(route('customers.index'));
    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});

test('a user without CUSTOMER:WRITE cannot delete a customer', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'READ']]);
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});

test('a guest cannot delete a customer', function () {
    $customer = Customer::factory()->create();

    $response = $this->delete("/customers/{$customer->id}");

    $response->assertRedirect('/login');
    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});

test('a user with CUSTOMER:WRITE cannot delete a customer that still has orders', function () {
    $user = $this->userWithPermissions([['CUSTOMER', 'WRITE']]);
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->delete("/customers/{$customer->id}");

    $response->assertSessionHasErrors('customer');
    $this->assertDatabaseHas('customers', ['id' => $customer->id]);
});
