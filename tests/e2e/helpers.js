// Shared helpers for Playwright E2E specs.
const { expect } = require('@playwright/test');

async function login(page, username = 'expenzz-e2e-user', password = 'e2e-test-password-2026') {
  await page.goto('/login');
  await page.fill('input[name="username"]', username);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForURL('**/dashboard'),
    page.click('button[type="submit"]'),
  ]);
}

async function logout(page) {
  await page.goto('/logout');
  await page.waitForURL('**/login');
}

async function createAccount(page, opts) {
  const { name, kind = 'savings', opening = '0', start = '2026-01', dueDay = '', bnpl = 'cycle', offset = '1', limit = '' } = opts;
  await page.goto('/accounts');
  await page.click('text=+ Add Account');
  await expect(page.locator('#accountModal')).toBeVisible();
  await page.fill('#acc_name', name);
  await page.selectOption('#acc_kind', kind);
  if (kind === 'savings') {
    await page.fill('#acc_opening', String(opening));
    await page.fill('#acc_start', start);
  } else {
    if (dueDay !== '') await page.fill('#acc_due', String(dueDay));
    if (kind === 'paylater') {
      await page.selectOption('#acc_bnpl', bnpl);
      await page.selectOption('#acc_offset', String(offset));
    }
    if (limit !== '') await page.fill('#acc_limit', String(limit));
    if (opening) await page.fill('#acc_opening', String(opening));
  }
  await page.click('button:has-text("Save Account")');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(name);
}

async function switchAccount(page, name) {
  await page.selectOption('select[name="account_id"]', { label: name });
  // The select auto-submits a form; wait for the resulting navigation.
  await page.waitForLoadState('load');
  await page.waitForTimeout(200);
}

async function addTransaction(page, opts) {
  const { account, type = 'expense', amount, category, date, description, months, repayMode, cashPrice } = opts;
  await page.goto('/transactions');
  await page.selectOption('#addRecordForm select[name="account_id"]', { label: account });
  await page.selectOption('#addRecordForm select[name="type"]', type);
  await page.fill('#addRecordForm input[name="amount"]', String(amount));
  await page.selectOption('#addRecordForm select[name="category_id"]', { label: category });
  await page.fill('#addRecordForm input[name="date"]', date);
  await page.fill('#addRecordForm input[name="description"]', description);
  if (repayMode) {
    await page.selectOption('#repay_mode', repayMode);
    if (repayMode === 'installments' && months) {
      await page.fill('#months', String(months));
    }
    if (cashPrice !== undefined) {
      await page.fill('#cash_price', String(cashPrice));
    }
  }
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('#addRecordForm button[type="submit"]'),
  ]);
}

async function accountBalance(page) {
  // Reads the "Balance"/"Outstanding" value on the dashboard for the active account.
  const text = await page.locator('body').innerText();
  return text;
}

// The app uses a custom confirmation dialog (#appDialog) for destructive forms
// marked with data-confirm. Accept it after triggering the action.
async function confirmDialog(page) {
  await expect(page.locator('#appDialog')).toBeVisible();
  await page.click('#appDialogAccept');
}

module.exports = { login, logout, createAccount, switchAccount, addTransaction, accountBalance, confirmDialog };
