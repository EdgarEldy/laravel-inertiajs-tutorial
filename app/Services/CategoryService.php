<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Validation\ValidationException;

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
     * Rejects deletion if the category still has any products - it must be
     * emptied (or its products reassigned/deleted) first. Follows the same
     * referential-integrity pattern as
     * `RoleService::deleteRole()`/`PermissionService::deletePermission()`:
     * a `ValidationException` surfaces as a redirect-back field error
     * instead of letting the database's own `restrictOnDelete()` foreign
     * key raise a raw query exception up to the user.
     */
    public function deleteCategory(Category $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category still has products and cannot be deleted.',
            ]);
        }

        $category->delete();
    }
}
