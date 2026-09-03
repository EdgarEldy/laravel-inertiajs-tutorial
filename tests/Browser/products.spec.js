import { test, expect } from '@playwright/test';

/**
 * Full create -> edit -> delete flow through the actually-rendered
 * Products/Index.vue page (Data + Form), logged in as the seeded dev admin
 * user (ADMIN role, holds PRODUCT:WRITE and CATEGORY:WRITE).
 *
 * A dedicated category is created first (through the real Categories UI,
 * same pattern as categories.spec.js) so the product form's category select
 * has a known, disposable option to pick - and deleted again at the end,
 * after the product itself, since `CategoryService::deleteCategory()`
 * rejects deleting a category that still has products. This keeps the run
 * self-contained against the real dev database regardless of whatever other
 * categories/products already exist.
 *
 * Submits by clicking the modal's own "Create"/"Save" button, not the
 * input's Enter-key shortcut - see categories.spec.js for why this matters
 * (the Components/Modal.vue stacking-context fix).
 */
test.describe('Products', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('create, edit, then delete a product through the rendered UI', async ({ page }) => {
        const categoryName = `E2E Product Category ${Date.now()}`;
        const productName = `E2E Product ${Date.now()}`;
        const updatedName = `${productName} (updated)`;

        // Set up a dedicated, disposable category for this product.
        await page.goto('/categories');
        await page.getByRole('button', { name: 'Create Category' }).click();
        await page.locator('#category_name').fill(categoryName);
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Category created.')).toBeVisible();

        // Create the product
        await page.goto('/products');
        await expect(page.getByRole('heading', { name: 'Products' })).toBeVisible();

        await page.getByRole('button', { name: 'Create Product' }).click();
        await expect(page.getByText('Create Product').last()).toBeVisible();
        await page.locator('#category_id').selectOption({ label: categoryName });
        await page.locator('#product_name').fill(productName);
        await page.locator('#unit_price').fill('42.50');
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('Product created.')).toBeVisible();
        const row = page.locator('tbody tr', { hasText: productName });
        await expect(row).toBeVisible();
        await expect(row).toContainText(categoryName);
        await expect(row).toContainText('42.5');

        // Edit
        await row.getByRole('button', { name: 'Edit' }).click();
        await expect(page.getByText('Edit Product').last()).toBeVisible();
        await expect(page.locator('#product_name')).toHaveValue(productName);
        await page.locator('#product_name').fill(updatedName);
        await page.locator('#unit_price').fill('99.99');
        await page.locator('dialog').getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Product updated.')).toBeVisible();
        const updatedRow = page.locator('tbody tr', { hasText: updatedName });
        await expect(updatedRow).toBeVisible();
        await expect(updatedRow).toContainText('99.99');
        await expect(page.locator('tbody tr', { hasText: productName, hasNotText: updatedName })).toHaveCount(0);

        // Delete
        page.once('dialog', (dialog) => dialog.accept());
        await updatedRow.getByRole('button', { name: 'Delete' }).click();

        await expect(page.getByText('Product deleted.')).toBeVisible();
        await expect(page.locator('tbody tr', { hasText: updatedName })).toHaveCount(0);

        // Clean up the dedicated category (now product-free, so this is
        // allowed by CategoryService::deleteCategory()).
        await page.goto('/categories');
        const categoryRow = page.locator('tbody tr', { hasText: categoryName });
        page.once('dialog', (dialog) => dialog.accept());
        await categoryRow.getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByText('Category deleted.')).toBeVisible();
    });

    test('the category filter dropdown narrows the products list to the selected category', async ({ page }) => {
        const categoryAName = `E2E Filter Category A ${Date.now()}`;
        const categoryBName = `E2E Filter Category B ${Date.now()}`;
        const productAName = `E2E Filter Product A ${Date.now()}`;
        const productBName = `E2E Filter Product B ${Date.now()}`;

        // Set up two categories, one product each.
        await page.goto('/categories');
        for (const name of [categoryAName, categoryBName]) {
            await page.getByRole('button', { name: 'Create Category' }).click();
            await page.locator('#category_name').fill(name);
            await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
            await expect(page.getByText('Category created.')).toBeVisible();
        }

        await page.goto('/products');
        for (const [categoryName, productName] of [[categoryAName, productAName], [categoryBName, productBName]]) {
            await page.getByRole('button', { name: 'Create Product' }).click();
            await page.locator('#category_id').selectOption({ label: categoryName });
            await page.locator('#product_name').fill(productName);
            await page.locator('#unit_price').fill('10.00');
            await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
            await expect(page.getByText('Product created.')).toBeVisible();
        }

        // Filter down to category A only. The product form's own category
        // select (id="category_id") stays in the DOM even while its modal is
        // closed, so it is excluded explicitly rather than relying on
        // `page.locator('select')` picking "the first one" by luck.
        await page.locator('select:not(#category_id)').selectOption({ label: categoryAName });
        await expect(page).toHaveURL(/category=\d+/);
        await expect(page.locator('tbody tr', { hasText: productAName })).toBeVisible();
        await expect(page.locator('tbody tr', { hasText: productBName })).toHaveCount(0);

        // Clean up: delete both products then both categories.
        for (const productName of [productAName, productBName]) {
            await page.goto('/products');
            const row = page.locator('tbody tr', { hasText: productName });
            page.once('dialog', (dialog) => dialog.accept());
            await row.getByRole('button', { name: 'Delete' }).click();
            await expect(page.getByText('Product deleted.')).toBeVisible();
        }

        await page.goto('/categories');
        for (const categoryName of [categoryAName, categoryBName]) {
            const categoryRow = page.locator('tbody tr', { hasText: categoryName });
            page.once('dialog', (dialog) => dialog.accept());
            await categoryRow.getByRole('button', { name: 'Delete' }).click();
            await expect(page.getByText('Category deleted.')).toBeVisible();
        }
    });
});
