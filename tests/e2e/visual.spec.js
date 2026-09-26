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

    const pages = ['/dashboard', '/transactions', '/recurring', '/budgets', '/categories', '/accounts', '/settings'];
    fs.mkdirSync('QA/screenshots', { recursive: true });

    for (const p of pages) {
      await page.goto(p);
      await page.waitForLoadState('networkidle');

      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `horizontal overflow on ${p}`).toBeLessThanOrEqual(2);

      await page.screenshot({ path: 'QA/screenshots/' + p.replace(/\//g, '_') + '.png', fullPage: true });
    }

    const real = problems.filter((p) => !IGNORE.some((re) => re.test(p)));
    expect(real, real.join('\n')).toEqual([]);
  });
});
