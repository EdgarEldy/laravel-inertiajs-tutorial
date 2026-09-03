<?php

namespace App\Services;

use App\Models\Category;

/**
 * CRUD for categories. Unlike `RoleService`/`PermissionService`/`UserService`,
 * this is not one of the RBAC services and does not use `LogsAuditEvents` -
 * audit logging in this project is scoped to RBAC mutations only.
 */
class CategoryService
{
    public function createCategory(array $data): Category
    {
        return Category::create([
            'category_name' => $data['category_name'],
        ]);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update([
            'category_name' => $data['category_name'],
        ]);

        return $category;
    }

    /**
     * No referential-integrity check yet - `Product` doesn't exist on this
     * branch. `feature/products` updates this method to reject deletion if
     * the category still has products, following the same pattern as
     * `RoleService::deleteRole()`/`PermissionService::deletePermission()`.
     */
    public function deleteCategory(Category $category): void
    {
        $category->delete();
    }
}
