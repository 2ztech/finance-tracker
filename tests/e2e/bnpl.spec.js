const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction, confirmDialog } = require('./helpers');

test.describe('BNPL / bills', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  function billCard(page, dueText) {
    return page
      .locator('div.rounded-xl', { hasText: dueText })
      .filter({ has: page.locator('button:has-text("Pay")') })
      .last();
  }

  test('plan instalments, partial payment, completion and refund', async ({ page }) => {
    await createAccount(page, { name: 'TEST_BNPL_FUND', kind: 'savings', opening: '2000', start: '2026-01' });
    await createAccount(page, { name: 'TEST_BNPL_E2E', kind: 'paylater', dueDay: '10', bnpl: 'cycle', offset: '1' });
    await switchAccount(page, 'TEST_BNPL_E2E');

    await addTransaction(page, {
      account: 'TEST_BNPL_E2E', type: 'expense', amount: '9',
      category: 'Food & Dining', date: '2026-09-05', description: 'bnpl-inst-1',
      repayMode: 'installments', months: '3',
    });

    await page.goto('/bills');
    await expect(page.locator('body')).toContainText('Due 10 Oct 2026');

    // Full price RM9 over 3 months = RM3.00 per bill. Partial RM1.
    await billCard(page, 'Due 10 Oct 2026').locator('button:has-text("Pay")').click();
    await expect(page.locator('#payModal')).toBeVisible();
    await page.selectOption('#payModal select[name="from_account_id"]', { label: 'TEST_BNPL_FUND' });
    await page.fill('#pay_amount', '1');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#payModal button[type="submit"]'),
    ]);
    await expect(page.locator('body')).toContainText(/partial/i);

    // Pay the remaining RM2.
    await billCard(page, 'Due 10 Oct 2026').locator('button:has-text("Pay")').click();
    await expect(page.locator('#payModal')).toBeVisible();
    await page.fill('#pay_amount', '2');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#payModal button[type="submit"]'),
    ]);
    // Fully paid: the Pay button is gone and the paid total is shown.
    await expect(page.locator('body')).toContainText('RM 3.00 paid of RM 3.00');

    // Refund against a plan.
    await page.locator('button:has-text("Refund")').first().click();
    await expect(page.locator('#refundModal')).toBeVisible();
    await page.fill('#refund_amount', '1');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#refundModal button[type="submit"]'),
    ]);
    await expect(page.locator('body')).toContainText(/Refund recorded/i);
  });

  test('settle a plan clears its remaining balance', async ({ page }) => {
    await createAccount(page, { name: 'TEST_BNPL_FUND2', kind: 'savings', opening: '1000', start: '2026-01' });
    await createAccount(page, { name: 'TEST_BNPL_SETTLE', kind: 'paylater', dueDay: '10', bnpl: 'cycle', offset: '1' });
    await switchAccount(page, 'TEST_BNPL_SETTLE');

    await addTransaction(page, {
      account: 'TEST_BNPL_SETTLE', type: 'expense', amount: '20',
      category: 'Entertainment', date: '2026-09-06', description: 'settle-me',
      repayMode: 'installments', months: '2',
    });

    await page.goto('/bills');
    await expect(page.locator('body')).toContainText('Instalment plans');
    await page.locator('form[action="/plans/settle"] button[type="submit"]').first().click();
    await confirmDialog(page);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/settled/i);
  });
});
