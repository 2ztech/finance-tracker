const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, addTransaction } = require('./helpers');

function dataRows(csvText) {
  return csvText.trim().split('\n').filter((l) => l.trim() !== '').length - 1;
}

test.describe('CSV import/export', () => {
  test('export includes Account column and re-import is idempotent', async ({ page }) => {
    await login(page);
    await createAccount(page, { name: 'TEST_CSV', kind: 'savings', opening: '0', start: '2026-01' });
    await switchAccount(page, 'TEST_CSV');
    await addTransaction(page, {
      account: 'TEST_CSV', type: 'expense', amount: '13.37',
      category: 'Groceries', date: '2026-09-15', description: 'csv-e2e-row',
    });

    await page.goto('/settings');
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.click('a[href="/settings/export"]'),
    ]);
    const filePath = await download.path();
    const fs = require('fs');
    const before = fs.readFileSync(filePath, 'utf8');
    expect(before.split('\n')[0]).toContain('Account');
    const beforeRows = dataRows(before);

    // Re-import the exact same file: dedup must prevent new rows.
    await page.setInputFiles('input[name="csv_file"]', filePath);
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('button:has-text("Upload & Import")'),
    ]);
    await expect(page.locator('body')).toContainText('imported successfully');

    const [download2] = await Promise.all([
      page.waitForEvent('download'),
      page.click('a[href="/settings/export"]'),
    ]);
    const after = fs.readFileSync(await download2.path(), 'utf8');
    expect(dataRows(after)).toBe(beforeRows);
  });

  test('importing malformed data does not crash and reports status', async ({ page }) => {
    await login(page);
    const fs = require('fs');
    const os = require('os');
    const path = os.tmpdir() + '/bad-' + Date.now() + '.csv';
    fs.writeFileSync(path, 'not,a,valid,csv\njust,garbage,here\n');
    await page.goto('/settings');
    await page.setInputFiles('input[name="csv_file"]', path);
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('button:has-text("Upload & Import")'),
    ]);
    // App must respond with a status message, not a 500/blank page.
    await expect(page.locator('body')).toContainText(/imported successfully|Error importing data/);
  });
});
