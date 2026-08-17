# Shooting Statistics

A PHP based site for helping break down some basic school shooting statistics, especially in relation to transgender assailants. This was created as a means to help combat propaganda blaming transgender people for the majority of school shootings.

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) or `php -S localhost:8000` for local dev

## Setup

### 1. Database

```sql
-- Run schema.sql in MySQL:
mysql -u root -p < schema.sql
```

### 2. Configuration

Upon first install, please load ./admin/login.php and follow the instructions to set up the database connection info and to set the admin password.

This writes `includes/config.local.php`, which holds the database credentials and the admin password hash and is never committed. `includes/config.php` carries defaults only — anything defined in `config.local.php` wins, so credentials belong there rather than in the tracked file. The `includes/` directory ships with an `.htaccess` denying direct web access for that reason.

### 3. Web Server

**Apache** — ensure `mod_rewrite` is on (no `.htaccess` needed as-is).

**NGINX** — point root to the project directory.

**Local dev:**
```bash
cd school-shooting-stats
php -S localhost:8000
```

## Directory Structure

```
school-shooting-stats/
├── index.php             # Homepage / overview dashboard
├── incidents.php         # Filterable incident list
├── incident.php          # Single incident detail
├── analysis.php          # Statistical analysis + trans context
├── heatmap.php           # Geographic incident map
├── geocode.php           # JSON endpoint that geocodes uncached locations
├── includes/
│   ├── config.php        # Defaults only — do not put credentials here
│   ├── config.local.php  # ← Credentials live here (generated, never committed)
│   ├── db.php            # Database helpers
│   ├── layout.php        # Shared header/footer
│   └── .htaccess         # Denies web access to this directory
├── admin/
│   ├── index.php         # Admin dashboard
│   ├── login.php         # Password login + first-run setup
│   ├── logout.php        # Session teardown
│   ├── edit.php          # Add / edit incidents
│   ├── list.php          # Manage all incidents
│   ├── import.php        # CSV import tool
│   ├── export.php        # CSV export
│   ├── delete.php        # Delete handler
│   └── auth.php          # Session auth + CSRF
├── assets/
│   └── css/main.css    # Full stylesheet
└── schema.sql          # Run once to create the database
```

## Importing Your Spreadsheet

1. Open your spreadsheet in Excel or Google Sheets
2. **File → Save As / Download → CSV (comma-separated)**
3. Go to `yoursite.com/admin/import.php`
4. Upload the CSV

The importer auto-detects column names. Before matching, each header is lowercased, `#`, `?` and `!` are stripped, the filler word "of" is removed, and runs of spaces, hyphens and underscores collapse to a single underscore — so `Total # of assailants` arrives as `total_assailants` and matches. Accepted names per field:

| Field | Accepted names |
|-------|---------------|
| Date | `date`, `incident_date`, `event_date` |
| City | `city` |
| State | `state` |
| School | `school`, `school_name`, `name` |
| Deaths | `deaths`, `death`, `killed`, `fatalities`, `fatality` |
| Injuries | `injuries`, `injured`, `injury`, `wounded`, `wounds` |
| Description | `description`, `desc`, `notes`, `summary`, `details` |
| Trans assailant? | `trans`, `had_trans`, `trans_assailant`, `had_trans_assailant`, `transgender`, `transgender_involvement`, `trans_shooter` |
| # trans assailants | `trans_assailants`, `trans_count`, `num_trans`, `trans_assailant_count`, `transgender_count` |
| Genders | `gender`, `genders`, `assailant_gender`, `assailant_genders`, `shooter_gender` |
| Total assailants | `total_assailants`, `assailants`, `num_assailants`, `total_shooters`, `shooters` |
| Source | `source`, `source_url`, `url`, `urls`, `link`, `reference` |

An unrecognised column is ignored rather than treated as an error, so a spreadsheet carrying extra columns imports fine.

## Admin Panel

From the admin panel you can:
- Add individual incidents manually
- Edit or delete any record
- Import CSV files (bulk)
- Export all incidents back out as CSV
- View an import log
- Geocode incident locations for the map (results are cached, so each city/state pair is looked up once)

## Statistical Methodology

The **Analysis** page computes:

- **Trans assailants as % of all assailants** — direct calculation from data
- **Expected trans assailants** — `total_assailants × 0.008` (Williams Institute 2025 estimate: ~0.8% of U.S. adults identify as transgender)
- **Ratio to expected** — `actual ÷ expected` (1.0 = exactly proportional)

Population figure source: [Williams Institute, UCLA School of Law (2025)](https://williamsinstitute.law.ucla.edu/publications/trans-adults-united-states/)

Three constants in `includes/config.php` govern this, and they are meant to be changed together:

| Constant | Purpose |
|----------|---------|
| `TRANS_POPULATION_PCT` | The population share itself, as a decimal — the multiplier behind the expected-value calculation |
| `TRANS_POPULATION_SOURCE` | The citation text printed beside the figures on the Analysis page, the Overview page and in the footer |
| `TRANS_POPULATION_URL` | Where that citation links to |

Revising the estimate — or repointing the link if the Williams Institute reorganises their site — is three lines in one file. Any of the three can also be overridden in `includes/config.local.php` without touching the tracked defaults.
