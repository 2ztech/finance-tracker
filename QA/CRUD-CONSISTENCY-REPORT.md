# Expenzz — CRUD & Cross-Page State Consistency Report

**Date:** 2026-09-26
**Method:** Live UI driving via Playwright (browser MCP) against an isolated
instance (`FINANCE_DB_PATH=data/test/audit.db`), with backend/database
verification (SQLite) and cross-page checks after every mutation.
**Commit under test:** `3a39a0b` + this audit commit.

---

## 1. Dependency matrix (entity → pages/calculations affected)

| Entity | Affected pages / calculations |
|---|---|
| **Account** | Accounts list, sidebar switcher, Dashboard (balance/outstanding/available credit), Net worth, Transactions scoping, Recurring scoping, Bills scoping, Transfers (source/destination), CSV, backup |
| **Transaction** | Transactions list, day/month totals, Dashboard (income/expense/net), Account balance, Category breakdown/chart, Budget progress, CSV |
| **Transfer** | Source balance, Destination balance, both account ledgers, Dashboard, Net worth (unchanged), CSV |
| **Bill (credit/paylater)** | Bills page, statement amount/status, Account outstanding, Available credit, Ledger (payment line), Dashboard |
| **BNPL plan / instalment** | Bills page, instalment list, plan status badge, Account outstanding, Ledger, Dashboard |
| **Budget** | Budgets page, Dashboard budget bar, Category spend |
| **Category** | Categories page, all category selectors (add/edit/quick-template/recurring/budget/import), Transactions list, Dashboard chart, Budget association |
| **Recurring commitment** | Recurring list, generated `[Auto]` transactions, Account balance, Category totals, Budget, Dashboard |
| **CSV / Backup** | Whole application state |

## 2. CRUD coverage matrix

| Module | Create | Read | Edit | Delete | Special actions | Cross-page verified |
|---|---|---|---|---|---|---|
| Accounts | ✔ | ✔ | ✔ (form) | ✔ (guard: blocked when txns) | archive (flag) | ✔ |
| Transactions | ✔ | ✔ | ✔ | ✔ | — | ✔ |
| Transfers | ✔ | ✔ | – (delete+recreate) | ✔ | invariant | ✔ |
| Bills (credit) | ✔ (auto) | ✔ | – | – | pay in full | ✔ |
| Bills (paylater) | ✔ (auto) | ✔ | – | – (cancelled with plan) | partial / full / settle / refund | ✔ |
| BNPL plans | ✔ | ✔ | ✔ (via txn edit) | ✔ (via txn delete) | partial/full/settle/refund, status | ✔ |
| Budgets | ✔ | ✔ | ✔ (overwrite) | ✔ | over-budget state | ✔ |
| Categories | ✔ | ✔ | – (not supported) | ✔ | cascade effects | ✔ |
| Recurring | ✔ | ✔ | ✔ | ✔ | process/dedupe | ✔ |
| CSV | import | export | – | – | round-trip idempotency | ✔ |
| Backup/Restore | ✔ | ✔ | – | – | full-state revert | ✔ |

## 3. Chained workflows exercised (through the real UI)

1. Create account → switch → dashboard/net-worth/selector.
2. Create category → appears in all selectors.
3. Create budget → dashboard budget card.
4. **Account → Transaction → Budget → Dashboard**: create txn 40 (balance 960, budget 40/100, category chart) → edit 60 (940, 60/100, single row) → delete (1000, 0/100, empty list).
5. **Account A → Transfer → Account B → Dashboard**: transfer 200 → A 800 / B 200 / sum 1000 / net worth unchanged / ledger shows TRANSFER → delete → A 1000 / B 0.
6. **Credit → Purchase → Statement → Payment → Balance**: purchase 300 → outstanding 300, available 700, statement due 20 Oct = 300 → pay 300 → outstanding 0, available 1000, statement paid, funding ledger shows −300.
7. **BNPL → Plan → Instalments → Partial → Full → Refund → Settle**: 30 over 3 → bills 10/10/10 → partial 4 → full 6 → refund 5 → settle → all paid, plan **COMPLETED**, outstanding 0.
8. Recurring → generate `[Auto]` txn → edit amount propagates (single row) → delete cascades.
9. Category delete → transaction uncategorized (not deleted), budget cascade-deleted, removed from all selectors.
10. Backup → mutate → restore → exact state revert; pre-restore snapshot created.
11. Month scoping + browser back/forward + refresh → no stale data.

**Workflows tested:** 11 chained, ~45 individual CRUD operations.
**Cross-page consistency checks:** ~55 assertions across Dashboard,
Transactions, Budgets, Bills, Accounts, Categories, Recurring, and DB.
**UI controls exercised:** ~32 (add/save/edit/update/delete/confirm/transfer/
switcher/month-nav/pay/settle/refund/slider/selects/category-delete, etc.).

## 4. Bugs discovered and fixed

### 4.1 Add-form Type labels did not follow the selected account
- **Reproduce:** open Transactions with a savings account active; change the
  add-form *Account* selector to a credit/paylater account. The *Type* dropdown
  still shows "Expense / Income" instead of "Purchase / Refund / Credit".
- **Impact:** misleading labels when logging liability purchases (values were
  still correct, so no data corruption — UI inconsistency only).
- **Root cause:** Type labels were server-rendered from the page-load active
  account; the client-side account-change handler did not relabel them.
- **Fix:** `onAddAccountChange()` now relabels the Type options based on the
  selected account kind; called on `DOMContentLoaded`.
- **Regression test:** `tests/e2e/consistency.spec.js` (Type labels follow the
  selected account kind).

### 4.2 Previously-reported issues re-verified in the live UI (not regressions)
- BNPL plan badge correctly shows **COMPLETED** after full payment (was "active · RM0.00 left").
- Editing a plan-linked transaction regenerates the plan + bills (30 → 60), single row.
- Deleting a plan-linked transaction cancels the plan and clears bills (orphan reconcile).

## 5. Stale-state verification

| Action | Result |
|---|---|
| Refresh Transactions after delete | empty state shown, no stale row |
| Navigate Dashboard → Budgets → back | values consistent |
| Browser Back (Sep → Aug) | Aug shows "No activity"; Sep txn not visible |
| Browser Forward (Aug → Sep) | Sep txn visible; deleted category shows "Uncategorized" |
| Switch account | balances/list scope to the new account; no leakage |
| Month switch | monthly list/totals rescope; balance card unaffected (as designed) |
| Category delete | removed from every selector/chart/budget page |

## 6. Financial invariants re-confirmed

- Transfer: `source_effect + destination_effect = 0` (800 + 200 = 1000; net worth unchanged).
- Delete reverses effect exactly (960 → 940 → 1000).
- Liability payment: `original − payments + charges = remaining` (300 → 0).
- Refund never yields negative remaining instalments.
- Account isolation (A's transaction never changed B's balance).
- Backup restore reproduces the backed-up state (4 accounts / 4 txns / net −345).

## 7. Final test results

| Suite | Count | Result |
|---|---|---|
| PHPUnit (backend) | 83 | ✅ OK (183 assertions) |
| Playwright E2E (desktop + mobile) | 20 | ✅ 20 passed |
| PHP syntax (`lint:php`) | — | ✅ OK |

New regression tests: `tests/e2e/consistency.spec.js` (2 tests).

## 8. Remaining untested areas

- **Category edit** is not supported by the app (create/delete only) — no edit
  workflow exists to test.
- **Transfer edit** is not supported (delete + recreate only).
- **Bill/statement manual edit** is not supported.
- Browser **Firefox/WebKit** not exercised (Chromium only).
- **PDF/print** output not validated (browser print dialog).
- **Concurrency** (simultaneous writes) not tested.
- Very large datasets / pagination (the app has no pagination).
- Mobile coverage is a layout smoke test, not full CRUD on mobile viewport.

**This audit does not claim the application is bug-free.** It confirms that the
supported CRUD workflows listed above were exercised through the real UI and
that dependent pages/calculations updated consistently and persisted correctly.
