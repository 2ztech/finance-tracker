const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, confirmDialog } = require('./helpers');

test.describe('Recurring items', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  function upcomingSection(page) {
    return page.locator('section', { has: page.getByRole('heading', { name: 'Upcoming Bills' }) });
  }

  function itemCard(page, name) {
    return page.locator('tr.recurring-row', { hasText: name }).first();
  }

  async function submitRecurring(page) {
    const confirms = await page.$eval('#commitment_form', (f) => f.hasAttribute('data-confirm'));
    await page.click('#commitment_form button[type="submit"]');
    if (confirms) {
      await expect(page.locator('#appDialog')).toBeVisible();
      await Promise.all([page.waitForLoadState('load'), page.click('#appDialogAccept')]);
    } else {
      await page.waitForLoadState('load');
    }
  }

  async function addRecurring(page, { type = 'expense', name, amount, category, day = 31, start = '2026-09-01', end } = {}) {
    await page.goto('/recurring');
    await page.selectOption('#form_type', type);
    await page.fill('#form_name', name);
    await page.fill('#form_amount', String(amount));
    await page.selectOption('#form_category_id', { label: category });
    await page.$eval('#due_date_slider', (el, v) => { el.value = String(v); el.dispatchEvent(new Event('input')); }, day);
    await page.fill('#form_start_date', start);
    if (end) await page.fill('#form_end_date', end);
    await submitRecurring(page);
  }

  test('post now logs a transaction, clears Upcoming, and deleting reopens it', async ({ page }) => {
    await createAccount(page, { name: 'REC_A', kind: 'savings', opening: '1000', start: '2026-09' });
    await switchAccount(page, 'REC_A');

    await addRecurring(page, { name: 'RENT_E2E', amount: 300, category: 'Groceries', day: 31 });
    const card = itemCard(page, 'RENT_E2E');
    await expect(card).toBeVisible();
    await expect(card.locator('button:has-text("Post now")')).toBeVisible();

    // Post now -> registers a transaction and flips to "Posted".
    await card.locator('button:has-text("Post now")').click();
    await page.waitForLoadState('load');
    await expect(itemCard(page, 'RENT_E2E')).toContainText('Posted');

    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'RENT_E2E' })).toBeVisible();

    // The current month's occurrence clears once paid (next month's stays).
    await page.goto('/dashboard');
    await expect(upcomingSection(page)).not.toContainText('Due 30 Sep 2026');

    // Delete the posted transaction -> the month is uncovered again (Model 1).
    await page.goto('/transactions');
    await page.locator('.transaction-row', { hasText: 'RENT_E2E' }).locator('form[action*="/transactions"] button[type="submit"]').click();
    await confirmDialog(page);
    await page.waitForLoadState('load');
    await expect(page.locator('.transaction-row', { hasText: 'RENT_E2E' })).toHaveCount(0);

    // Post now is available again, and the current month reappears in Upcoming.
    await page.goto('/recurring');
    await expect(itemCard(page, 'RENT_E2E').locator('button:has-text("Post now")')).toBeVisible();
    await page.goto('/dashboard');
    await expect(upcomingSection(page)).toContainText('Due 30 Sep 2026');
  });

  test('editing the category propagates, and archive keeps history', async ({ page }) => {
    await createAccount(page, { name: 'REC_B', kind: 'savings', opening: '1000', start: '2026-09' });
    await switchAccount(page, 'REC_B');
    await addRecurring(page, { name: 'LOAN_E2E', amount: 100, category: 'Groceries', day: 31 });

    await itemCard(page, 'LOAN_E2E').locator('button:has-text("Post now")').click();
    await page.waitForLoadState('load');

    // Edit category Groceries -> Entertainment; the posted txn must follow.
    await itemCard(page, 'LOAN_E2E').locator('button[onclick*="editItem"]').click();
    await page.selectOption('#form_category_id', { label: 'Entertainment' });
    await Promise.all([page.waitForLoadState('load'), page.click('#commitment_form button[type="submit"]')]);

    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'LOAN_E2E' })).toContainText('Entertainment');

    // Archive -> moves to the Archived list, ledger untouched.
    await page.goto('/recurring');
    await itemCard(page, 'LOAN_E2E').locator('button[title="Archive"]').click();
    await confirmDialog(page);
    await page.waitForLoadState('load');
    await expect(page.locator('body')).toContainText('Archived (1)');
    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'LOAN_E2E' })).toBeVisible();

    // Restore.
    await page.goto('/recurring');
    await page.locator('summary', { hasText: 'Archived' }).click();
    await Promise.all([page.waitForLoadState('load'), page.locator('button:has-text("Restore")').first().click()]);
    await expect(itemCard(page, 'LOAN_E2E')).toBeVisible();
  });

  test('main account drives the projected end-of-month card', async ({ page }) => {
    await page.goto('/accounts');
    await page.click('text=+ Add Account');
    await page.fill('#acc_name', 'REC_MAIN');
    await page.selectOption('#acc_kind', 'savings');
    await page.fill('#acc_opening', '500');
    await page.fill('#acc_start', '2026-09');
    await page.check('#acc_primary');
    await page.click('button:has-text("Save Account")');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText('REC_MAIN');

    await switchAccount(page, 'REC_MAIN');
    await addRecurring(page, { type: 'income', name: 'SAL_E2E', amount: 1000, category: 'Salary', day: 31 });
    await addRecurring(page, { name: 'BIL_E2E', amount: 200, category: 'Groceries', day: 31 });

    await page.goto('/dashboard');
    const card = page.locator('section[aria-label="Projected end of month"]');
    await expect(card).toBeVisible();
    await expect(card).toContainText('RM 1,300.00'); // 500 + 1000 - 200
  });

  test('start date is optional and adds no backdated entries', async ({ page }) => {
    await createAccount(page, { name: 'REC_C', kind: 'savings', opening: '0', start: '2026-09' });
    await switchAccount(page, 'REC_C');

    await addRecurring(page, { name: 'OPT_E2E', amount: 20, category: 'Groceries', day: 31, start: '' });
    await expect(itemCard(page, 'OPT_E2E')).toBeVisible();

    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'OPT_E2E' })).toHaveCount(0);
  });

  test('a past start date asks for confirmation before backfilling', async ({ page }) => {
    await createAccount(page, { name: 'REC_D', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'REC_D');

    await page.goto('/recurring');
    await page.selectOption('#form_type', 'expense');
    await page.fill('#form_name', 'BACK_E2E');
    await page.fill('#form_amount', '10');
    await page.selectOption('#form_category_id', { label: 'Groceries' });
    await page.fill('#form_start_date', '2026-01-01');
    await page.click('#commitment_form button[type="submit"]');

    const dialog = page.locator('#appDialog');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(/backdated/i);
    await Promise.all([page.waitForLoadState('load'), page.click('#appDialogAccept')]);

    await expect(itemCard(page, 'BACK_E2E')).toBeVisible();
    await page.goto('/transactions');
    await expect(page.locator('.transaction-row', { hasText: 'BACK_E2E' }).first()).toBeVisible();
  });

  test('an expired schedule moves into the Archived list', async ({ page }) => {
    await createAccount(page, { name: 'REC_E', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'REC_E');

    await addRecurring(page, { name: 'EXP_E2E', amount: 15, category: 'Groceries', day: 1, start: '2026-01-01', end: '2026-02-28' });

    await expect(page.locator('body')).toContainText('Archived (1)');
    await page.locator('summary', { hasText: 'Archived' }).click();
    await expect(page.locator('body')).toContainText('EXP_E2E');
  });
});
