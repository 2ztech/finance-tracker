const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount } = require('./helpers');

// Permanent regression coverage for the mandatory Type → Category rule.
test.describe('Type → Category filtering', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  async function categoryOptions(page) {
    return page.evaluate(() =>
      [...document.querySelectorAll('#add_category_id option')]
        .map((o) => o.textContent.trim())
        .filter((t) => t && t !== 'Uncategorized'));
  }

  test('add form shows only categories matching the selected type', async ({ page }) => {
    await createAccount(page, { name: 'TYPCAT', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TYPCAT');
    await page.goto('/transactions');

    // Default type is Expense -> only expense categories.
    await page.selectOption('#add_type', 'expense');
    const expCats = await categoryOptions(page);
    expect(expCats).toContain('Food & Dining');
    expect(expCats).not.toContain('Salary');

    // Switch to Income -> only income categories.
    await page.selectOption('#add_type', 'income');
    const incCats = await categoryOptions(page);
    expect(incCats).toContain('Salary');
    expect(incCats).not.toContain('Food & Dining');

    // Back to Expense -> expense categories again.
    await page.selectOption('#add_type', 'expense');
    expect(await categoryOptions(page)).toContain('Food & Dining');
  });

  test('backend rejects an invalid Type + Category combination', async ({ page }) => {
    await createAccount(page, { name: 'TYPCAT_BE', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TYPCAT_BE');
    await page.goto('/transactions');

    const result = await page.evaluate(async () => {
      const csrf = document.querySelector('input[name="csrf_token"]').value;
      const accountId = document.querySelector('#add_account_id').value;
      // "Food & Dining" is an expense category; pair it with type=income (invalid).
      const expenseOpt = [...document.querySelectorAll('#add_category_id option')]
        .find((o) => o.textContent.trim() === 'Food & Dining');
      const body = new URLSearchParams({
        csrf_token: csrf, action: 'add_transaction', account_id: accountId,
        type: 'income', amount: '5', description: 'INVALID_COMBO',
        date: '2026-09-15', category_id: expenseOpt ? expenseOpt.value : '0',
      });
      const res = await fetch('/transactions?month=2026-09', { method: 'POST', body, redirect: 'follow' });
      const text = await res.text();
      return { url: res.url, text };
    });
    // The handler must redirect with the invalid_category flag (no insert).
    expect(result.url).toContain('invalid_category');
    expect(result.text).toContain('does not match the transaction type');

    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'INVALID_COMBO' })).toHaveCount(0);
  });

  test('editing a transaction to another type refilters categories', async ({ page }) => {
    await createAccount(page, { name: 'TYPCAT_EDIT', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TYPCAT_EDIT');

    // Add an expense.
    await page.goto('/transactions');
    await page.selectOption('#add_type', 'expense');
    await page.fill('#add_amount', '12');
    await page.selectOption('#add_category_id', { label: 'Food & Dining' });
    await page.fill('#add_date', '2026-09-15');
    await page.fill('#add_description', 'EDIT_TYPE_TX');
    await Promise.all([page.waitForLoadState('load'), page.click('#addRecordForm button[type="submit"]')]);

    // Edit: change type to income -> category list must be income-only.
    const row = page.locator('.transaction-row', { hasText: 'EDIT_TYPE_TX' });
    await row.locator('button[onclick*="openEdit"]').click();
    await page.selectOption('#edit_type', 'income');
    const editCats = await page.evaluate(() =>
      [...document.querySelectorAll('#edit_category_id option')].map((o) => o.textContent.trim()));
    expect(editCats).toContain('Salary');
    expect(editCats).not.toContain('Food & Dining');

    // Choose a valid income category and save.
    await page.selectOption('#edit_category_id', { label: 'Salary' });
    await Promise.all([page.waitForLoadState('load'), page.click('button:has-text("Update Transaction")')]);

    await page.goto('/dashboard');
    await expect(page.locator('body')).toContainText('RM 12.00'); // income now
  });
});
