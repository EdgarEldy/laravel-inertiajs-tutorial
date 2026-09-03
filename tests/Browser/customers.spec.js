import { test, expect } from '@playwright/test';

/**
 * Full create -> edit -> delete flow through the actually-rendered
 * Customers/Index.vue page (Data + Form), logged in as the seeded dev admin
 * user (ADMIN role, holds CUSTOMER:WRITE).
 *
 * This test drives against the real dev database (whatever `.env`/
 * `php artisan serve` point at), not a dedicated test database - the
 * create -> edit -> delete flow always cleans up the row it creates, so the
 * run is self-contained regardless.
 *
 * Submits by clicking the modal's own "Create"/"Save" button, not the
 * input's Enter-key shortcut - see categories.spec.js for why this matters
 * (the Components/Modal.vue stacking-context fix).
 */
test.describe('Customers', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('create, edit, then delete a customer through the rendered UI', async ({ page }) => {
        const unique = Date.now();
        const firstName = `E2E`;
        const lastName = `Customer${unique}`;
        const email = `e2e-customer-${unique}@example.com`;
        const updatedLastName = `${lastName}Updated`;
        const updatedEmail = `e2e-customer-${unique}-updated@example.com`;

        await page.goto('/customers');
        await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible();

        // Create
        await page.getByRole('button', { name: 'Create Customer' }).click();
        await expect(page.getByText('Create Customer').last()).toBeVisible();
        await page.locator('#first_name').fill(firstName);
        await page.locator('#last_name').fill(lastName);
        await page.locator('#telephone').fill('555-0100');
        await page.locator('#email').fill(email);
        await page.locator('#address').fill('123 Main St');
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('Customer created.')).toBeVisible();
        const row = page.locator('tbody tr', { hasText: lastName });
        await expect(row).toBeVisible();
        await expect(row).toContainText(email);

        // Edit
        await row.getByRole('button', { name: 'Edit' }).click();
        await expect(page.getByText('Edit Customer').last()).toBeVisible();
        await expect(page.locator('#first_name')).toHaveValue(firstName);
        await expect(page.locator('#email')).toHaveValue(email);
        await page.locator('#last_name').fill(updatedLastName);
        await page.locator('#email').fill(updatedEmail);
        await page.locator('dialog').getByRole('button', { name: 'Save' }).click();

        await expect(page.getByText('Customer updated.')).toBeVisible();
        const updatedRow = page.locator('tbody tr', { hasText: updatedLastName });
        await expect(updatedRow).toBeVisible();
        await expect(updatedRow).toContainText(updatedEmail);
        await expect(page.locator('tbody tr', { hasText: email })).toHaveCount(0);

        // Delete
        page.once('dialog', (dialog) => dialog.accept());
        await updatedRow.getByRole('button', { name: 'Delete' }).click();

        await expect(page.getByText('Customer deleted.')).toBeVisible();
        await expect(page.locator('tbody tr', { hasText: updatedLastName })).toHaveCount(0);
    });
});
