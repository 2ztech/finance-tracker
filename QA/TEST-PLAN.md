# Expenzz — QA Test Plan

_Last updated: 2026-09-26_

## 1. Application architecture

| Layer | Location | Notes |
|---|---|---|
| Entry point / router | `public/index.php` | Session bootstrap, CSRF validation, route dispatch, inline handlers for accounts, transfers, bills, plans, CSV, backup/restore, duplicates. |
| Domain logic | `src/*.php` | Flat, non-namespaced classes (no Composer). |
| Views | `templates/*.php` | Server-rendered; `layout.php` provides chrome + theme. |
| Assets | `public/` | `tailwind.min.css` (compiled), `vendor/chart.umd.min.js` (vendored). |
| Data | SQLite `data/finance.db` | Schema created on demand by `Database::initSchema()`. |

### Key classes
- `Database` — PDO/SQLite singleton, schema/migrations, `path()` (env-overridable), `reset()`.
- `Account` — savings/credit/paylater CRUD, balances, net worth, active account.
- `Expense` — transactions, commitments (recurring), totals, category breakdown, month processing.
- `Transfer` — internal movements and bill payments.
- `PaylaterPlan` — BNPL plans, instalment schedules, refunds, settlement, reconciliation.
- `Bill` — bill/statement generation for paylater and credit accounts, payments.
- `Budget`, `Category`, `QuickTemplate`, `Settings`, `Auth`, `Csrf`, `Backup`, `Helper`, `AppLog`, `Maintenance`.

## 2. Testable modules

Business logic is concentrated in `src/` and is directly unit-testable. The router
(`public/index.php`) holds request-handling logic and is covered by browser E2E.

## 3. User workflows

1. **Auth** — first-run setup, login, logout, protected routes.
2. **Accounts** — create/edit/archive/delete, switch active account, per-account scoping.
3. **Transactions** — add income/expense, edit, delete, search/filter, month navigation.
4. **Transfers** — move money between accounts; delete reverses.
5. **Bills / BNPL** — purchase → plan → instalments → partial/full payment, refund, settle.
6. **Credit** — purchases → monthly statement → payment → available credit.
7. **Budgets** — per-category caps aggregated across accounts.
8. **Recurring** — commitments auto-posted; edit/delete propagate; due-day clamping.
9. **Data** — CSV export/import (dedup), database backup/restore, duplicate review.
10. **Settings** — credentials, account management, logs.

## 4. Critical financial rules (invariants)

- **Savings balance** = opening + income − expense + transfers in − transfers out.
- **Liability outstanding** = opening + purchases − refunds − payments.
- **Net worth** = Σ savings − Σ liabilities.
- **Transfer** never affects income/expense or net worth: `source_effect + destination_effect = 0`.
- **Deleting a transaction** reverses its financial effect exactly.
- **Liability payment invariant**: `original − payments + new_charges = remaining`.
- **Refunds** reduce remaining but never make an instalment/plan negative.
- **Account isolation**: an Account A transaction cannot change Account B's scoped balance.
- **Import idempotency**: importing the same CSV twice creates no duplicates.
- **Backup invariant**: restoring a valid backup reproduces the backed-up state.
- **Money** is rounded to 2 decimal places; sums must not drift.

## 5. High-risk areas (post multi-account release)

- Account-scoped balance maths and the savings/liability sign conventions.
- Transfers (double-sided effect, deletion reversal).
- Paylater instalment schedules: cycle vs per-purchase, first-due offset, month clamping, rounding.
- Bill aggregation and FIFO payment allocation; partial and repeated payments.
- Overpayment / refund behaviour on liabilities.
- CSV import dedup and account mapping.
- Backup/restore integrity (must never corrupt or accept invalid DBs).
- CSRF and session/auth enforcement.

## 6. Existing testing gaps (pre-QA)

- No automated tests of any kind.
- No isolated test database; manual testing risked the real DB.
- No regression protection for account/transfer/bill/paylater logic.

## 7. Proposed automated test architecture

- **Backend:** PHPUnit 11 (PHAR, no Composer) with a custom autoloader.
  - `tests/Support/TestDb.php` creates a fresh SQLite database in the system
    temp dir per test and points the app at it via `FINANCE_DB_PATH`.
  - `tests/Unit/*` cover domain classes and invariants.
- **E2E:** Playwright against a disposable PHP built-in server bound to
  `data/test/e2e.db`, reset before each run.
  - `tests/e2e/*` cover real workflows through the browser.
- **CI:** GitHub Actions runs CSS build, PHP lint, backend tests, then E2E.
- **Financial safety:** assertions compare to 2dp; invariant tests assert
  properties rather than brittle totals.

## 8. Isolation guarantees

- Tests **never** read or write `data/finance.db`.
- `FINANCE_DB_PATH` and `FINANCE_LOG_DIR` are the only hooks added to the app;
  both default to the original production paths (behaviour-preserving).
