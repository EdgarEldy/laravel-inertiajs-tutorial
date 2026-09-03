import { test, expect } from '@playwright/test';

/**
 * Full create -> edit -> delete flow through the actually-rendered
 * Categories/Index.vue page (Data + Form), logged in as the seeded dev
 * admin user (ADMIN role, holds CATEGORY:WRITE).
 *
 * This test drives against the real dev database (whatever `.env`/
 * `php artisan serve` point at), not a dedicated test database - the
 * create -> edit -> delete flow always cleans up the row it creates, so the
 * run is self-contained regardless.
 *
 * Submits by clicking the modal's own "Create"/"Save" button, not the
 * input's Enter-key shortcut - a real pointer click on those buttons was
 * broken project-wide until a stacking-context fix in Components/Modal.vue
 * (the backdrop permanently covered its own content once Vue's enter
 * transition settled, since Tailwind 4's bare `transform` utility no
 * longer creates a stacking context the way Tailwind 3's did). Clicking
 * the actual button here is what exercises that fix, not a workaround
 * around it.
 */
test.describe('Categories', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('create, edit, then delete a category through the rendered UI', async ({ page }) => {
        const categoryName = `E2E Category ${Date.now()}`;
        const updatedName = `${categoryName} (updated)`;

        await page.goto('/categories');
        await expect(page.getByRole('heading', { name: 'Categories' })).toBeVisible();

        // Create
        await page.getByRole('button', { name: 'Create Category' }).click();
        await expect(page.getByText('Create Category').last()).toBeVisible();
        const nameInput = page.locator('#category_name');
        await nameInput.fill(categoryName);
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('Category created.')).toBeVisible();
        const row = page.locator('tbody tr', { hasText: categoryName });
        await expect(row).toBeVisible();

        // Edit
        await row.getByRole('button', { name: 'Edit' }).click();
        await expect(page.getByText('Edit Category').last()).toBeVisible();
        await expect(nameInput).toHaveValue(categoryName);
        await nameInput.fill(updatedName);
        await page.locator('dialog').getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Category updated.')).toBeVisible();
        const updatedRow = page.locator('tbody tr', { hasText: updatedName });
        await expect(updatedRow).toBeVisible();
        await expect(page.locator('tbody tr', { hasText: categoryName, hasNotText: updatedName })).toHaveCount(0);

        // Delete
        page.once('dialog', (dialog) => dialog.accept());
        await updatedRow.getByRole('button', { name: 'Delete' }).click();

        await expect(page.getByText('Category deleted.')).toBeVisible();
        await expect(page.locator('tbody tr', { hasText: updatedName })).toHaveCount(0);
    });
});
