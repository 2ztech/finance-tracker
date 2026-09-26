const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const fs = require('fs');

const IGNORE = [/fonts\.googleapis/, /fonts\.gstatic/, /favicon/];

test.describe('UI smoke / console health', () => {
  test('key pages render with no console errors, JS exceptions or overflow', async ({ page }) => {
    const problems = [];
    page.on('console', (m) => {
      if (m.type() === 'error') problems.push('console: ' + m.text());
    });
    page.on('pageerror', (e) => problems.push('pageerror: ' + e.message));

    await login(page);

    // Use the transaction fixture when the full suite has already created it,
    // so the dashboard screenshot exercises non-empty chart/table states.
    const accountSelect = page.locator('select[name="account_id"]');
    if (await accountSelect.count()) {
      const transactionAccount = accountSelect.locator('option', { hasText: 'TEST_TX' });
      if (await transactionAccount.count()) {
        await page.selectOption('select[name="account_id"]', await transactionAccount.first().getAttribute('value'));
      }
    }

    const pages = ['/dashboard', '/transactions', '/recurring', '/budgets', '/categories', '/accounts', '/bills', '/settings'];
    fs.mkdirSync('QA/screenshots', { recursive: true });

    for (const p of pages) {
      await page.goto(p);
      await page.waitForLoadState('networkidle');

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `horizontal overflow on ${p}`).toBeLessThanOrEqual(2);

      await page.evaluate(() => {
        document.body.style.height = 'auto';
        document.body.style.overflow = 'visible';
        const main = document.querySelector('main');
        const content = main && main.querySelector(':scope > div');
        if (main) main.style.overflow = 'visible';
        if (content) {
          content.style.height = 'auto';
          content.style.overflow = 'visible';
          content.style.flex = 'none';
        }
      });

      await page.screenshot({ path: 'QA/screenshots/' + p.replace(/\//g, '_') + '.png', fullPage: true });
    }

    const real = problems.filter((p) => !IGNORE.some((re) => re.test(p)));
    expect(real, real.join('\n')).toEqual([]);
  });
});
