import { test, expect } from '@playwright/test';

/**
 * E2E tests for BrilliantPOS authentication flows.
 * Requires backend API running with seeded data.
 *
 * Seeded credentials:
 *   Owner:   admin@brilliantpos.com / password
 *   Manager: manager@brilliantpos.com / password
 *   Cashier: cashier@brilliantpos.com / password
 */

const OWNER_EMAIL = 'admin@brilliantpos.com';
const MANAGER_EMAIL = 'manager@brilliantpos.com';
const CASHIER_EMAIL = 'cashier@brilliantpos.com';
const PASSWORD = 'password';

test.describe('Authentication', () => {
  test('login page renders with email and password fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: /login/i })).toBeVisible();
  });

  test('shows error on empty submit', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page.getByText(/required|invalid/i)).toBeVisible();
  });

  test('shows error for invalid email format', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('not-an-email');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page.getByText(/valid email/i)).toBeVisible();
  });

  test('shows error for short password', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('admin@brilliantpos.com');
    await page.locator('#password').fill('short');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page.getByText(/at least 6 characters/i)).toBeVisible();
  });

  test('shows error for wrong credentials', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('admin@brilliantpos.com');
    await page.locator('#password').fill('wrongpassword');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page.getByText(/invalid|incorrect|failed/i)).toBeVisible();
  });

  test('owner login redirects to dashboard', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(OWNER_EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard/, { timeout: 10000 });
  });

  test('cashier login redirects to POS', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(CASHIER_EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/pos/, { timeout: 10000 });
  });

  test('manager login redirects to dashboard', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill(MANAGER_EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/dashboard/, { timeout: 10000 });
  });
});

test.describe('Auth Guard', () => {
  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/\/login/, { timeout: 10000 });
  });

  test('unauthenticated user accessing POS is redirected to login', async ({ page }) => {
    await page.goto('/admin/pos');
    await expect(page).toHaveURL(/\/login/, { timeout: 10000 });
  });
});

test.describe('Logout', () => {
  test('logout clears session and redirects to login', async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.locator('#email').fill(OWNER_EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin/, { timeout: 10000 });

    // Find and click logout — could be in nav, user menu, or profile
    const logoutBtn = page.getByRole('button', { name: /logout|sign out|log out/i });
    const logoutLink = page.getByRole('link', { name: /logout|sign out|log out/i });

    if (await logoutBtn.isVisible()) {
      await logoutBtn.click();
    } else if (await logoutLink.isVisible()) {
      await logoutLink.click();
    } else {
      // Check for a user menu dropdown that might contain logout
      const userMenu = page.getByRole('button', { name: /user|profile|account|menu/i }).first();
      if (await userMenu.isVisible()) {
        await userMenu.click();
        await page.getByRole('menuitem', { name: /logout|sign out/i }).click();
      }
    }

    await expect(page).toHaveURL(/\/login/, { timeout: 10000 });
  });
});