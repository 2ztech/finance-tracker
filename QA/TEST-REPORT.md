# Expenzz — QA Test Report

## Summary

| Field | Value |
|---|---|
| Test date | 2026-09-26 |
| Commit under test | `ff84719` (application) + this QA commit |
| Environment | Ubuntu 24.04, PHP 8.3.6 (pdo_sqlite), Node 24, Chromium (Playwright headless shell) |
| Backend framework | PHPUnit 11.5.56 (PHAR, `tools/phpunit.phar`) |
| E2E framework | Playwright 1.63 |
| Backend tests | **83** (183 assertions) |
| E2E tests | **18** (17 desktop + 1 mobile) |
| Total | **101** |
| Passed | **101** |
| Failed | **0** |
| Skipped | **0** |

```
Backend:  OK (83 tests, 183 assertions)
E2E:      18 passed (45.7s)
```

## How to reproduce

```bash
npm install
npx playwright install --with-deps chromium
npm run build:css
npm test
```

## Coverage by area

| Area | Backend | E2E |
|---|---|---|
| Accounts (CRUD, archive, delete guard, balance, net worth, isolation, active) | ✔ | ✔ |
| Transactions (add/edit/delete, scoping, filtering, rounding) | ✔ | ✔ |
| Transfers (effects, reversal, rejection rules, invariant) | ✔ | ✔ |
| Bills (paylater + credit, partial/full/repeat, status) | ✔ | ✔ |
| Pay-later / BNPL (schedule, cycle vs per-purchase, rounding, refund, settle, reconcile, clamping) | ✔ | ✔ |
| Credit statements (amount, due date, payment, overpayment) | ✔ | ✔ (dashboard) |
| Budgets (CRUD, cross-account aggregation) | ✔ | — |
| Recurring (processing, idempotency, propagation, clamping, scoping) | ✔ | — |
| CSV export/import (Account column, dedup idempotency, malformed input) | — | ✔ |
| Backup/restore (valid, invalid rejection) | ✔ | ✔ |
| Auth / CSRF / rate limit | ✔ | ✔ |
| UI smoke (console errors, JS exceptions, overflow) / mobile | — | ✔ |

## Bugs discovered and fixed

### 1. Login page threw a JavaScript error (`tailwind is not defined`)
- **Reproduction:** open `/login`; browser console shows `pageerror: tailwind is not defined`.
- **Expected:** no console errors.
- **Actual:** `login.php` still contained a `tailwind.config = {…}` inline script left
  over from the Tailwind CDN → static-CSS migration. With the CDN gone, `tailwind`
  is undefined and the assignment throws.
- **Root cause:** incomplete refactor; the config block was removed from `layout.php`
  but not `login.php`.
- **Fix:** removed the obsolete `<script>tailwind.config…</script>` from `templates/login.php`.
- **Regression test:** `tests/e2e/visual.spec.js` asserts zero console/page errors
  during login and across all main pages.

### 2. Transfers accepted invalid inputs at the model layer
- **Reproduction:** call `Transfer::create()` with the same source/destination, a
  zero/negative amount, or a nonexistent account.
- **Expected:** rejected.
- **Actual:** rows were inserted (only the HTTP route guarded these).
- **Root cause:** validation lived solely in `public/index.php`.
- **Fix:** `Transfer::create()` now validates amount, distinct accounts and account
  existence; returns `0` on failure. Route guard retained.
- **Regression test:** `tests/Unit/TransferTest.php` (same/zero/negative/nonexistent).

### 3. `Account::delete()` could orphan transactions when called directly
- **Reproduction:** call `Account::delete($id)` for an account that has transactions.
- **Expected:** refuse.
- **Actual:** the row was deleted and `transactions.account_id` set to NULL.
- **Root cause:** the "prevent deletion when transactions exist" rule existed only in
  the Accounts page handler.
- **Fix:** `Account::delete()` now refuses when transactions reference the account.
- **Regression test:** `tests/Unit/AccountTest.php::testDeleteBlockedWhenTransactionsExist`.

## Corrected (test-side, not application bugs)

- Initial `PaylaterPlanTest` / `TransactionTest` expectations were wrong
  (mis-computed remaining balance sign / offence vs credit); corrected to the
  verified behaviour. The application was correct.

## Financial invariants verified

- Transfer: `source_effect + destination_effect = 0`.
- Delete reverses a transaction's financial effect.
- Liability payment invariant: `original − payments + new_charges = remaining`.
- Refund never yields negative remaining instalments.
- Account isolation (A never affects B's scoped balance).
- CSV import is idempotent.
- Backup reproduces the backed-up database state.
- Rounding: `0.10 + 0.20 = 0.30`; splitting `100 / 3` sums back to `100.00`.

## Exploratory testing (hostile inputs)

| Probe | Result |
|---|---|
| POST without CSRF token | `403` (blocked) |
| `/dashboard?month=2026-99` | `200`, falls back to current month |
| Add transaction amount `0` | not inserted |
| Add transaction amount `-5` | not inserted |
| Delete nonexistent transaction id | `302`, no crash |
| Self-transfer (same account) | no rows created |
| Removed `settings/clean-duplicates` route | `404` |
| PHP warnings/errors during probes | none |

## Known issues / out of scope

- **PDF/print** output is not automated (browser print dialog); PDF is covered only by
  the presence of the export control and balance inputs.
- **Credit statements** use a simplified month-based cycle (cycle purchases − refunds);
  no carried-balance compounding from prior statements.
- **Recurring auto-entries resurrect** if deleted manually (intentional; documented).
- **No maximum amount** is enforced (only `> 0`).
- **Login page fonts** load from Google Fonts CDN (offline falls back gracefully).
- **E2E runs Chromium only**; mobile coverage is a single layout smoke test.
- **Login rate-limit** is unit-tested at the model level, not via full HTTP lockout timing.
- **Concurrency** (simultaneous writes) is not tested.

## Test artifacts

- HTML report: `QA/playwright-report/` (on failure, CI uploads it).
- Screenshots: `QA/screenshots/` (desktop page captures).
- Traces/videos: `test-results/` (retained on failure).
- App logs: `data/logs/app-YYYY-MM-DD.log` (git-ignored).
