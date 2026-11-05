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

Rules: a migration must be **idempotent** (safe to run twice, and safe to resume after a half-finished run) , 
use the `mig_*` helpers in `packages/backend/migrator.php` (`mig_add_column`, `mig_add_index`, `mig_table_exists`,
`mig_column_exists`, `mig_column_type`, ...), which check `information_schema` first. Never edit a migration
that has already been released; add a new one.

## Schema file: structure changes need no migration

Plain structure changes (new table, new column, new index, a longer `varchar`, a new `enum`
value) do **not** need a migration file any more:

1. Change the database on your own machine any way you like (phpMyAdmin, SQL or a script).
   While you work on `localhost`, the app keeps **`database/schema.snapshot.json`** in step
   with your local database (structure only, never data).
2. Commit that file with your code and deploy.
3. On the first request after the deploy the server compares the file with its own
   database and **adds** whatever is missing. It never drops, renames or shrinks anything.

Use a **migration** (this folder) for everything the schema file deliberately does not do:
data fixes and backfills, renames, dropping or shrinking things, views/triggers, and any
change listed under "Needs review" in Super Admin → Database.

Order on every deploy: migrations first, then the schema file. Both run automatically, and
by hand with `php database/migrate.php` (`--status` to preview, `--capture` to save the file).
