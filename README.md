# Shooting Statistics

A PHP based site for helping break down some basic school shooting statistics, especially in relation to transgender assailants. This was created as a means to help combat propaganda blaming transgender people for the majority of school shootings.

---

## Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) or `php -S localhost:8000` for local dev

---

## Setup

### 1. Database

```sql
-- Run schema.sql in MySQL:
mysql -u root -p < schema.sql
```

### 2. Configuration

Upon first install, please load ./admin/login.php and follow the instructions to set up the database connection info and to set the admin password.

### 3. Web Server

**Apache** — ensure `mod_rewrite` is on (no `.htaccess` needed as-is).

**NGINX** — point root to the project directory.

**Local dev:**
```bash
cd school-shooting-stats
php -S localhost:8000
```

---

## Directory Structure

```
school-shooting-stats/
├── index.php           # Homepage / overview dashboard
├── incidents.php       # Filterable incident list
├── incident.php        # Single incident detail
├── analysis.php        # Statistical analysis + trans context
├── includes/
│   ├── config.php      # ← Edit credentials here
│   ├── db.php          # Database helpers
│   └── layout.php      # Shared header/footer
├── admin/
│   ├── index.php       # Admin dashboard
│   ├── login.php       # Password login
│   ├── edit.php        # Add / edit incidents
│   ├── list.php        # Manage all incidents
│   ├── import.php      # CSV import tool
│   ├── delete.php      # Delete handler
│   └── auth.php        # Session auth
├── assets/
│   └── css/main.css    # Full stylesheet
└── schema.sql          # Run once to create the database
```

---

## Importing Your Spreadsheet

1. Open your spreadsheet in Excel or Google Sheets
2. **File → Save As / Download → CSV (comma-separated)**
3. Go to `yoursite.com/admin/import.php`
4. Upload the CSV

The importer auto-detects column names (case-insensitive). Accepted aliases per field:

| Field | Accepted names |
|-------|---------------|
| Date | `date`, `incident_date`, `event_date` |
| Location | `location`, `school`, `school_name` |
| Deaths | `deaths`, `killed`, `fatalities` |
| Injuries | `injuries`, `injured`, `wounded` |
| Trans assailant? | `trans`, `had_trans`, `transgender` |
| # trans assailants | `trans_count`, `num_trans`, `trans_assailant_count` |
| Genders | `assailant_genders`, `genders`, `gender` |
| Total assailants | `total_assailants`, `assailants` |
| Source | `source`, `source_url`, `url` |

See `import/sample_template.csv` for a working example.

---

## Admin Panel

From the admin panel you can:
- Add individual incidents manually
- Edit or delete any record
- Import CSV files (bulk)
- View an import log

---

## Statistical Methodology

The **Analysis** page computes:

- **Trans assailants as % of all assailants** — direct calculation from data
- **Expected trans assailants** — `total_assailants × 0.006` (Williams Institute 2022 estimate: ~0.6% of U.S. adults identify as transgender)
- **Ratio to expected** — `actual ÷ expected` (1.0 = exactly proportional)

Population figure source: [Williams Institute, UCLA School of Law (2022)](https://williamsinstitute.law.ucla.edu/publications/trans-adults-united-states/)

To update the population percentage, edit `TRANS_POPULATION_PCT` in `includes/config.php`.
