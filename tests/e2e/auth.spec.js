const { test, expect } = require('@playwright/test');
const { login, logout } = require('./helpers');

test.describe('Authentication', () => {
  test('login reaches the dashboard', async ({ page }) => {
    await login(page);
    await expect(page).toHaveURL(/dashboard/);
    await expect(page.locator('body')).toContainText('Expenzz');
  });

  test('invalid credentials are rejected', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="username"]', 'unknown-user');
    await page.fill('input[name="password"]', 'wrong-password');
    await page.click('button[type="submit"]');
    await expect(page.locator('body')).toContainText('Invalid credentials');
  });

  test('logout returns to login', async ({ page }) => {
    await login(page);
    await logout(page);
    await expect(page).toHaveURL(/login/);
  });

  test('protected pages redirect to login when unauthenticated', async ({ page }) => {
    for (const path of ['/dashboard', '/transactions', '/accounts', '/bills', '/settings']) {
      await page.goto(path);
      await expect(page).toHaveURL(/login/);
    }
  });
});
