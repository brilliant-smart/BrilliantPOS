import { test, expect } from '@playwright/test';

/**
 * E2E smoke tests for BrilliantPOS.
 * These verify basic app shell integrity without requiring seeded data.
 */

test.describe('App Shell', () => {
  test('login page loads without critical console errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });

    await page.goto('/login');
    await page.waitForLoadState('networkidle');

    // Filter out non-critical errors (favicon 404s, etc.)
    const criticalErrors = errors.filter(e =>
      !e.includes('favicon') &&
      !e.includes('404') &&
      !e.includes('net::ERR_BLOCKED_BY_CLIENT')
    );
    expect(criticalErrors).toHaveLength(0);
  });

  test('login page has correct title', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveTitle(/brilliant|pos|login/i);
  });

  test('invalid route shows 404 or redirect', async ({ page }) => {
    await page.goto('/this-route-does-not-exist');
    // Should either show a 404 page or redirect to login
    const url = page.url();
    const hasNotFound = await page.getByText(/not found|404/i).isVisible().catch(() => false);
    const redirectedToLogin = /login/.test(url);
    expect(hasNotFound || redirectedToLogin).toBeTruthy();
  });
});

test.describe('Backend Health', () => {
  test('backend API responds to login endpoint', async ({ request }) => {
    const response = await request.post('/api/login', {
      data: { email: 'nonexistent@test.com', password: 'wrong' }
    });
    // Should get 401 or 422 (validation error), not 500 or connection refused
    expect([401, 422, 400]).toContain(response.status());
  });

  test('backend API returns proper error for missing fields', async ({ request }) => {
    const response = await request.post('/api/login', {
      data: {}
    });
    expect([401, 422, 400]).toContain(response.status());
    const body = await response.json();
    // Should have a message or error field
    expect(body).toHaveProperty('message');
  });
});