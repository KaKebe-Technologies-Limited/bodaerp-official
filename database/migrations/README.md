# Database migrations

Schema changes are applied **automatically**: push the code, and the first request that touches the
database applies every pending file here (once, under a lock). Applied files are recorded in the
`schema_migrations` table. No SQL has to be imported by hand.

- Status / manual run: **Super Admin → Platform Settings → Database updates**, or `php database/migrate.php [--status]`.
- A failed migration never takes the site down: it is logged, shown on that card, and retried every 5 minutes.
- On a brand-new empty database the baseline migration builds the whole schema from `database/bodaerp_full.sql`
  (it refuses to run over a partial schema).

## Adding a change

Create `database/migrations/YYYY_MM_DD_HHMMSS_short_name.php` (names run in alphabetical order):

```php
<?php
return [
    'description' => 'Riders: add a licence number',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'riders', 'licence_no', 'VARCHAR(30) NULL AFTER `nin`');
        mig_add_index($db, 'riders', 'idx_riders_licence', '`licence_no`');
        mig_exec($db, "CREATE TABLE IF NOT EXISTS ...");
    },
];
```

Rules: a migration must be **idempotent** (safe to run twice, and safe to resume after a half-finished run) —
use the `mig_*` helpers in `includes/migrator.php` (`mig_add_column`, `mig_add_index`, `mig_table_exists`,
`mig_column_exists`, `mig_column_type`, ...), which check `information_schema` first. Never edit a migration
that has already been released; add a new one. Also keep `schema.sql` / `bodaerp_full.sql` in step so fresh installs match.
