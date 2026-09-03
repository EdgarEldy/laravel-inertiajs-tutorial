<?php

use App\Models\Category;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with CATEGORY:READ can view the categories index', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'READ']]);
    Category::factory()->create(['category_name' => 'Electronics']);

    $response = $this->actingAs($user)->get('/categories');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Categories/Index'));
});

test('a user without CATEGORY:READ is denied the categories index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/categories');

    $response->assertForbidden();
});

test('a guest is redirected away from the categories index', function () {
    $response = $this->get('/categories');

    $response->assertRedirect('/login');
});

test('a user with CATEGORY:WRITE can create a category', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);

    $response = $this->actingAs($user)->post('/categories', ['category_name' => 'Furniture']);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', ['category_name' => 'Furniture']);
});

test('creating a category without a category_name fails validation', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $categoriesBefore = Category::count();

    $response = $this->actingAs($user)->post('/categories', ['category_name' => '']);

    $response->assertSessionHasErrors('category_name');
    expect(Category::count())->toBe($categoriesBefore);
});

test('creating a category with a category_name that already exists fails validation', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    Category::factory()->create(['category_name' => 'Furniture']);

    $response = $this->actingAs($user)->post('/categories', ['category_name' => 'Furniture']);

    $response->assertSessionHasErrors('category_name');
});

test('a user without CATEGORY:WRITE cannot create a category', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'READ']]);

    $response = $this->actingAs($user)->post('/categories', ['category_name' => 'Furniture']);

    $response->assertForbidden();
    $this->assertDatabaseMissing('categories', ['category_name' => 'Furniture']);
});

test('a user with CATEGORY:WRITE can update a category name', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $category = Category::factory()->create(['category_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/categories/{$category->id}", ['category_name' => 'NEW_NAME']);

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'category_name' => 'NEW_NAME']);
});

test('updating a category without a category_name fails validation', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $category = Category::factory()->create(['category_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/categories/{$category->id}", ['category_name' => '']);

    $response->assertSessionHasErrors('category_name');
});

test('updating a category to a name already used by another category fails validation', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    Category::factory()->create(['category_name' => 'TAKEN']);
    $category = Category::factory()->create(['category_name' => 'ORIGINAL']);

    $response = $this->actingAs($user)->put("/categories/{$category->id}", ['category_name' => 'TAKEN']);

    $response->assertSessionHasErrors('category_name');
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'category_name' => 'ORIGINAL']);
});

test('updating a category to its own current name does not fail the unique rule', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $category = Category::factory()->create(['category_name' => 'SAME_NAME']);

    $response = $this->actingAs($user)->put("/categories/{$category->id}", ['category_name' => 'SAME_NAME']);

    $response->assertSessionHasNoErrors();
});

test('a user without CATEGORY:WRITE cannot update a category', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'READ']]);
    $category = Category::factory()->create(['category_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/categories/{$category->id}", ['category_name' => 'NEW_NAME']);

    $response->assertForbidden();
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'category_name' => 'OLD_NAME']);
});

test('a user with CATEGORY:WRITE can delete a category', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $category = Category::factory()->create(['category_name' => 'UNUSED']);

    $response = $this->actingAs($user)->delete("/categories/{$category->id}");

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a user without CATEGORY:WRITE cannot delete a category', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'READ']]);
    $category = Category::factory()->create(['category_name' => 'UNUSED']);

    $response = $this->actingAs($user)->delete("/categories/{$category->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('a user with CATEGORY:WRITE cannot delete a category that still has products', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'WRITE']]);
    $category = Category::factory()->create(['category_name' => 'Electronics']);
    Product::factory()->create(['category_id' => $category->id]);

    $response = $this->actingAs($user)->delete("/categories/{$category->id}");

    $response->assertSessionHasErrors('category');
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('a guest cannot create, update, or delete a category', function () {
    $category = Category::factory()->create(['category_name' => 'UNUSED']);

    $this->post('/categories', ['category_name' => 'Furniture'])->assertRedirect('/login');
    $this->put("/categories/{$category->id}", ['category_name' => 'NEW_NAME'])->assertRedirect('/login');
    $this->delete("/categories/{$category->id}")->assertRedirect('/login');

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'category_name' => 'UNUSED']);
});

test('the categories index search filters by category_name and eager loads paginated results', function () {
    $user = $this->userWithPermissions([['CATEGORY', 'READ']]);
    Category::factory()->create(['category_name' => 'Electronics']);
    Category::factory()->create(['category_name' => 'Furniture']);

    $response = $this->actingAs($user)->get('/categories?search=Elect');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Categories/Index')
        ->has('categories.data', 1)
        ->where('categories.data.0.category_name', 'Electronics')
        ->where('filters.search', 'Elect')
    );
});
