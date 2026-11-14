const { test, expect } = require('@playwright/test');
const { login, createAccount } = require('./helpers');

test.describe('Institution templates', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  async function openChooser(page) {
    await page.goto('/accounts');
    await page.click('text=+ Add Account');
    await expect(page.locator('#templateModal')).toBeVisible();
  }

  async function chooseProducts(page, names) {
    for (const name of names) {
      await page.locator('#wiz_body label', { hasText: name }).locator('input[type="checkbox"]').check();
    }
    await page.click('#wiz_next');
  }

  async function next(page) {
    await page.click('#wiz_next');
  }

  async function fillMoney(page, value) {
    await page.fill('#wiz_q_input', value);
    await next(page);
  }

  test('Atome creates one account with a Card and a PayLater section, each with its own limit', async ({ page }) => {
    await openChooser(page);
    await page.click('[data-template-key="atome"]');
    await expect(page.locator('#wizardModal')).toBeVisible();

    // Products are unchecked by default; pick both.
    await chooseProducts(page, ['Atome Card', 'Atome PayLater']);

    // Card: name (prefilled), credit limit, bill date.
    await next(page);
    await fillMoney(page, '1000');
    await page.fill('#wiz_q_input', '16');
    await expect(page.locator('#wiz_bill_due')).toContainText('26');
    await next(page);

    // PayLater: name, credit limit, info.
    await next(page);
    await fillMoney(page, '500');
    await next(page);

    await expect(page.locator('#wiz_body')).toContainText('Atome');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#wiz_create'),
    ]);

    await expect(page.locator('[data-institution-card]', { hasText: 'Atome' })).toBeVisible();
    await expect(page.locator('[data-section-row]', { hasText: 'Atome Card' })).toContainText('RM 1,000.00');
    await expect(page.locator('[data-section-row]', { hasText: 'Atome Card' })).toContainText('RM 1,000.00 available');
    await expect(page.locator('[data-section-row]', { hasText: 'Atome PayLater' })).toContainText('RM 500.00');
    await expect(page.locator('[data-section-row]', { hasText: 'Atome Card' })).toContainText('due 26');

    // Verify the stored section settings.
    await page.locator('[data-section-row]', { hasText: 'Atome Card' }).locator('button[title="Edit"]').click();
    await expect(page.locator('#acc_stmt')).toHaveValue('15');
    await expect(page.locator('#acc_due')).toHaveValue('26');
    await expect(page.locator('#acc_limit')).toHaveValue('1000');
    await page.keyboard.press('Escape');
  });

  test('Grab creates Wallet + Postpaid sections from one account', async ({ page }) => {
    await openChooser(page);
    await page.click('[data-template-key="grab"]');
    await expect(page.locator('#wizardModal')).toBeVisible();

    await chooseProducts(page, ['GrabPay Wallet', 'Grab PayLater Postpaid']);

    // Wallet: name, balance, main.
    await next(page);
    await fillMoney(page, '25');
    await page.locator('#wiz_body input[name="wiz_main"][value="1"]').check();
    await next(page);

    // Postpaid: name, credit limit, info.
    await next(page);
    await fillMoney(page, '300');
    await next(page);

    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#wiz_create'),
    ]);

    const grab = page.locator('[data-institution-card]', { hasText: 'Grab' });
    await expect(grab).toBeVisible();
    await expect(grab.locator('[data-section-row]', { hasText: 'GrabPay Wallet' })).toContainText('MAIN');
    await expect(grab.locator('[data-section-row]', { hasText: 'Grab PayLater Postpaid' })).toContainText('due 7');
    await expect(grab.locator('[data-section-row]')).toHaveCount(2);
  });

  test('TikTok offers Wallet, PayLater and Personal Financing sections', async ({ page }) => {
    await openChooser(page);
    await page.click('[data-template-key="tiktok"]');
    await expect(page.locator('#wiz_body')).toContainText('Which TikTok products do you use?');
    await expect(page.locator('#wiz_body input[type="checkbox"]')).toHaveCount(3);

    await chooseProducts(page, ['TikTok Wallet', 'TikTok PayLater']);

    // Wallet: name, balance, main (default No).
    await next(page);
    await fillMoney(page, '15');
    await next(page);

    // PayLater: name, optional limit, statement choice.
    await next(page);
    await fillMoney(page, '500');
    await page.locator('#wiz_body input[name="wiz_choice"][value="1"]').check();
    await next(page);

    await expect(page.locator('#wiz_body')).toContainText('TikTok Wallet');
    await expect(page.locator('#wiz_body')).toContainText('TikTok PayLater');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#wiz_create'),
    ]);

    const tiktok = page.locator('[data-institution-card]', { hasText: 'TikTok' });
    await expect(tiktok.locator('[data-section-row]')).toHaveCount(2);
    await expect(tiktok.locator('[data-section-row]', { hasText: 'TikTok PayLater' })).toContainText('due 10');
    await expect(tiktok.locator('[data-section-row]', { hasText: 'TikTok PayLater' })).toContainText('RM 500.00 available');
    await expect(tiktok.locator('[data-section-row]', { hasText: 'Personal Financing' })).toHaveCount(0);

    // The account switcher lists institutions only; sections live in the sidebar submenu.
    const institutionOptions = (await page.locator('select[name="institution_id"] option').allTextContents()).map((text) => text.trim());
    expect(institutionOptions).toContain('TikTok');
    expect(institutionOptions).not.toContain('TikTok Wallet');
    await expect(page.locator('[data-section-nav]', { hasText: 'TikTok Wallet' })).toBeVisible();
    await expect(page.locator('[data-section-nav]', { hasText: 'TikTok PayLater' })).toBeVisible();
  });

  test('Shopee SLoan is a separate financing section', async ({ page }) => {
    await openChooser(page);
    await page.click('[data-template-key="shopee"]');
    await expect(page.locator('#wiz_body input[type="checkbox"]')).toHaveCount(3);
    await chooseProducts(page, ['Shopee SLoan']);

    // SLoan: name, credit limit, info.
    await next(page);
    await fillMoney(page, '5000');
    await next(page);

    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#wiz_create'),
    ]);

    const shopee = page.locator('[data-institution-card]', { hasText: 'Shopee' });
    await expect(shopee.locator('[data-section-row]', { hasText: 'Shopee SLoan' })).toContainText('Financing');
    await expect(shopee.locator('[data-section-row]', { hasText: 'Shopee SLoan' })).toContainText('RM 5,000.00 available');
    await expect(page.locator('#sidebar a[href="/bills"]')).toBeVisible();
    await expect(page.locator('#sidebar a[href="/financing"]')).toBeVisible();

    await page.goto('/financing');
    await expect(page.locator('body')).toContainText('Shopee SLoan');
  });

  test('Bills tabs separate PayLater and Financing sections', async ({ page }) => {
    await openChooser(page);
    await page.click('[data-template-key="tiktok"]');
    await chooseProducts(page, ['TikTok PayLater', 'TikTok Personal Financing']);

    // PayLater: name, limit, statement choice.
    await page.fill('#wiz_q_input', 'BILL_PAYLATER');
    await next(page);
    await fillMoney(page, '1000');
    await page.locator('#wiz_body input[name="wiz_choice"][value="1"]').check();
    await next(page);

    // Personal Financing: name, limit, info.
    await page.fill('#wiz_q_input', 'BILL_PF');
    await next(page);
    await fillMoney(page, '500');
    await next(page);

    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#wiz_create'),
    ]);

    await page.goto('/bills');
    await expect(page.locator('body')).toContainText('PayLater bills');
    await expect(page.locator('body')).not.toContainText('Financing bills');

    await page.getByRole('link', { name: /BILL_PF/ }).click();
    await expect(page.locator('body')).toContainText('Financing bills');
    await expect(page.locator('body')).not.toContainText('PayLater bills');

    // The PF entry in the sidebar opens the Financing page (not the dashboard).
    await page.locator('[data-section-nav]', { hasText: 'BILL_PF' }).click();
    await expect(page).toHaveURL(/\/financing/);
    await expect(page.locator('body')).toContainText('BILL_PF');
  });

  test('dashboard shows available/limit and usage for the viewed section', async ({ page }) => {
    await page.goto('/accounts');
    await page.click('text=+ Add Account');
    await page.click('#templateModal >> text=Custom account');
    await page.fill('#acc_name', 'DASH_CREDIT');
    await page.selectOption('#acc_kind', 'credit');
    await page.fill('#acc_due', '20');
    await page.fill('#acc_limit', '1500');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#accountModal button[type="submit"]'),
    ]);

    await page.goto('/dashboard');
    const card = page.locator('section[aria-label="Credit limit"]');
    await expect(card).toBeVisible();
    await expect(card).toContainText('RM 1,500.00');
    await expect(card).toContainText('RM 0.00 outstanding');
    await expect(card).toContainText('0% used');

    await page.goto('/transactions');
    await page.selectOption('#addRecordForm select[name="account_id"]', { label: 'DASH_CREDIT' });
    await page.selectOption('#addRecordForm select[name="type"]', 'expense');
    await page.fill('#addRecordForm input[name="amount"]', '500');
    await page.selectOption('#addRecordForm select[name="category_id"]', { label: 'Entertainment' });
    await page.fill('#addRecordForm input[name="date"]', '2026-09-15');
    await page.fill('#addRecordForm input[name="description"]', 'dash-credit');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#addRecordForm button[type="submit"]'),
    ]);

    await page.goto('/dashboard');
    await expect(card).toContainText('RM 1,000.00');
    await expect(card).toContainText('RM 500.00 outstanding');
    await expect(card).toContainText('33% used');
  });

  test('adds a provider section through the section wizard', async ({ page }) => {
    // Create a fresh TikTok via the institution wizard (Wallet + PayLater).
    await openChooser(page);
    await page.click('[data-template-key="tiktok"]');
    await chooseProducts(page, ['TikTok Wallet', 'TikTok PayLater']);
    await next(page); // wallet name
    await fillMoney(page, '10');
    await next(page); // main
    await next(page); // paylater name
    await fillMoney(page, '500');
    await page.locator('#wiz_body input[name="wiz_choice"][value="1"]').check();
    await next(page);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('#wiz_create')]);

    const tiktok = page.locator('[data-institution-card]', { hasText: 'TikTok' }).last();
    await tiktok.locator('button', { hasText: '+ Section' }).click();
    await expect(page.locator('#sectionChooserModal')).toBeVisible();
    await page.locator('#sectionChooserModal [data-section-card]', { hasText: 'TikTok Personal Financing' }).click();

    // Name (prefilled) -> limit -> info -> review.
    await next(page);
    await fillMoney(page, '500');
    await next(page);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('#wiz_create')]);

    const row = page.locator('[data-section-row]', { hasText: 'TikTok Personal Financing' }).first();
    await expect(row).toContainText('Financing');
  });

  test('adds a generic credit card through the section wizard', async ({ page }) => {
    await createAccount(page, { name: 'SEC_CUSTOM', kind: 'savings', opening: '0', start: '2026-01' });

    const institution = page.locator('[data-institution-card]', { hasText: 'SEC_CUSTOM' }).last();
    await institution.locator('button', { hasText: '+ Section' }).click();
    await expect(page.locator('#sectionChooserModal')).toBeVisible();
    await page.locator('#sectionChooserModal [data-section-card]', { hasText: 'Credit card' }).click();

    await page.fill('#wiz_q_input', 'SEC_CC');
    await next(page);
    await fillMoney(page, '1000');
    await page.fill('#wiz_q_input', '15');
    await next(page);
    await page.fill('#wiz_q_input', '26');
    await next(page);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('#wiz_create')]);

    const row = page.locator('[data-section-row]', { hasText: 'SEC_CC' }).first();
    await expect(row).toContainText('Credit card');
    await expect(row).toContainText('due 26');
  });

  test('product checkboxes start unchecked and the search filters providers', async ({ page }) => {
    await openChooser(page);
    const tiktokLogo = page.locator('[data-template-key="tiktok"] img');
    await expect(tiktokLogo).toBeVisible();
    await expect.poll(async () => tiktokLogo.evaluate((el) => el.complete && el.naturalWidth > 0)).toBe(true);

    await page.fill('#templateSearch', 'maybank');
    await expect(page.locator('[data-template-key="maybank"]')).toBeVisible();
    await expect(page.locator('[data-template-key="tiktok"]')).toBeHidden();

    await page.fill('#templateSearch', 'grab');
    await page.click('[data-template-key="grab"]');
    await expect(page.locator('#wiz_body')).toContainText('Which Grab products do you use?');
    await expect(page.locator('#wiz_body input[type="checkbox"]:checked')).toHaveCount(0);
  });

  test('custom still creates a single section', async ({ page }) => {
    await openChooser(page);
    await page.click('#templateModal >> text=Custom account');
    await expect(page.locator('#accountModal')).toBeVisible();
    await page.fill('#acc_name', 'Custom Wallet');
    await page.fill('#acc_opening', '42');
    await page.fill('#acc_start', '2026-01');
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page.click('#accountModal button[type="submit"]'),
    ]);
    await expect(page.locator('body')).toContainText('Custom Wallet');
    await expect(page.locator('[data-section-row]', { hasText: 'Custom Wallet' })).toContainText('RM 42.00');
  });
});
