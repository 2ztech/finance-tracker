// Playwright configuration for the Expenzz finance tracker.
// Runs against an isolated SQLite database (data/test/e2e.db) started by a
// disposable PHP built-in server. The production data/finance.db is untouched.
const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

const dbPath = path.join(__dirname, 'data', 'test', 'e2e.db');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 45_000,
  expect: { timeout: 7_000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'QA/playwright-report', open: 'never' }],
  ],
  use: {
    baseURL: 'http://127.0.0.1:8099',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'desktop',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: /mobile\.spec\.js/,
    },
    {
      name: 'mobile',
      use: { ...devices['Pixel 5'] },
      testMatch: /mobile\.spec\.js/,
    },
  ],
  webServer: {
    command: 'php tests/e2e/reset-db.php && php -S 127.0.0.1:8099 -t public',
    url: 'http://127.0.0.1:8099/login',
    reuseExistingServer: false,
    timeout: 30_000,
    env: { FINANCE_DB_PATH: dbPath },
  },
});
