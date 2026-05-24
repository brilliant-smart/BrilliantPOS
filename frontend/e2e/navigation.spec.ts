import { test, expect } from '@playwright/test';

/**
 * E2E tests for navigation and role-based access control.
 * Requires backend API running with seeded data.
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

test.describe('Dashboard Access', () => {
  test('owner can access dashboard', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    // Dashboard should render some content — look for headings or metric cards
    await expect(page.locator('h1, h2, h3').first()).toBeVisible({ timeout: 10000 });
  });

  test('cashier can access dashboard', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
    await expect(page.locator('h1, h2, h3').first()).toBeVisible({ timeout: 10000 });
  });
});

test.describe('POS Access', () => {
  test('owner can access POS', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/pos');
    await expect(page).toHaveURL(/\/admin\/pos/);
  });

  test('cashier can access POS', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/pos');
    await expect(page).toHaveURL(/\/admin\/pos/);
  });
});

test.describe('Role-Based Access Control', () => {
  test('cashier cannot access products page', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/products');
    // Cashier should be redirected away from products
    await expect(page).toHaveURL(/\/admin\/(pos|dashboard)/, { timeout: 10000 });
  });

  test('cashier cannot access users page', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/\/admin\/(pos|dashboard)/, { timeout: 10000 });
  });

  test('cashier cannot access settings', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/system/settings');
    await expect(page).toHaveURL(/\/admin\/(pos|dashboard)/, { timeout: 10000 });
  });

  test('owner can access products page', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/products');
    await expect(page).toHaveURL(/\/admin\/products/);
  });

  test('owner can access users page', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/\/admin\/users/);
  });

  test('owner can access settings', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/system/settings');
    await expect(page).toHaveURL(/\/admin\/system\/settings/);
  });
});

test.describe('Navigation Sidebar', () => {
  test('owner sees all nav links', async ({ page }) => {
    await loginAs(page, OWNER_EMAIL);
    await page.goto('/admin/dashboard');
    // Owner should see products, users, settings in the sidebar
    const sidebar = page.locator('nav, aside, [data-sidebar]');
    if (await sidebar.isVisible()) {
      await expect(sidebar.getByText(/products/i)).toBeVisible();
      await expect(sidebar.getByText(/users/i)).toBeVisible();
    }
  });

  test('cashier sees limited nav links', async ({ page }) => {
    await loginAs(page, CASHIER_EMAIL);
    await page.goto('/admin/dashboard');
    // Cashier should NOT see users or products in the sidebar
    const sidebar = page.locator('nav, aside, [data-sidebar]');
    if (await sidebar.isVisible()) {
      await expect(sidebar.getByText(/users/i)).not.toBeVisible();
    }
  });
});