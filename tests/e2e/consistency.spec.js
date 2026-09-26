const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction } = require('./helpers');

test.describe('Cross-page consistency', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('add-form Type labels follow the selected account kind', async ({ page }) => {
    await createAccount(page, { name: 'CONS_SAV', kind: 'savings', opening: '0', start: '2026-01' });
    await createAccount(page, { name: 'CONS_PL', kind: 'paylater', dueDay: '10', bnpl: 'cycle', offset: '1' });
    await switchAccount(page, 'CONS_SAV');

    await page.goto('/transactions');
    const typeOptions = () => page.evaluate(() =>
      [...document.querySelectorAll('#addRecordForm select[name="type"] option')].map((o) => o.text));

    // Savings selected -> Expense/Income
    expect(await typeOptions()).toEqual(['Expense', 'Income']);

    // Switch the add-form account to a paylater account -> Purchase/Refund
    await page.selectOption('#add_account_id', { label: 'CONS_PL' });
    expect(await typeOptions()).toEqual(['Purchase', 'Refund / Credit']);

    // Back to savings -> Expense/Income again
    await page.selectOption('#add_account_id', { label: 'CONS_SAV' });
    expect(await typeOptions()).toEqual(['Expense', 'Income']);
  });

  test('transaction edit/delete stays consistent across dashboard and budget', async ({ page }) => {
    await createAccount(page, { name: 'CONS_BUD', kind: 'savings', opening: '500', start: '2026-01' });
    await switchAccount(page, 'CONS_BUD');

    // Budget 100 on Groceries.
    await page.goto('/budgets');
    await page.selectOption('form[action="/budget/set"] select[name="category_id"]', { label: 'Groceries' });
    await page.fill('form[action="/budget/set"] input[name="amount"]', '100');
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("Save Budget")')]);

    await addTransaction(page, {
      account: 'CONS_BUD', type: 'expense', amount: '40',
      category: 'Groceries', date: '2026-09-15', description: 'CONS_TX',
    });

    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 460.00');
    await expect(page.locator('body')).toContainText('40 / RM 100');

    // Edit 40 -> 70
    await page.goto('/transactions');
    const row = page.locator('.transaction-row', { hasText: 'CONS_TX' });
    await row.locator('button[onclick*="openEdit"]').click();
    await page.fill('#edit_amount', '70');
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("Update Transaction")')]);

    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 430.00');
    await expect(page.locator('body')).toContainText('70 / RM 100');

    // Delete -> reverts
    await page.goto('/transactions');
    page.on('dialog', (d) => d.accept());
    await Promise.all([
      page.waitForLoadState('load'),
      page.locator('.transaction-row', { hasText: 'CONS_TX' }).locator('form[action^="/transactions"] button[type="submit"]').click(),
    ]);
    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 500.00');
    await expect(page.locator('body')).toContainText('0 / RM 100');
  });
});
