# Expenzz - Personal Finance Tracker

A lightweight, mathematically rigorous personal finance tracker built with vanilla PHP 8.3 and SQLite.

Unlike standard budget apps that just sum up monthly totals, Expenzz is built on **true ledger logic**. It tracks your actual physical cash (On-Hand Balance) and carries it over month-to-month, while separately calculating End-of-Month (EOM) projections based on your unpaid future commitments.

## Features

- **Multi-Account:** Manage savings, credit card, and paylater (BNPL) accounts. Switch the active account from the sidebar; balances and net worth are tracked per account.
- **Internal Transfers:** Move money between accounts without distorting income/expense totals.
- **Credit Cards & Paylater:** Cycle-based statements (Atome Card, Shopee/TikTok PayLater) and per-purchase instalment plans (Atome BNPL, Grab PayLater), with bills, partial payments, early settlement, and refunds.
- **Ledger Logic:** Real cash-on-hand tracking with opening balances and month-to-month carry-over.
- **EOM Projections:** Smart end-of-month balance estimates accounting for unpaid commitments and scheduled income.
- **Recurring Commitments:** Auto-process bills and income with due dates, start/end date ranges, and category linking.
- **Budget Tracking:** Set monthly spending caps per category with progress bars on the dashboard (aggregated across accounts).
- **Quick-Add Templates:** Save frequently used transactions as one-click templates.
- **CSV Import/Export:** Smart deduplication engine; optional Account column.
- **Dark/Light Mode:** Toggleable theme with localStorage persistence.
- **PDF Export:** Bank-statement style monthly reports for savings accounts.
- **Undo Delete:** 8-second undo toast for accidental transaction deletions.
- **Data Portability:** 100% self-hosted SQLite with backup/restore from the settings UI.
- **Dynamic Timezone:** Syncs to your server's timezone via Docker environment variables.

## Tech Stack

- **Backend:** Vanilla PHP 8.3
- **Database:** SQLite3
- **Frontend:** HTML5, Tailwind CSS, Chart.js
- **Deployment:** Docker & Docker Compose

---

## Docker (Recommended)

```bash
docker run -d \
  -p 8000:80 \
  -v ./data:/var/www/html/data \
  -e TZ=Asia/Kuala_Lumpur \
  2ztech/expenzz:latest
```

Or with Docker Compose:

```yaml
services:
  expenzz:
    image: 2ztech/expenzz:latest
    ports:
      - "8000:80"
    volumes:
      - ./data:/var/www/html/data
    environment:
      - TZ=Asia/Kuala_Lumpur
    restart: unless-stopped
```

[Docker Hub](https://hub.docker.com/r/2ztech/expenzz)

## Quick Start (PHP Built-in Server)

```bash
git clone https://github.com/2ztech/finance-tracker.git
cd finance-tracker
php -S localhost:8000 -t public
```

## First Login

There are **no default credentials**. On first launch the app shows a setup
screen where you choose your own username and password. You can change them
later from Settings.

## Data Persistence

Your entire database lives in `data/finance.db`. When using Docker, mount the data directory as a volume to survive container rebuilds. Backup via the Settings page or just copy the file.

## Contributing

Issues and pull requests welcome.
