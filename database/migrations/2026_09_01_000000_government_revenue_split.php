<?php
// Government becomes a 4th revenue party. Existing rows keep summing to exactly 100 because the
// Government share is carved out of the existing Platform share (roughly half).
//
// Re-runnable at any point: every step is driven by the current state of the data, so a run that
// failed half-way repairs itself on the next attempt.
return [
    'description' => 'Revenue split: add Government as a 4th party',
    'up' => function (mysqli $db) {
        // 1. Older schemas carry a 3-column "must equal 100" CHECK that rejects the new split — drop it first.
        $clause = mig_check_clause($db, 'cities', 'chk_revenue_split');
        $oldCheck = $clause !== null && stripos($clause, 'government') === false;
        if ($oldCheck) {
            try { mig_exec($db, 'ALTER TABLE cities DROP CONSTRAINT chk_revenue_split'); }
            catch (Throwable $e) { mig_exec($db, 'ALTER TABLE cities DROP CHECK chk_revenue_split'); }
        }

        // 2. Columns.
        mig_add_column($db, 'cities', 'revenue_split_government', 'TINYINT UNSIGNED NOT NULL DEFAULT 10 AFTER `revenue_split_platform`');
        mig_add_column($db, 'platform_settings', 'default_split_government', 'TINYINT UNSIGNED NOT NULL DEFAULT 10 AFTER `default_split_platform`');

        // 3. Rows still on the old 3-way split (city+association+platform = 100, but 4-way total != 100):
        //    carve Government out of Platform. Rows that already total 100 are left untouched.
        mig_exec($db, "UPDATE cities
            SET revenue_split_government = FLOOR(revenue_split_platform / 2),
                revenue_split_platform   = revenue_split_platform - FLOOR(revenue_split_platform / 2)
            WHERE revenue_split_city + revenue_split_association + revenue_split_platform = 100
              AND revenue_split_city + revenue_split_association + revenue_split_platform + revenue_split_government <> 100");
        mig_exec($db, "UPDATE platform_settings
            SET default_split_government = FLOOR(default_split_platform / 2),
                default_split_platform   = default_split_platform - FLOOR(default_split_platform / 2)
            WHERE default_split_city + default_split_association + default_split_platform = 100
              AND default_split_city + default_split_association + default_split_platform + default_split_government <> 100");

        // 4. Defaults that sum to 100 for newly inserted rows.
        mig_exec($db, "ALTER TABLE cities ALTER COLUMN revenue_split_city SET DEFAULT 55, ALTER COLUMN revenue_split_platform SET DEFAULT 9");
        mig_exec($db, "ALTER TABLE platform_settings ALTER COLUMN default_split_city SET DEFAULT 55, ALTER COLUMN default_split_platform SET DEFAULT 9");

        // 5. Re-establish the integrity rule for four parties (only if every row satisfies it).
        if ($oldCheck) {
            $bad = (int) mig_value($db, "SELECT COUNT(*) FROM cities WHERE revenue_split_city + revenue_split_association + revenue_split_platform + revenue_split_government <> 100");
            if ($bad === 0) {
                mig_exec($db, 'ALTER TABLE cities ADD CONSTRAINT chk_revenue_split CHECK (revenue_split_city + revenue_split_association + revenue_split_platform + revenue_split_government = 100)');
            } else {
                error_log("BodaERP: {$bad} city row(s) do not total 100% — chk_revenue_split not re-added.");
            }
        }
    },
];
