const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction, currentDate } = require('./helpers');

test.describe('Analytics page', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('nav groups render and Analytics is under Personal', async ({ page }) => {
    await page.goto('/dashboard');
    const sidebar = page.locator('#sidebar');
    await expect(sidebar.getByText('Personal', { exact: true })).toBeVisible();
    await expect(sidebar.getByText('Workspace', { exact: true })).toBeVisible();
    await expect(sidebar.getByText('Manage', { exact: true })).toBeVisible();
    await expect(sidebar.locator('a[href="/analytics"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/budgets"]')).toBeVisible();
    await expect(sidebar.locator('a[href="/accounts"]')).toBeVisible();
  });

  test('analytics page renders default widgets and filter bar', async ({ page }) => {
    await createAccount(page, { name: 'ANL_TX', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'ANL_TX');
    await addTransaction(page, {
      account: 'ANL_TX', type: 'expense', amount: '42.50',
      category: 'Food & Dining', date: currentDate(), description: 'analytics-test-grab',
    });

    await page.goto('/analytics');
    await expect(page).toHaveURL(/\/analytics/);
    await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible();
    await expect(page.locator('#analyticsRange')).toHaveValue('last_6');
    await expect(page.locator('#analyticsInstitution')).toHaveValue('0');
    await expect(page.locator('.analytics-widget')).toHaveCount(5);
    await expect(page.locator('canvas[data-chart]').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Income vs Expenses');
    await expect(page.locator('body')).toContainText('Spending by Category');
  });

  test('add widget modal disables widgets already on the page', async ({ page }) => {
    await page.goto('/analytics?edit=1');
    await page.click('#analyticsAddWidget');

    for (const key of ['income_expense', 'spending_category', 'spending_institution', 'top_merchants', 'credit_utilization']) {
      const option = page.locator(`.analytics-add-option[data-key="${key}"]`);
      await expect(option).toBeDisabled();
      await expect(option).toContainText('Added');
    }
    await expect(page.locator('.analytics-add-option[data-key="net_worth"]')).toBeEnabled();

    await page.click('.analytics-add-option[data-key="net_worth"]');
    await page.waitForLoadState('networkidle');
    await page.click('#analyticsAddWidget');
    await expect(page.locator('.analytics-add-option[data-key="net_worth"]')).toBeDisabled();
    await expect(page.locator('.analytics-add-option[data-key="net_worth"]')).toContainText('Added');

    // Duplicate action was intentionally removed.
    await expect(page.locator('[data-action="duplicate"]')).toHaveCount(0);

    await page.click('#analyticsResetLayout');
    await page.click('#appDialogAccept');
    await page.waitForLoadState('networkidle');
  });

  test('institution filter updates data', async ({ page }) => {
    await createAccount(page, { name: 'ANL_A', kind: 'savings', opening: '0', start: '2026-01' });
    await createAccount(page, { name: 'ANL_B', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'ANL_A');
    await addTransaction(page, {
      account: 'ANL_A', type: 'expense', amount: '99',
      category: 'Groceries', date: currentDate(), description: 'filter-a',
    });
    await switchAccount(page, 'ANL_B');
    await addTransaction(page, {
      account: 'ANL_B', type: 'expense', amount: '11',
      category: 'Groceries', date: currentDate(), description: 'filter-b',
    });

    await page.goto('/analytics');
    // All accounts: institution mode, includes both accounts' spending.
    let payload = await page.locator('.analytics-widget[data-widget-key="spending_institution"] canvas').getAttribute('data-chart');
    let data = JSON.parse(payload);
    expect(data.data.mode).toBe('institution');
    expect(data.data.items.length).toBeGreaterThanOrEqual(2);
    const allTotal = data.data.total;

    // Filter to ANL_A only: switches to section mode with ANL_A's 99.
    await page.selectOption('#analyticsInstitution', { label: 'ANL_A' });
    await page.waitForLoadState('networkidle');
    payload = await page.locator('.analytics-widget[data-widget-key="spending_institution"] canvas').getAttribute('data-chart');
    data = JSON.parse(payload);
    expect(data.data.mode).toBe('section');
    expect(data.data.items.length).toBe(1);
    expect(data.data.items[0].name).toBe('ANL_A');
    expect(data.data.total).toBeCloseTo(99, 1);
    expect(allTotal).toBeGreaterThanOrEqual(110);
  });

  test('customize mode: add, resize, reorder, remove, persistence', async ({ page }) => {
    await page.goto('/analytics?edit=1');
    await expect(page.locator('body')).toContainText('Customize mode');

    // Add Net Worth widget (default size full = span-12).
    await page.click('#analyticsAddWidget');
    await expect(page.locator('#analyticsAddModal')).toBeVisible();
    await page.click('.analytics-add-option[data-key="net_worth"]');
    await page.waitForLoadState('networkidle');
    const netWorthWidget = page.locator('.analytics-widget[data-widget-key="net_worth"]');
    await expect(netWorthWidget).toHaveCount(1);
    await expect(netWorthWidget).toHaveClass(/span-12/);
    await expect(page.locator('.analytics-widget')).toHaveCount(6);

    // Resize it: full -> half.
    await netWorthWidget.locator('.analytics-ctl[data-action="resize"]').click();
    await page.waitForLoadState('networkidle');
    const resizedWidget = page.locator('.analytics-widget[data-widget-key="net_worth"]');
    await expect(resizedWidget).toHaveClass(/span-6/);

    // Move it up one position.
    const firstBefore = await page.locator('.analytics-widget').first().getAttribute('data-widget-key');
    await resizedWidget.locator('.analytics-ctl[data-action="move"][data-dir="-1"]').click();
    await page.waitForLoadState('networkidle');
    // It should have moved from position 6 to position 5 — not to position 1.
    expect(await page.locator('.analytics-widget').first().getAttribute('data-widget-key')).toBe(firstBefore);

    // Remove it.
    await page.locator('.analytics-widget[data-widget-key="net_worth"] .analytics-ctl[data-action="remove"]').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.analytics-widget')).toHaveCount(5);
    await expect(page.locator('.analytics-widget[data-widget-key="net_worth"]')).toHaveCount(0);

    // Persistence: navigate away and back, the added widget stays removed.
    await page.goto('/dashboard');
    await page.goto('/analytics');
    await expect(page.locator('.analytics-widget[data-widget-key="net_worth"]')).toHaveCount(0);
    await expect(page.locator('.analytics-widget')).toHaveCount(5);

    // Reset restores defaults (5 widgets, first is full-width).
    await page.goto('/analytics?edit=1');
    await page.click('#analyticsResetLayout');
    await page.click('#appDialogAccept');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.analytics-widget')).toHaveCount(5);
    await expect(page.locator('.analytics-widget').first()).toHaveClass(/span-12/);
  });

  test('empty layout state when all widgets removed', async ({ page }) => {
    await page.goto('/analytics?edit=1');
    // Remove all 5 default widgets.
    for (let i = 0; i < 5; i++) {
      await page.locator('.analytics-widget').first().locator('.analytics-ctl[data-action="remove"]').click();
      await page.waitForLoadState('networkidle');
    }
    await expect(page.locator('body')).toContainText('No widgets yet');
    // Restore for other tests.
    await page.click('#analyticsAddWidget');
    await page.click('.analytics-add-option[data-key="income_expense"]');
    await page.waitForLoadState('networkidle');
    await page.click('#analyticsResetLayout');
    await page.click('#appDialogAccept');
    await page.waitForLoadState('networkidle');
  });
});
