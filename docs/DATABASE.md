# Database

BodaERP uses one MySQL/MariaDB database. **Nobody imports SQL by hand**: the app creates the
database (locally), builds the tables, and keeps them up to date on every machine, including the live server.

## 1. Connection

| Where | What you do |
|---|---|
| **Your computer (XAMPP etc.)** | Nothing. The app connects to `127.0.0.1` as `root` (no password) and creates the database `bodaerp` if it is missing. |
| **Different local settings** | Put only the lines you need in `.env`: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`. |
| **Live server** | Create an empty database and user in the hosting panel, then put `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (and `APP_ENV=live`) in `.env`. Tables appear on the first visit. |

Order of precedence: `.env` / environment variables → the old `config/database.php` (still read on
servers that have it) → built-in local defaults. Everything is in `packages/backend/database/database.php`.
If the connection fails, visitors get a plain "can't reach its database" page — never credentials.

The connection details and live status are shown at **Super Admin → Database**.

## 2. How changes reach every machine

Two automatic layers run on the first request after new code arrives (about 1 ms when there is nothing to do):

1. **Migrations** — `database/migrations/*.php`, applied in filename order, each once (recorded in the
   `schema_migrations` table). For data fixes, renames, drops, views and anything risky.
2. **Schema file** — `database/schema.snapshot.json`, the shape (tables, columns, indexes, foreign keys)
   of the developer's database. Any machine compares it with its own database and **adds** what is missing.

```
 you change the local database ─► app saves database/schema.snapshot.json ─► you commit + deploy
                                                                                   │
              live server: first request after the deploy ◄────────────────────────┘
              adds the missing tables / columns / indexes by itself
```

### What the schema file does and does not do

| Does (automatically) | Never does |
|---|---|
| Creates missing tables, columns, indexes and foreign keys | Drops or renames anything |
| Widens safely: longer `varchar`, bigger integers, `text`→`longtext`, new `enum` values **at the end**, "allow NULL" | Shrinks or changes a column's type |
| Skips tables named `tmp_*`, `temp_*`, `bak_*`, `backup_*`, `old_*`, `zz_*` | Touches your rows (data is never copied) |
| Reports risky differences under **Needs review** | Handles views, triggers or stored routines (use a migration) |

A failure never takes the site down: it is logged, shown on the Database page with the exact error
(often a new `UNIQUE` index on data that has duplicates) and retried — immediately when the snapshot
changes, otherwise about every hour, or when you press *Update database now*.

### The snapshot file is written on developer machines only

While you browse the app on `localhost` the file is kept in step with your local database (checked at most
every 10 seconds). It is **never** written on a live server, even if someone forges a request. Switch it off
with `SCHEMA_AUTO_CAPTURE=0` in `.env`. Save it on demand with the button on the Database page or
`php database/migrate.php --capture`. Ignore extra tables with `SCHEMA_IGNORE_TABLES=a,b` in `.env`.

## 3. "I want to…"

| I want to… | Do this |
|---|---|
| Add a column / table / index | Change your local database (phpMyAdmin or SQL). Reload any page. **Commit `database/schema.snapshot.json`** with your code. |
| Make a column longer / add an `enum` option | Same — it is a safe widening, applied automatically. |
| Rename or remove a column, shrink a type, change a type | Write a **migration** (below). The schema file will list it under *Needs review* until you do. |
| Fix or backfill data | A **migration**. |
| Create or change a view | A **migration** (`CREATE OR REPLACE VIEW`). The two statistics views (`v_stage_stats`, `v_city_stats`) must stay views. |
| Add starter data for a new city | A **data pack**: `database/datapacks/<name>.php`, applied from Super Admin → System & Updates. |
| Apply updates right now | Super Admin → Database → *Update database now*, or `php database/migrate.php`. |
| Preview what would change | `php database/migrate.php --status` (lists everything; changes nothing). |
| Start from scratch | Drop the database in phpMyAdmin and open the site. |

### Writing a migration

Create `database/migrations/YYYY_MM_DD_HHMMSS_short_name.php` (the timestamp sets the order). Never edit
a migration that has already been released — add a new one. Make it **idempotent** (safe to run twice) by
checking the current state first; the helpers in `packages/backend/migrator.php` do that for you.

```php
<?php
return [
    'description' => 'Rename riders.route to riders.main_route',
    'up' => function (mysqli $db) {
        if (mig_column_exists($db, 'riders', 'route') && !mig_column_exists($db, 'riders', 'main_route')) {
            mig_exec($db, "ALTER TABLE `riders` CHANGE `route` `main_route` VARCHAR(150) NULL");
        }
    },
];
```

Helpers: `mig_table_exists`, `mig_column_exists`, `mig_column_type`, `mig_index_exists`, `mig_add_column`,
`mig_add_index`, `mig_value`, `mig_exec`. A migration that throws stops the later ones, is retried every
5 minutes, and shows up in red on the Database page. Test it on a scratch database first
(`DB_NAME=bodaerp_test php database/migrate.php`).

## 4. Demo and reference data

| What | How | Result |
|---|---|---|
| Large demo set | `php database/seed_run.php` (`--fresh` empties the data tables first) | 4 cities, 10 stages, ~1,200 riders, payments, 14 users incl. 4 government accounts |
| Small demo set | After the site has run once, import `database/sample_data.sql` in phpMyAdmin | 3 cities, 13 users, 8 riders (logins are listed at the top of the file) |
| A new city | Data pack (Super Admin → System & Updates → Data packs). Currently: Soroti City | Applied once, never overwrites existing records |

Do not run either demo set on a database that holds real data.

## 5. Backups

Super Admin → Database → **Backup (.sql)** downloads the whole database (including password hashes — keep the
file private). Restore it in phpMyAdmin by importing it into an empty database. Take one before any big change.

## 6. Files in `database/`

| File | Purpose |
|---|---|
| `migrations/` | One-off changes, run automatically (see §3) |
| `schema.snapshot.json` | Database shape, written by the app, committed |
| `datapacks/` | Reference data bundles |
| `bodaerp_full.sql` | The original base schema. The first migration runs it **once** on an empty database; do not import it yourself |
| `seed_run.php`, `seed_data/` | Large demo data generator |
| `sample_data.sql` | Small demo data set |
| `migrate.php` | Command line: update / `--status` / `--capture` |

The folder is blocked from the web by `.htaccess`.

## 7. Problems

| Symptom | Fix |
|---|---|
| Database page shows red / "Could not be applied" | Read the error under it. Fix the data or write a migration, then *Update database now*. |
| "Needs review" lists a difference | The snapshot and this database disagree in a way that could lose data. Write a migration that changes it safely. |
| A table exists locally but not on the server | Was the snapshot committed and deployed? Check that `database/schema.snapshot.json` changed in git. |
| Migration failed half-way | Fix the migration (it is idempotent, so it just continues) and press *Update database now*. |
| Local database seems out of step after switching branches | Open any page; the app applies the branch's snapshot and migrations to your local database. |
