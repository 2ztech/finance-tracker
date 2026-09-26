const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

test.describe('Mobile layout', () => {
  test('no horizontal overflow and sidebar works on a phone viewport', async ({ page }) => {
    await login(page);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, 'dashboard horizontal overflow').toBeLessThanOrEqual(2);

    // Open the mobile sidebar.
    await page.locator('header button').first().click();
    await expect(page.locator('#sidebar')).toBeVisible();

    // Navigate.
    await page.locator('#sidebar a[href="/transactions"]').click();
    await expect(page).toHaveURL(/transactions/);

    const overflow2 = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow2, 'transactions horizontal overflow').toBeLessThanOrEqual(2);
  });
});
