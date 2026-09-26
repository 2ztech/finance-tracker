const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, confirmDialog } = require('./helpers');

test.describe('Transfers', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  async function dashboardBalance(page) {
    await page.goto('/dashboard');
    return page.locator('body').innerText();
  }

  test('transfer moves money and deleting it reverses the effect', async ({ page }) => {
    await createAccount(page, { name: 'TEST_TR_A', kind: 'savings', opening: '1000', start: '2026-01' });
    await createAccount(page, { name: 'TEST_TR_B', kind: 'savings', opening: '0', start: '2026-01' });

    await switchAccount(page, 'TEST_TR_A');
    await page.goto('/transactions');
    await page.click('button:has-text("Transfer")');
    await page.selectOption('#transferForm select[name="from_account_id"]', { label: 'TEST_TR_A' });
    await page.selectOption('#transferForm select[name="to_account_id"]', { label: 'TEST_TR_B' });
    await page.fill('#transferForm input[name="amount"]', '300');
    await page.fill('#transferForm input[name="date"]', '2026-09-15');
    await page.fill('#transferForm input[name="description"]', 'e2e-move');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#transferForm button[type="submit"]'),
    ]);

    await switchAccount(page, 'TEST_TR_A');
    await expect(await dashboardBalance(page)).toContain('RM 700.00');
    await switchAccount(page, 'TEST_TR_B');
    await expect(await dashboardBalance(page)).toContain('RM 300.00');

    // Delete the transfer from A's ledger.
    await switchAccount(page, 'TEST_TR_A');
    await page.goto('/transactions');
    const transferRow = page.locator('.transaction-row', { hasText: 'e2e-move' });
    await expect(transferRow).toBeVisible();
    await transferRow.locator('form[action="/transfers/delete"] button[type="submit"]').click();
    await confirmDialog(page);
    await page.waitForLoadState('networkidle');

    await expect(await dashboardBalance(page)).toContain('RM 1,000.00');
    await switchAccount(page, 'TEST_TR_B');
    await expect(await dashboardBalance(page)).toContain('RM 0.00');
  });
});
