const { test, expect } = require('@playwright/test');
const { login, createAccount, switchAccount, confirmDialog } = require('./helpers');
const fs = require('fs');
const os = require('os');

test.describe('Backup & restore', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('restore returns the database to the backed-up state', async ({ page }) => {
    await createAccount(page, { name: 'TEST_BAK_KEEP', kind: 'savings', opening: '777', start: '2026-01' });

    await page.goto('/settings');
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.click('a[href="/settings/backup"]'),
    ]);
    // Playwright's download.path() has no extension; the restore route requires
    // a .db/.sqlite name, so persist it with a real filename first.
    const backupPath = os.tmpdir() + '/e2e-backup-' + Date.now() + '.db';
    await download.saveAs(backupPath);

    // Change state after the backup.
    await createAccount(page, { name: 'TEST_BAK_REMOVE', kind: 'savings', opening: '1', start: '2026-01' });
    await page.goto('/accounts');
    await expect(page.locator('body')).toContainText('TEST_BAK_REMOVE');

    // Restore the backup.
    await page.goto('/settings');
    await page.setInputFiles('input[name="db_file"]', backupPath);
    await page.click('button:has-text("Restore")');
    await confirmDialog(page);
    await page.waitForURL(/settings\?msg=/);
    await expect(page.locator('body')).toContainText('restored successfully');

    await page.goto('/accounts');
    await expect(page.locator('body')).toContainText('TEST_BAK_KEEP');
    await expect(page.locator('body')).not.toContainText('TEST_BAK_REMOVE');
  });

  test('invalid restore file is rejected without changing data', async ({ page }) => {
    await createAccount(page, { name: 'TEST_BAK_SAFE', kind: 'savings', opening: '10', start: '2026-01' });
    const bad = os.tmpdir() + '/not-a-db-' + Date.now() + '.db';
    fs.writeFileSync(bad, 'definitely not a sqlite database');

    await page.goto('/settings');
    await page.setInputFiles('input[name="db_file"]', bad);
    await page.click('button:has-text("Restore")');
    await confirmDialog(page);
    await page.waitForURL(/settings\?msg=/);
    await expect(page.locator('body')).toContainText(/Invalid or corrupt/);

    await page.goto('/accounts');
    await expect(page.locator('body')).toContainText('TEST_BAK_SAFE');
  });
});
