const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction } = require('./helpers');

test.describe('Accounts', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('create a savings account with an opening balance', async ({ page }) => {
    await createAccount(page, { name: 'TEST_Savings_E2E', kind: 'savings', opening: '1000', start: '2026-01' });
    await switchAccount(page, 'TEST_Savings_E2E');
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 1,000.00');
  });

  test('account scoping isolates transactions', async ({ page }) => {
    await createAccount(page, { name: 'TEST_Scope_A', kind: 'savings', opening: '500', start: '2026-01' });
    await createAccount(page, { name: 'TEST_Scope_B', kind: 'savings', opening: '500', start: '2026-01' });

    await addTransaction(page, {
      account: 'TEST_Scope_A', type: 'expense', amount: '100',
      category: 'Food & Dining', date: '2026-09-15', description: 'scope-a-only',
    });

    await switchAccount(page, 'TEST_Scope_A');
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 400.00');

    await switchAccount(page, 'TEST_Scope_B');
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 500.00');
  });

  test('create a credit account and show outstanding', async ({ page }) => {
    await createAccount(page, { name: 'TEST_Credit_E2E', kind: 'credit', dueDay: '20', limit: '1000' });
    await addTransaction(page, {
      account: 'TEST_Credit_E2E', type: 'expense', amount: '250',
      category: 'Entertainment', date: '2026-09-15', description: 'card-purchase',
    });
    await switchAccount(page, 'TEST_Credit_E2E');
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('Outstanding');
    await expect(page.locator('body')).toContainText('RM 250.00');
  });
});
