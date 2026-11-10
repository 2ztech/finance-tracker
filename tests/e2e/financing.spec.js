const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount } = require('./helpers');

test.describe('Personal Financing', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('financing cashes in and produces a separate bill on the borrow day', async ({ page }) => {
    await createAccount(page, { name: 'FIN_CASH', kind: 'savings', opening: '0', start: '2026-09' });
    await createAccount(page, { name: 'FIN_TIKTOK', kind: 'paylater', dueDay: '10', bnpl: 'cycle', offset: '1' });
    await switchAccount(page, 'FIN_TIKTOK');

    // The sidebar exposes Personal Financing for cycle paylater accounts.
    await expect(page.locator('#sidebar a[href="/financing"]')).toBeVisible();

    await page.goto('/financing');
    await page.fill('input[name="description"]', 'Test Financing');
    await page.fill('input[name="total_payable"]', '600');
    await page.fill('input[name="cash_received"]', '600');
    await page.fill('input[name="months"]', '3');
    await page.selectOption('select[name="savings_account_id"]', { label: 'FIN_CASH' });
    await page.fill('input[name="date"]', '2026-10-05');
    await Promise.all([
      page.waitForLoadState('load'),
      page.click('#financing-form button[type="submit"]'),
    ]);
    await expect(page.locator('body')).toContainText('Test Financing');

    await page.goto('/bills');
    await expect(page.locator('body')).toContainText('Personal Financing');
    await expect(page.locator('body')).toContainText('Due 05 Nov 2026');
    await page.locator('summary', { hasText: 'View details' }).first().click();
    await expect(page.locator('body')).toContainText('Personal Financing · 1/3');
  });
});
