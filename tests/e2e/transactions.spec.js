const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction } = require('./helpers');

test.describe('Transactions', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('create income, expense, edit and delete update the balance', async ({ page }) => {
    await createAccount(page, { name: 'TEST_TX', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TEST_TX');

    await addTransaction(page, {
      account: 'TEST_TX', type: 'income', amount: '500',
      category: 'Salary', date: '2026-09-15', description: 'income-1',
    });
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 500.00');

    await addTransaction(page, {
      account: 'TEST_TX', type: 'expense', amount: '100',
      category: 'Food & Dining', date: '2026-09-15', description: 'expense-1',
    });
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 400.00');

    // Edit the expense 100 -> 250.
    page.on('dialog', (d) => d.accept());
    await page.goto('/transactions');
    const row = page.locator('.transaction-row', { hasText: 'expense-1' });
    await row.locator('button[onclick*="openEdit"]').click();
    await page.fill('#edit_amount', '250');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('button:has-text("Update Transaction")'),
    ]);
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 250.00');

    // Delete the expense -> back to 500.
    await page.goto('/transactions');
    const row2 = page.locator('.transaction-row', { hasText: 'expense-1' });
    await Promise.all([
      page.waitForLoadState('networkidle'),
      row2.locator('form[action*="/transactions"] button[type="submit"]').click(),
    ]);
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 500.00');
  });

  test('search filters the transaction list', async ({ page }) => {
    await createAccount(page, { name: 'TEST_TX_SEARCH', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TEST_TX_SEARCH');
    await addTransaction(page, {
      account: 'TEST_TX_SEARCH', type: 'expense', amount: '11',
      category: 'Groceries', date: '2026-09-15', description: 'unique-search-token',
    });
    await page.goto('/transactions');
    await page.fill('#filterSearch', 'unique-search-token');
    await expect(page.locator('.transaction-row', { hasText: 'unique-search-token' })).toBeVisible();
    await page.fill('#filterSearch', 'no-such-token-xyz');
    await expect(page.locator('.transaction-row', { hasText: 'unique-search-token' })).toBeHidden();
  });
});
