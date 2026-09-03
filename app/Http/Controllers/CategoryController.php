<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store`/`update`/`destroy` on categories. No `create`/`edit`
 * routes - the frontend uses a create/edit modal on `Categories/Index.vue`
 * instead, per this project's fixed convention.
 */
class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $categories = Category::query()
            ->when($search, fn ($query, $search) => $query->where('category_name', 'like', "%{$search}%"))
            ->orderBy('category_name')
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `CategoryResource::collection()` on a paginator.
        $categories->through(fn (Category $category) => (new CategoryResource($category))->resolve());

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categoryService): RedirectResponse
    {
        $categoryService->createCategory($request->validated());

        return redirect()->route('categories.index')->with('flash.banner', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, CategoryService $categoryService): RedirectResponse
    {
        $categoryService->updateCategory($category, $request->validated());

        return redirect()->route('categories.index')->with('flash.banner', 'Category updated.');
    }

    public function destroy(Category $category, CategoryService $categoryService): RedirectResponse
    {
        $categoryService->deleteCategory($category);

        return redirect()->route('categories.index')->with('flash.banner', 'Category deleted.');
    }
}
