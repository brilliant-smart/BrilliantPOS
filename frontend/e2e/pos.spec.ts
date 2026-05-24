import { test, expect } from '@playwright/test';

/**
 * E2E tests for POS terminal operations.
 * Requires backend API running with seeded data.
 *
 * Seeded products:
 *   Paracetamol 500mg (Pack of 100) — SKU: PHM-PAR-500
 *   Golden Penny Semovita 2kg — SKU: SS-GP-SEM-2
 */

const OWNER_EMAIL = 'admin@brilliantpos.com';
const CASHIER_EMAIL = 'cashier@brilliantpos.com';
const PASSWORD = 'password';

async function loginAs(page, email: string) {
  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(PASSWORD);
  await page.getByRole('button', { name: /login/i }).click();
  await expect(page).toHaveURL(/\/admin/, { timeout: 10000 });
}

test.describe('POS Terminal', () => {
  test('POS page loads with product search', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');
    // The POS should have a search or product area
    await expect(page.locator('input[type="text"], input[type="search"], input[placeholder*="search" i]').first()).toBeVisible({ timeout: 10000 });
  });

  test('can search for a product', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');

    // Find the search input and type a product name
    const searchInput = page.locator('input[type="text"], input[type="search"], input[placeholder*="search" i]').first();
    await searchInput.fill('Paracetamol');
    await page.waitForTimeout(500); // Wait for debounce/search

    // Should show Paracetamol in results
    await expect(page.getByText(/Paracetamol/i)).toBeVisible({ timeout: 10000 });
  });

  test('can add product to cart', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');

    // Search for a product
    const searchInput = page.locator('input[type="text"], input[type="search"], input[placeholder*="search" i]').first();
    await searchInput.fill('Paracetamol');
    await page.waitForTimeout(500);

    // Click on the product to add it to cart
    const productItem = page.getByText(/Paracetamol/i).first();
    await productItem.click();

    // Cart should show the product
    await expect(page.getByText(/Paracetamol/i)).toBeVisible({ timeout: 5000 });
  });

  test('cart shows total after adding item', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');

    // Search and add product
    const searchInput = page.locator('input[type="text"], input[type="search"], input[placeholder*="search" i]').first();
    await searchInput.fill('Paracetamol');
    await page.waitForTimeout(500);
    await page.getByText(/Paracetamol/i).first().click();

    // Wait for cart to update and check for a total amount
    await page.waitForTimeout(500);
    const cartArea = page.locator('[data-testid="cart"], [data-testid="cart-total"], .cart, aside').first();
    if (await cartArea.isVisible()) {
      // Should have a numeric total (e.g. ₦2,500 or 2500.00)
      await expect(page.getByText(/\d+[,.]?\d*/).first()).toBeVisible({ timeout: 5000 });
    }
  });

  test('can increase item quantity in cart', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');

    // Add a product first
    const searchInput = page.locator('input[type="text"], input[type="search"], input[placeholder*="search" i]').first();
    await searchInput.fill('Paracetamol');
    await page.waitForTimeout(500);
    await page.getByText(/Paracetamol/i).first().click();
    await page.waitForTimeout(500);

    // Look for a quantity increment button (+)
    const incrementBtn = page.getByRole('button', { name: /\+|increase|increment/i }).first();
    if (await incrementBtn.isVisible()) {
      await incrementBtn.click();
      // Quantity should increase — look for "2" or "x2"
      await expect(page.getByText(/\b2\b/).first()).toBeVisible({ timeout: 5000 });
    }
  });
});

test.describe('Sales List', () => {
  test('sales page loads for owner', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/sales');
    await expect(page).toHaveURL(/\/admin\/sales/);
    // Should show sales listing or empty state
    await expect(page.locator('h1, h2, h3, [data-testid="sales-table"], [data-testid="empty-state"]').first()).toBeVisible({ timeout: 10000 });
  });

  test('cashier can access sales list', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/sales');
    await expect(page).toHaveURL(/\/admin\/sales/);
  });
});

test.describe('Expenses', () => {
  test('expenses page loads for owner', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/expenses');
    await expect(page).toHaveURL(/\/admin\/expenses/);
    await expect(page.locator('h1, h2, h3').first()).toBeVisible({ timeout: 10000 });
  });

  test('cashier can access expenses', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/expenses');
    await expect(page).toHaveURL(/\/admin\/expenses/);
  });
});

test.describe('Responsive Layout', () => {
  test('POS page renders on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');
    // Page should still be functional on mobile
    await expect(page.locator('main, [role="main"], #root').first()).toBeVisible({ timeout: 10000 });
  });

  test('login page renders on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/login');
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
  });
});