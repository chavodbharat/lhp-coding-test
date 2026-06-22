# Setup Guide

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.3 or higher |
| Composer | 2.x |
| Node.js | 20 or higher |
| MySQL | 8.0 or higher |

> **MySQL is required.** The project uses FULLTEXT indexes and stored generated columns for search — these are MySQL-specific and won't work with SQLite.

---

## Step 1 — Install dependencies

```bash
composer install
npm install
```

---

## Step 2 — Create your environment file

```bash
cp .env.example .env
```

Open `.env` and update the database section:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lhp_events
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
```

Create the database in MySQL first:

```sql
CREATE DATABASE lhp_events CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Step 3 — Generate the application key

```bash
php artisan key:generate
```

---

## Step 4 — Run migrations

```bash
php artisan migrate
```

---

## Step 5 — Link storage for images

```bash
php artisan storage:link
```

---

## Step 6 — Seed the database

```bash
php artisan db:seed
```

> This seeds **1.25 million events** with images and all related data. It will take a few minutes — that's expected. Progress is printed in the terminal.

---

## Step 7 — Start the development server

```bash
composer dev
```

This starts everything in one command:

- PHP server at `http://localhost:8000`
- Vite (hot-reload)
- Queue worker (for emails)
- Log watcher

Open `http://localhost:8000` in your browser.

---

## Emails

Emails are written to the log file by default — no SMTP setup needed. To see them:

```
storage/logs/laravel.log
```

---

## Reminder Emails

```bash
# Preview without sending
php artisan reminders:send --window=3day --dry-run
php artisan reminders:send --window=24h --dry-run

# Actually send
php artisan reminders:send --window=3day
php artisan reminders:send --window=24h
```

---

## Quick Reference

| Command | What it does |
|---|---|
| `composer dev` | Start everything (server + queue + vite + logs) |
| `php artisan migrate:fresh --seed` | Wipe and re-seed from scratch |
| `php artisan queue:work` | Run the email queue manually |
| `npm run build` | Build frontend for production |
