# NXLOG Analytics

NXLOG Analytics is a PHP and MySQL trading journal built to help traders record trades, review execution, and study performance over time.

The project is designed around more than trade storage: it includes trade history, analytics, review workflows, trading rules, reports, and MetaTrader 5 (MT5) synchronization components.

> **Project status:** Active development. This repository reflects the current codebase, but it has not been independently audited or verified as production-ready.

## Features

- User authentication and session-based access
- Trade logging, editing, closing, and history
- Performance analytics, insights, and reports
- Trade review workflow
- Trading rules and account settings
- MT5 synchronization components, including an Expert Advisor and webhook endpoint
- PHP and MySQL backend

Feature availability and behavior may change as development continues.

## Tech stack

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Local development:** XAMPP
- **Trading platform integration:** MetaTrader 5 (MQL5 Expert Advisor and PHP webhook)

## Repository structure

```text
.
├── api/                 # API endpoints, including MT5 integration
├── assets/              # Frontend assets
├── auth/                # Authentication routes
├── config/              # Application configuration
├── downloads/           # MT5 synchronization files
├── pages/               # Page modules
├── partials/            # Shared UI components
├── analytics.php        # Performance analytics
├── dashboard.php        # Main dashboard
├── insights.php         # Performance insights
├── log.php              # Trade log
├── log_new.php          # Create a trade
├── trade-history.php    # Trade history
└── reports.php          # Reports
```

## Local setup

### Requirements

- PHP supported by your installed XAMPP version
- MySQL or MariaDB
- Apache and MySQL enabled in XAMPP
- Git (optional, for cloning the repository)

### 1. Get the code

Clone the repository into your XAMPP web root:

```bash
git clone https://github.com/Ejiro404/trading-journal.git
```

For a standard XAMPP installation on Windows, place the project folder under `C:\\xampp\\htdocs\\`.

### 2. Create the database

Open phpMyAdmin and create a database named:

```text
trading_journal
```

**Database schema note:** This repository currently does not include a verified, complete SQL schema export. Import the schema from your own project backup before running the application. Do not import real user or trading data into a public repository.

### 3. Configure the database

The local development defaults in `config/db.php` target XAMPP's typical local MySQL setup:

- Host: `127.0.0.1`
- Database: `trading_journal`
- User: `root`
- Password: empty by default in many local XAMPP installations

If your local MySQL credentials differ, update the configuration accordingly.

For deployment, set these environment variables on the server:

| Variable | Purpose |
| --- | --- |
| `APP_ENV` | Set to `production` for production deployment |
| `DB_HOST` | Database server hostname |
| `DB_NAME` | Database name |
| `DB_USER` | Dedicated database user |
| `DB_PASS` | Database password |

Production configuration rejects an empty database password and the MySQL `root` user. Use a dedicated database account with only the privileges the application needs. Do not commit credentials or secrets to Git.

### 4. Run the application

1. Start Apache and MySQL in XAMPP.
2. Ensure the database schema has been imported.
3. Open the local project URL in your browser, for example `http://localhost/trading-journal/`.
4. Test authentication and the main trade workflows with non-sensitive test data.

The exact setup may require additional configuration depending on the schema and local environment.

## MetaTrader 5 integration

The repository includes MT5 synchronization components, including an Expert Advisor under `downloads/` and a webhook under `api/`.

Before enabling synchronization:

1. Review the Expert Advisor and webhook code.
2. Configure the endpoint for your own environment.
3. Use HTTPS and a strong, unique webhook secret or equivalent request authentication.
4. Never expose database credentials or private tokens in the Expert Advisor, repository, screenshots, or logs.
5. Test with a non-production account before relying on synchronization.

Do not expose a local XAMPP server directly to the public internet as a production deployment.

## Security and deployment notes

- Use HTTPS in any hosted environment.
- Keep production secrets in server environment configuration, not source control.
- Use a dedicated database user rather than MySQL `root`.
- Review session cookie settings, CSRF protections, authorization checks, and webhook authentication before public deployment.
- Back up the database and test restore procedures.
- Run functional and security tests before handling real user data.

These are deployment recommendations, not a claim that every item has already been implemented or audited.

## Development status

NXLOG Analytics is an ongoing project. The repository is intended to document the implementation and support further development. Setup is not yet fully reproducible from a clean clone until a complete, verified database schema export and any required seed/setup instructions are provided.

## Repository

[GitHub: Ejiro404/trading-journal](https://github.com/Ejiro404/trading-journal)
