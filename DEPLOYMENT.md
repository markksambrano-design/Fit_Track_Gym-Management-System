# FitTrack deployment guide

FitTrack requires PHP 7.4+ with the PDO MySQL and MySQLi extensions, plus a MySQL 8.0+ database. GitHub stores the source code; it does not run this PHP application or host its MySQL database.

## 1. Prepare GitHub

Create a GitHub repository and push this project. Keep the repository private if it contains school or client information. The `.gitignore` excludes runtime uploads, logs, local environment files, and diagnostic files.

Before every push, check that no real credentials or personal records are staged:

```bash
git status
git diff --cached
```

## 2. Create the hosted database

In cPanel or your hosting dashboard:

1. Create a MySQL database.
2. Create a dedicated MySQL user with a strong password.
3. Grant that user all privileges on the new database.
4. Open phpMyAdmin, select the database, and import `database_schema.sql`.

The committed schema intentionally contains no default admin account or production records.

## 3. Configure database credentials

Set these environment variables in the hosting dashboard. Use the exact values supplied by the host:

```text
DB_HOST=your-database-host
DB_PORT=3306
DB_NAME=your-database-name
DB_USER=your-database-user
DB_PASSWORD=your-strong-password
```

Do not commit a `.env` file. `.env.example` documents the required variable names only. On ordinary cPanel hosting, ask the provider how PHP environment variables are configured; do not put secrets in a public repository.

For InfinityFree, copy `includes/db.local.example.php` to
`includes/db.local.php` in the online File Manager, then replace only the
password placeholder with the current MySQL password. `db.local.php` is
ignored by Git and must never be uploaded to GitHub.

For the existing XAMPP installation, the application still defaults to `localhost`, port `3306`, user `root`, an empty password, and database `Fit_Track` when variables are absent.

## 4. Deploy the PHP application

Upload the project to the hosting account's web root (commonly `public_html`) or connect the hosting service to the GitHub repository. Confirm that these PHP extensions are enabled:

- `pdo_mysql`
- `mysqli`
- `mbstring`

The web server must have write permission only where runtime uploads are required. Do not make the whole application directory world-writable.

## 5. Create the first administrator

Do not insert a plain-text password in SQL. Use the application's administrator-creation flow if available. If an initial hash must be generated manually, run this locally and paste only the generated hash into a one-time SQL insert:

```bash
php -r "echo password_hash('replace-with-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Delete the one-time SQL after use and verify that the login code uses `password_verify()`.

## 6. Production checks

- Enable HTTPS.
- Confirm login, registration, QR scanning, uploads, and session behavior.
- Ensure PHP errors are logged but not displayed to visitors.
- Back up both the database and the `uploads` directory regularly.
- Do not use Git as a backup for uploaded member/staff photos or database contents.
