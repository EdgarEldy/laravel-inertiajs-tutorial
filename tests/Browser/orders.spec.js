import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

/**
 * Full chained flow through the actually-rendered pages: create a category
 * -> a product (in that category) -> a customer -> an order (referencing
 * that product and customer), logged in as the seeded dev admin user (ADMIN
 * role, holds every permission this project seeds). Verifies the rendered
 * `total` in the orders table equals `quantity * unit_price`.
 *
 * Unlike categories.spec.js/products.spec.js/customers.spec.js, this flow
 * cannot clean up after itself: orders have no update/destroy route at all
 * (create-only, per this project's fixed convention), and
 * `CustomerService::deleteCustomer()`/`ProductService::deleteProduct()`
 * both reject deleting a customer/product that still has an order - the
 * exact referential-integrity rule this same branch introduced (see
 * tests/Feature/CustomersTest.php and tests/Feature/ProductsTest.php). The
 * category/product/customer/order created here are therefore left in the
 * dev database, same as the seeded admin user's own dev data - each run
 * uses a `Date.now()`-based unique name so repeat runs never collide.
 */
test.describe('Orders', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('test@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);
    });

    test('create a category, a product, a customer, then an order, through the rendered UI - the displayed total matches quantity * unit_price', async ({ page }) => {
        const unique = Date.now();
        const categoryName = `E2E Order Category ${unique}`;
        const productName = `E2E Order Product ${unique}`;
        const unitPrice = '19.99';
        const quantity = '3';
        const expectedTotal = '59.97'; // 19.99 * 3
        const customerLastName = `E2EOrderCustomer${unique}`;
        const customerEmail = `e2e-order-customer-${unique}@example.com`;

        // 1. Category
        await page.goto('/categories');
        await page.getByRole('button', { name: 'Create Category' }).click();
        await page.locator('#category_name').fill(categoryName);
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Category created.')).toBeVisible();

        // 2. Product, in that category
        await page.goto('/products');
        await page.getByRole('button', { name: 'Create Product' }).click();
        await page.locator('#category_id').selectOption({ label: categoryName });
        await page.locator('#product_name').fill(productName);
        await page.locator('#unit_price').fill(unitPrice);
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Product created.')).toBeVisible();

        // 3. Customer
        await page.goto('/customers');
        await page.getByRole('button', { name: 'Create Customer' }).click();
        await page.locator('#first_name').fill('E2E');
        await page.locator('#last_name').fill(customerLastName);
        await page.locator('#telephone').fill('555-0100');
        await page.locator('#email').fill(customerEmail);
        await page.locator('#address').fill('123 Main St');
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();
        await expect(page.getByText('Customer created.')).toBeVisible();

        // 4. Order, referencing the product and customer just created
        await page.goto('/orders');
        await expect(page.getByRole('heading', { name: 'Orders' })).toBeVisible();

        await page.getByRole('button', { name: 'Create Order' }).click();
        await expect(page.getByText('Create Order').last()).toBeVisible();
        await page.locator('#customer_id').selectOption({ label: `E2E ${customerLastName}` });
        await page.locator('#product_id').selectOption({ label: productName });
        await page.locator('#quantity').fill(quantity);
        await page.locator('dialog').getByRole('button', { name: 'Create' }).click();

        await expect(page.getByText('Order placed.')).toBeVisible();
        const row = page.locator('tbody tr', { hasText: customerLastName });
        await expect(row).toBeVisible();
        await expect(row).toContainText(productName);
        await expect(row).toContainText(quantity);
        await expect(row).toContainText(expectedTotal);
    });

});

// A separate `describe` block, deliberately without the admin-login
// `beforeEach` above: that `beforeEach` runs on the same `page` fixture the
// test body itself uses, and Jetstream's `guest` middleware on `/login`
// redirects an already-authenticated session straight back to `/dashboard`
// - logging in as the seeded admin first, then trying to visit `/login`
// again for a second, unprivileged user, would never reach the login form
// at all. This test needs to start from a guest session.
test.describe('Orders - permission denied', () => {
    test('a user without ORDER:READ is denied the orders page', async ({ page }) => {
        // Idempotent fixture: a verified user holding only the seeded `USER`
        // role, which (per PermissionSeeder/OrderPermissionSeeder's
        // incremental seeding convention) is never granted any permission -
        // only `ADMIN` is. Created directly against the app's own database
        // (the same one `php artisan serve`, started by this config's
        // `webServer` block, already connects to) rather than through the
        // real registration + email-verification flow, which would need to
        // read the verification link back out of MailHog - overkill for a
        // permission-denied fixture that must merely exist and be verified.
        // `execFileSync` (not `execSync`) passes the PHP code straight
        // through to the `php` process argv, with no intermediate shell to
        // mangle its `$variables` - the alternative was found, mid-writing
        // this test, to silently strip every `$` via shell interpolation
        // even inside a double-quoted --execute="...", which resulted in a
        // Psy `ParseErrorException` rather than a silent bad fixture.
        //
        // `email_verified_at` is set via `forceFill()->save()`, not the
        // `firstOrCreate()` attributes array - `User::$fillable` does not
        // include `email_verified_at` (Jetstream's own verification flow is
        // the only thing meant to set it), so passing it through mass
        // assignment is silently discarded rather than persisted, which was
        // found here the hard way: the fixture user existed and had the
        // right role, but every login redirected straight to
        // `/email/verify` instead of `/dashboard` until this was fixed.
        const fixturePhp = `
            $role = App\\Models\\Role::firstOrCreate(['role_name' => 'USER']);
            $user = App\\Models\\User::firstOrCreate(
                ['email' => 'e2e-no-permission@example.com'],
                [
                    'first_name' => 'E2E',
                    'last_name' => 'NoPermission',
                    'password' => Illuminate\\Support\\Facades\\Hash::make('password'),
                ]
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->roles()->syncWithoutDetaching([$role->id]);
        `;
        execFileSync('php', ['artisan', 'tinker', '--execute', fixturePhp]);

        await page.goto('/login');
        await page.getByLabel('Email').fill('e2e-no-permission@example.com');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();
        await expect(page).toHaveURL(/\/dashboard/);

        // No "Orders" nav link either (can('ORDER:READ') UI convenience),
        // but the real, authoritative boundary is the server response - a
        // direct visit is denied regardless of what the nav shows.
        const response = await page.goto('/orders');
        expect(response.status()).toBe(403);
        await expect(page.getByRole('button', { name: 'Create Order' })).toHaveCount(0);
    });
});
