# Expenzz - Personal Finance Tracker

A lightweight, mathematically rigorous personal finance tracker built with vanilla PHP 8.3 and SQLite. 

Unlike standard budget apps that just sum up monthly totals, Expenzz is built on **true ledger logic**. It tracks your actual physical cash (On-Hand Balance) and carries it over month-to-month, while separately calculating End-of-Month (EOM) projections based on your unpaid future commitments. 

## ✨ Features

* **True Ledger Carry-Over:** Month-to-month starting balances reflect actual physical cash remaining, ignoring unpaid future bills until they are actually processed.
* **Smart EOM Projections:** Calculates your expected End-of-Month balance by taking your current cash and subtracting scheduled, unpaid monthly commitments.
* **Commitment Tracking:** Easily manage recurring bills (e.g., loans, subscriptions). The system automatically tracks what has been paid and what is still owed for the current month.
* **Intelligent CSV Imports:** Import bank or credit card statements with a smart deduplication engine that checks dates, amounts, types, and categories to prevent double-counting.
* **Data Portability & Backups:** 100% self-hosted with SQLite. Export data to CSV, download full database backups, and restore the database directly from the settings UI.
* **Dynamic Timezone Support:** Automatically syncs the app's internal PHP clock to your server's timezone via Docker environment variables.

## 🛠 Tech Stack

* **Backend:** Vanilla PHP 8.3
* **Database:** SQLite3
* **Frontend:** HTML5, CSS (Tailwind)
* **Deployment:** Docker & Docker Compose

---

## 🚀 Installation Guide

You can run Expenzz either locally using PHP's built-in web server (great for quick testing and development) or via Docker (recommended for 24/7 homelab or production environments).

### Method 1: Quick Start (PHP Built-in Server)
This is the fastest way to get the app running on your local machine without setting up containers.

**Prerequisites:**
* [Git](https://git-scm.com/downloads)
* PHP 8.2 or higher (with the `sqlite3` extension enabled)

**Steps:**

1. **Clone the repository:**
```bash
git clone https://github.com/2ztech/finance-tracker.git
cd finance-tracker
```

2. **Start the PHP development server:**
Point the server to the public directory to ensure routing works correctly:
```bash
php -S localhost:8000 -t public
```

3. **Access the app:**
Open your browser and navigate to `http://localhost:8000`.

---

### Method 2: Docker (Recommended for Production/Homelabs)
This method ensures the app runs in an isolated environment with all dependencies automatically handled.

**Prerequisites:**
* Git
* Docker
* Docker Compose

**Steps:**

1. **Clone the repository:**
```bash
git clone https://github.com/2ztech/finance-tracker.git
cd finance-tracker
```

2. **Configure the Timezone (Optional):**
If you need to set a specific timezone, edit the `docker-compose.yml` file and update the `TZ` environment variable.
```yaml
environment:
  - TZ=Asia/Kuala_Lumpur
```

3. **Build and start the container:**
Run the following command to build the image and start the container in the background:
```bash
docker-compose up -d --build
```

4. **Access the app:**
Open your browser and navigate to `http://localhost:8080` (or replace `localhost` with your server's local IP address if hosting on a separate machine).

---

## 🔐 Default Credentials

Upon first installation, use the following default credentials to log in:
* **Username:** admin
* **Password:** admin

*(Note: Please change these credentials immediately via the Settings page after logging in).*

---

## 📂 Data Persistence

Because Expenzz uses SQLite, your entire database is stored in a single file (`finance.db` located in the root directory).

If you are using Docker, the `docker-compose.yml` is configured to mount a volume to your host machine. This ensures your database is safe and persists even if the container is restarted, updated, or destroyed.

To create a manual backup at any time, simply navigate to the Settings page in the app and click "Download Database Backup".

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the issues page.
