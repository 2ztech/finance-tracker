const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const zlib = require('node:zlib');

function pngChunk(type, data) {
  const typeBytes = Buffer.from(type);
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const crcInput = Buffer.concat([typeBytes, data]);
  let crc = 0xffffffff;
  for (const byte of crcInput) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit++) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
  }
  const checksum = Buffer.alloc(4);
  checksum.writeUInt32BE((crc ^ 0xffffffff) >>> 0);
  return Buffer.concat([length, typeBytes, data, checksum]);
}

function makePng(width = 64, height = 64) {
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8; // bit depth
  header[9] = 6; // RGBA
  const pixels = Buffer.alloc(height * (1 + width * 4));
  for (let y = 0; y < height; y++) {
    const row = y * (1 + width * 4);
    pixels[row] = 0;
    for (let x = 0; x < width; x++) {
      const offset = row + 1 + x * 4;
      pixels[offset] = 49; pixels[offset + 1] = 85; pixels[offset + 2] = 200; pixels[offset + 3] = 255;
    }
  }
  return Buffer.concat([
    Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
    pngChunk('IHDR', header), pngChunk('IDAT', zlib.deflateSync(pixels)), pngChunk('IEND', Buffer.alloc(0)),
  ]);
}

test.describe('Category and account icons', () => {
  test.beforeEach(async ({ page }) => login(page));

  test('categories can choose a built-in icon, upload one, and edit the icon', async ({ page }) => {
    await page.goto('/categories');
    await page.fill('#category_name', 'E2E Icon Category');
    await page.click('[data-icon-key="shopping"]');
    await expect(page.locator('#category_icon_key')).toHaveValue('shopping');
    await page.setInputFiles('#category_icon_file', { name: 'category.png', mimeType: 'image/png', buffer: makePng() });
    await expect(page.locator('#category_icon_preview')).toBeVisible();
    await page.click('#categoryForm button[type="submit"]');

    const row = page.locator('.group', { hasText: 'E2E Icon Category' });
    await expect(row.locator('img')).toBeVisible();
    await row.getByRole('button', { name: 'Edit E2E Icon Category' }).click();
    await page.click('[data-icon-key="pet"]');
    await expect(page.locator('#category_clear_icon')).toHaveValue('1');
    await page.click('#categoryForm button[type="submit"]');

    await page.locator('.group', { hasText: 'E2E Icon Category' }).getByRole('button', { name: 'Edit E2E Icon Category' }).click();
    await expect(page.locator('[data-icon-key="pet"]')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('#category_icon_preview')).toBeHidden();
  });

  test('accounts can upload a square PNG and keep it when edited', async ({ page }) => {
    await page.goto('/accounts');
    await page.click('text=+ Add Account');
    await page.fill('#acc_name', 'E2E Icon Account');
    await page.setInputFiles('#acc_icon_file', { name: 'account.png', mimeType: 'image/png', buffer: makePng() });
    await expect(page.locator('#acc_icon_preview')).toBeVisible();
    await page.click('#accountModal button[type="submit"]');

    const row = page.locator('.rounded-xl.border', { hasText: 'E2E Icon Account' }).first();
    await expect(row.locator('img')).toBeVisible();
    await row.getByTitle('Edit').click();
    await expect(page.locator('#acc_icon_preview')).toBeVisible();
    await expect(page.locator('#acc_clear_icon_wrap')).toBeVisible();
    await page.check('#acc_clear_icon');
    await page.click('#accountModal button[type="submit"]');
    await expect(page.locator('.rounded-xl.border', { hasText: 'E2E Icon Account' }).first().locator('img')).toHaveCount(0);
  });

  test('wrong icon dimensions are rejected in the picker', async ({ page }) => {
    await page.goto('/categories');
    const input = page.locator('#category_icon_file');
    await input.setInputFiles({ name: 'tiny.png', mimeType: 'image/png', buffer: makePng(1, 1) });
    await expect(input).toHaveValue('');
    await expect(page.locator('#category_icon_preview')).toBeHidden();
  });
});
