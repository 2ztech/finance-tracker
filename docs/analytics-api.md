# Analytics API (backend contract)

Backend-only contract for the customizable analytics UI. The API returns raw,
JSON-safe data; the UI owns rendering, chart colors, layout editing and
navigation.

## Scope

- Session-authenticated, same-origin JSON API. No CORS headers are sent; the
  UI must be served from the same origin as the app.
- No per-user analytics state: the layout is stored once per database in
  `settings['analytics_layout']`.
- The backend does **not** register an `/analytics` page or navigation item;
  those are UI-owned. Only the API routes and the early API dispatch hook in
  `public/index.php` are backend.
- Uses purchase date (`transactions.date`). Transfers are separate and are not
  spending.
- No budget widget yet: `budgets` stores one amount per category, not a monthly
  history, so comparing a multi-month range to a budget would be misleading.
- No posted-date field; billing/posted-date work is deliberately deferred.

## Authentication and CSRF

All endpoints require an authenticated session. Unauthenticated requests get
HTTP `401` JSON, never an HTML redirect.

`GET /api/analytics` returns a `csrf_token`. Send it on every write:

```
X-CSRF-Token: <csrf_token>
```

A missing/invalid token returns HTTP `403` with
`error.code = "csrf_failed"`. The token can also be sent as `csrf_token` in the
JSON body or form body.

## Endpoints

### `GET /api/analytics`

Query parameters:

| Name | Values | Default |
|---|---|---|
| `range` | `this_month`, `last_3`, `last_6`, `last_12`, `custom` | `last_6` |
| `from` | `YYYY-MM-DD` (required for custom) | — |
| `to` | `YYYY-MM-DD` (required for custom) | — |
| `institution_id` | `0` for all, or an institution ID | `0` |

Custom ranges are limited to 120 months. `from` and `to` must use
`YYYY-MM-DD`; invalid/reversed ranges return `422`.

Returns the full page state in one response: filter options, catalog, current
layout, default layout, CSRF token and computed data for every widget in the
layout.

```json
{
  "ok": true,
  "generated_at": "2026-11-15T13:00:00+08:00",
  "currency": "MYR",
  "filters": {
    "ranges": [
      {"value": "this_month", "label": "This month"},
      {"value": "last_3", "label": "Last 3 months"},
      {"value": "last_6", "label": "Last 6 months"},
      {"value": "last_12", "label": "Last 12 months"},
      {"value": "custom", "label": "Custom range"}
    ],
    "institutions": [
      {"id": 1, "name": "Maybank", "color_hex": "#e8b600"}
    ],
    "range": {
      "preset": "last_6",
      "label": "Jun – Nov 2026",
      "from": "2026-06-01",
      "to": "2026-11-30",
      "months": [
        {"value": "2026-06", "label": "Jun 2026"}
      ]
    },
    "institution_id": 0
  },
  "catalog": [],
  "layout": {"version": 1, "widgets": []},
  "default_layout": {"version": 1, "widgets": []},
  "csrf_token": "…",
  "widgets": []
}
```

### `POST /api/analytics/layout`

Saves a complete layout. Body:

```json
{
  "layout": {
    "version": 1,
    "widgets": [
      {"id": "w1", "key": "income_expense", "size": "full", "config": {"chart": "bar"}}
    ]
  }
}
```

Response:

```json
{"ok": true, "layout": {"version": 1, "widgets": []}}
```

Layout rules:

- `version` must be `1`.
- Maximum 12 widgets.
- `id`: 1–40 characters, `A-Z a-z 0-9 _ -`, unique.
- `key`: one of the catalog keys.
- `size`: `half` (6/12), `wide` (8/12) or `full` (12/12). Missing size uses the
  widget default.
- `config`: only the fields declared in the catalog. Missing fields use
  defaults. Unknown fields are rejected.
- An empty `widgets` array is valid (the user removed everything).
- Invalid layouts return HTTP `422` with `error.details` as a list of strings.

### `POST /api/analytics/layout/reset`

Requires CSRF. Restores and returns `default_layout`.

```json
{"ok": true, "layout": {"version": 1, "widgets": []}}
```

## Widget catalog

The catalog is metadata for building the “Add widget” list and config forms.

Each catalog entry:

```json
{
  "key": "spending_category",
  "label": "Spending by Category",
  "group": "Spending",
  "description": "Expense totals grouped by category.",
  "type": "chart",
  "scope": "transactions",
  "range_semantics": "period",
  "default_size": "half",
  "sizes": ["half", "wide", "full"],
  "default_config": {"chart": "doughnut", "top": 8},
  "config_fields": [
    {
      "name": "chart",
      "label": "Chart type",
      "type": "select",
      "options": [{"value": "doughnut", "label": "Doughnut"}, {"value": "bar", "label": "Bar"}],
      "default": "doughnut"
    },
    {
      "name": "top",
      "label": "Number of items",
      "type": "select",
      "options": [{"value": 5, "label": "Top 5"}, {"value": 8, "label": "Top 8"}, {"value": 10, "label": "Top 10"}],
      "default": 8
    }
  ]
}
```

Supported keys:

| Key | Group | Default size | Config | Range semantics |
|---|---|---|---|---|
| `income_expense` | Income | full | `chart`: bar/line | period |
| `spending_category` | Spending | half | `chart`: doughnut/bar, `top`: 5/8/10 | period |
| `spending_institution` | Institutions | half | — | period |
| `top_merchants` | Spending | half | `top`: 5/8/10 | period |
| `credit_utilization` | Liabilities | half | — | snapshot |
| `net_worth` | Trends | full | — | timeline |
| `recurring_oneoff` | Spending | half | `chart`: doughnut/bar | period |
| `savings_rate` | Trends | full | — | timeline |

`scope`:

- `transactions`: based on expense/income transactions.
- `accounts`: based on account balances (credit utilization, net worth).

`range_semantics`:

- `period`: aggregate over the entire selected range.
- `snapshot`: a point-in-time value as of the range end.
- `timeline`: one value per month in the range.

### Default layout

1. `income_expense` — full
2. `spending_category` — half
3. `spending_institution` — half
4. `top_merchants` — half
5. `credit_utilization` — half

Addable but not default: `net_worth`, `recurring_oneoff`, `savings_rate`.

## Widget envelope

Every item in `widgets` has the same shell:

```json
{
  "id": "w1",
  "key": "income_expense",
  "title": "Income vs Expenses",
  "group": "Income",
  "type": "chart",
  "description": "Monthly income and expenses across the selected range.",
  "size": "full",
  "config": {"chart": "bar"},
  "meta": {
    "scope": "transactions",
    "range_semantics": "period",
    "range_applied": true,
    "institution_applied": true,
    "currency": "MYR"
  },
  "empty": false,
  "data": {}
}
```

Money is a number rounded to 2 decimals. Percentages are numbers rounded to 2
decimals. Counts are integers. Empty results are empty arrays/zeros, never null
(except `savings_rate.rate_pct` where income is zero).

## Data shapes

### `income_expense`

```json
{
  "labels": ["2026-06"],
  "display_labels": ["Jun 2026"],
  "income": [0.0],
  "expenses": [0.0],
  "totals": {"income": 0.0, "expenses": 0.0, "net": 0.0}
}
```

### `spending_category`

```json
{
  "items": [
    {
      "category_id": 1,
      "name": "Food & Dining",
      "color_hex": "#ef4444",
      "total": 123.45,
      "share_pct": 45.0
    }
  ],
  "total": 274.33,
  "other_total": 150.88
}
```

`category_id` is `null` for uncategorized transactions. `items` is limited by
`config.top`; `other_total` is the remainder. The UI should append an “Other”
slice/bar if `other_total > 0`.

### `spending_institution`

```json
{
  "mode": "institution",
  "items": [
    {
      "institution_id": 1,
      "account_id": null,
      "name": "Maybank",
      "institution_name": "Maybank",
      "color_hex": "#e8b600",
      "total": 123.45,
      "share_pct": 45.0
    }
  ],
  "total": 274.33
}
```

- With `institution_id = 0`, `mode` is `institution` and items are institutions.
- With one institution selected, `mode` is `section` and items are that
  institution’s sections (`account_id` set).

### `top_merchants`

```json
{
  "items": [
    {"name": "Grab", "total": 123.45, "count": 4, "share_pct": 30.0}
  ],
  "total": 412.0,
  "other_total": 288.55
}
```

Blank descriptions are excluded. Grouping is case-insensitive after trimming.
`items` is limited by `config.top`.

### `credit_utilization`

```json
{
  "as_of": "2026-11-30",
  "items": [
    {
      "account_id": 4,
      "institution_id": 1,
      "account_name": "Maybank Visa",
      "institution_name": "Maybank",
      "color_hex": "#3155c8",
      "limit": 5000.0,
      "outstanding": 1200.0,
      "available": 3800.0,
      "used_pct": 24.0,
      "over_limit": false
    }
  ],
  "totals": {
    "limit": 5000.0,
    "outstanding": 1200.0,
    "available": 3800.0,
    "used_pct": 24.0
  }
}
```

Only non-archived credit/paylater accounts with `credit_limit > 0` are included.
`as_of` is the range end. Outstanding is clamped to zero (overpaid cards are not
negative).

### `net_worth`

```json
{
  "labels": ["2026-06"],
  "display_labels": ["Jun 2026"],
  "values": [1200.0],
  "change": 200.0,
  "change_pct": 20.0,
  "account_count": 3
}
```

Month-end natural net worth: savings balances minus liability outstanding.
For a custom range that ends mid-month, the final point is as of the range end.
`account_count = 0` means no accounts matched the filter. `change_pct` is `null`
when the first value is zero.

### `recurring_oneoff`

```json
{
  "recurring": 300.0,
  "one_off": 100.0,
  "recurring_count": 1,
  "one_off_count": 1,
  "total": 400.0
}
```

Expense transactions with a non-null `commitment_id` count as recurring.

### `savings_rate`

```json
{
  "labels": ["2026-06"],
  "display_labels": ["Jun 2026"],
  "income": [0.0],
  "expenses": [0.0],
  "rate_pct": [null],
  "totals": {"income": 0.0, "expenses": 0.0, "rate_pct": null}
}
```

`rate_pct` is `(income - expenses) / income * 100`, or `null` when income is
zero for that month.

## Error format

```json
{
  "ok": false,
  "error": {
    "code": "invalid_layout",
    "message": "The layout is invalid.",
    "details": ["widgets[0].key is not a supported widget."]
  }
}
```

| Status | Code | Meaning |
|---|---|---|
| 400 | `invalid_json` | POST body is not valid JSON |
| 401 | `unauthorized` | No authenticated session |
| 403 | `csrf_failed` | Missing/invalid CSRF token |
| 404 | `not_found` | Unknown `/api/analytics/*` path |
| 405 | `method_not_allowed` | Wrong HTTP method (see `Allow`) |
| 422 | `invalid_request` | Invalid range or institution |
| 422 | `invalid_layout` | Layout failed validation |
| 500 | `server_error` | Query/processing failure |

Responses use `Content-Type: application/json; charset=utf-8` and
`Cache-Control: no-store`.

## Example flow

```js
const state = await fetch('/api/analytics?range=last_6').then((r) => r.json());

await fetch('/api/analytics/layout', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': state.csrf_token,
  },
  body: JSON.stringify({ layout: state.layout }),
});

const refreshed = await fetch('/api/analytics?range=last_6').then((r) => r.json());
```

## UI responsibilities

Kimi/K3 owns:

- The `/analytics` page, template, navigation and styling.
- Chart rendering (Chart.js already exists in the app) from the raw series.
- Add/edit/remove/reorder/resize interactions. The backend stores the layout; the
  UI decides the controls.
- Color mapping for widgets without explicit colors (income/expense/savings
  rate) and “Other” slices.
- Empty states using each widget’s `empty` flag.
- Login redirect when the API returns `401`.

The backend does **not** return Chart.js configuration; it returns labels,
series, colors and totals so the UI can choose chart types/options.

## Server-rendered option

The PHP classes are usable directly if the UI is server-rendered:

- `Analytics::range()`, `Analytics::filter()`, `Analytics::build()`
- `Widgets::catalog()`, `Widgets::load()`, `Widgets::save()`

The JSON API is a thin adapter around these classes.
