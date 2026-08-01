# Expenzz - Personal Finance Tracker

A lightweight, mathematically rigorous personal finance tracker built with vanilla PHP 8.3 and SQLite.

Unlike standard budget apps that just sum up monthly totals, Expenzz is built on **true ledger logic**. It tracks your actual physical cash (On-Hand Balance) and carries it over month-to-month, while separately calculating End-of-Month (EOM) projections based on your unpaid future commitments.

## Features

- **Ledger Logic:** Real cash-on-hand tracking with month-over-month balance carry-over. Future unpaid bills don't affect your current liquidity.
- **EOM Projections:** Smart end-of-month balance estimates accounting for unpaid commitments and scheduled income.
- **Recurring Commitments:** Auto-process bills and income with due dates, start/end date ranges, and category linking.
- **Budget Tracking:** Set monthly spending caps per category with progress bars on the dashboard.
- **Quick-Add Templates:** Save frequently used transactions (e.g. "Lunch at office") as one-click templates.
- **CSV Import/Export:** Smart deduplication engine prevents double-counting on bank statement imports.
- **Dark/Light Mode:** Toggleable theme with localStorage persistence. Easy on the eyes day or night.
- **PDF Export:** Generate bank-statement style monthly reports with beginning/closing balance and running totals.
- **Undo Delete:** 8-second undo toast for accidental transaction deletions.
- **Data Portability:** 100% self-hosted SQLite. Export CSV, download/restore full database backups from the settings UI.
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

## Default Credentials

- **Username:** admin
- **Password:** admin

Change immediately via Settings after first login.

## Data Persistence

Your entire database lives in `data/finance.db`. When using Docker, mount the data directory as a volume to survive container rebuilds. Backup via the Settings page or just copy the file.

## Contributing

Issues and pull requests welcome.
