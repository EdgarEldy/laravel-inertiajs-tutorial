<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store`/`update`/`destroy` on products. No `create`/`edit`
 * routes - the frontend uses a create/edit modal on `Products/Index.vue`
 * instead, per this project's fixed convention.
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $categoryId = $request->integer('category') ?: null;

        $products = Product::query()
            ->with('category')
            ->when($search, fn ($query, $search) => $query->where('product_name', 'like', "%{$search}%"))
            ->when($categoryId, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('product_name')
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `ProductResource::collection()` on a paginator.
        $products->through(fn (Product $product) => (new ProductResource($product))->resolve());

        // `->resolve()` is required here - see RoleController::permissions()
        // for why a bare `JsonResource`/`ResourceCollection` can't be handed
        // to `Inertia::render()` directly.
        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => CategoryResource::collection(Category::query()->orderBy('category_name')->get())->resolve(),
            'filters' => ['search' => $search, 'category' => $categoryId],
        ]);
    }

    public function store(StoreProductRequest $request, ProductService $productService): RedirectResponse
    {
        $productService->createProduct($request->validated());

        return redirect()->route('products.index')->with('flash.banner', 'Product created.');
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $productService): RedirectResponse
    {
        $productService->updateProduct($product, $request->validated());

        return redirect()->route('products.index')->with('flash.banner', 'Product updated.');
    }

    public function destroy(Product $product, ProductService $productService): RedirectResponse
    {
        $productService->deleteProduct($product);

        return redirect()->route('products.index')->with('flash.banner', 'Product deleted.');
    }
}
