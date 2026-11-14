# Billing and End-of-Month Model

## Credit statement cycles

Credit accounts use their configured statement day as the cutoff and their configured due day for payment. A purchase on or before the cutoff belongs to the statement closing in that calendar month; a purchase after the cutoff belongs to the next month's statement. The due day is applied in the statement month and clamped to that month's last day when necessary.

For Atome (statement day 15, due day 26):

| Purchase date | Statement period | Due date |
|---|---|---|
| August 16–September 15 | August 16–September 15 | September 26 |
| September 16–October 15 | September 16–October 15 | October 26 |

Expenses and income/refunds are grouped by their own transaction dates. Credit accounts without a configured statement day retain the prior calendar-month grouping and following-month due date.

PayLater bills remain driven by their existing installment dates and payment state.

## EOM calculation

The dashboard projection is for the selected month and the primary savings account:

```text
cash ledger balance through selected month-end
+ recurring income expected in the selected month but not posted
- recurring expenses expected in the selected month but not posted
- unpaid liability bills due on or before selected month-end
```

The savings balance includes transactions and transfers dated on or before the selected month-end, including manually entered future-dated activity. Entries dated after that month-end are excluded. Credit purchases do not reduce savings cash on their transaction date; their outstanding bill reduces EOM when it is due. PayLater installments reduce EOM by their due dates.

Bill payment allocations are recorded with payment dates so payments after a viewed historical month do not make its bills appear paid prematurely. Existing bill-payment transfers with a due date in their standard description are backfilled on first database initialization. Older PayLater plan-settlement transfers did not retain an installment reference; their paid amount is best-effort distributed across unattributed paid installments in due-date order. Legacy paid amounts without a recoverable payment date remain treated as paid throughout historical views because their original date cannot be derived safely.

Spending reports continue to classify credit purchases by transaction date; only the cash EOM forecast follows bill due dates.

---

## Planned: salary-cycle forecast (TODO)

The calendar-month EOM above stops at the selected month-end, so early next-month bills (e.g. Y15 on the 2nd, PF on the 5th, TikTok on the 10th, Grab on the 11th) are not deducted. A fixed 30-day window is not cycle-safe: on the 27th it would reach the next 26th salary and cross into the following cycle.

Plan:
- Add an explicit **"This is my salary"** flag to one recurring income.
- Add a rolling card anchored to salary cycles:
  - current cycle = [latest salary date, next salary date)
  - next cycle = [next salary date, the following salary date)
  - the boundary salary is excluded; the starting salary is included when it is still in the future.
- Show current balance, expected in/out, **available at cycle end**, **lowest balance/date**, and a date-sorted timeline (bills + recurring + transfers).
- When the salary is posted early, use the actual posted transaction in the starting balance and skip the expected duplicate; the next boundary comes from the schedule (set the salary due day to the real pay day). Show the assumed salary date.
- Fallback when no salary is flagged: **End of month** (not a fixed 30 days).
- Open UX questions: default cycle (current vs next vs both), salary flag vs Salary category, schedule vs actual+1 month for the next boundary.
