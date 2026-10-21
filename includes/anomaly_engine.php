<?php
// ============================================================
// BodaERP — Anomaly & Data-Quality Detection Engine
//
// Explainable, rule-based detection over riders, payments,
// enforcement actions and audit logs. Every flag carries a plain-
// language reason; flags are fingerprinted (re-scans never duplicate),
// reviewed by humans, and auto-resolve once the data is fixed.
// Scores are written to `anomaly_flags`; the UI only reads them.
// ============================================================

require_once __DIR__ . '/db_helpers.php';

const ANOMALY_COLLECTOR_DAILY_LIMIT = 15;   // confirmed payments by one collector in a day
const ANOMALY_STALE_HOURS           = 24;   // auto-rescan interval
const ANOMALY_ACTIVE_STATUSES       = "'open','confirmed'";

function anomaly_categories(): array {
    return [
        'identity'     => ['Identity & duplicates', '#5b4bd5'],
        'payment'      => ['Payments & revenue',    '#f59e0b'],
        'compliance'   => ['Compliance status',     '#2cc6a8'],
        'enforcement'  => ['Enforcement',           '#e5484d'],
        'security'     => ['Security',              '#1f1b3a'],
        'data_quality' => ['Data quality',          '#e0529c'],
    ];
}

function anomaly_rules(): array {
    return [
        'DUP_PLATE' => ['Duplicate bike plate', 'identity', 'fa-clone', 'high',
            'The same plate number (ignoring spaces and case) is registered to more than one rider — a cloned plate, a transferred bike not updated, or a double registration.'],
        'DUP_PHONE' => ['Shared phone number', 'identity', 'fa-phone', 'medium',
            'One phone number appears on several rider records. Can be a family phone, but is also a common sign of fake or duplicate registrations.'],
        'DUP_PERSON' => ['Possible double registration', 'identity', 'fa-user-friends', 'high',
            'Two or more riders share the same full name and date of birth but have different NINs — likely one person registered twice.'],
        'UNDERAGE' => ['Rider under 18', 'identity', 'fa-child', 'high',
            'The date of birth makes the rider younger than 18, which is below the legal age to operate a commercial motorcycle.'],
        'AGE_UNUSUAL' => ['Unusual rider age', 'identity', 'fa-hourglass-end', 'low',
            'The rider is older than 75 — usually a date-of-birth typing error worth confirming.'],
        'ACTIVE_NO_PAYMENT' => ['Active without any payment', 'payment', 'fa-file-invoice-dollar', 'high',
            'The rider is marked active and was registered over 14 days ago, yet has no confirmed payment on record — possible unpaid activation.'],
        'PAYMENT_AMOUNT' => ['Payment differs from city fee', 'payment', 'fa-coins', 'medium',
            'A confirmed payment in the last 12 months does not equal the city\'s annual fee (under- or over-payment). Instalments can be legitimate, so this is for review.'],
        'PAYMENT_DUPLICATE' => ['Possible duplicate payment', 'payment', 'fa-copy', 'medium',
            'The same rider has two or more confirmed payments of the same amount on the same day — a double charge or double entry.'],
        'COLLECTOR_BURST' => ['Collector volume spike', 'payment', 'fa-bolt', 'medium',
            'One collector recorded an unusually high number of payments in a single day (limit ' . ANOMALY_COLLECTOR_DAILY_LIMIT . ') — a pattern worth reconciling with cash handed in.'],
        'STATUS_STALE' => ['Active but permit expired', 'compliance', 'fa-calendar-times', 'medium',
            'Status still says active although the permit expiry date has passed. Statuses are not auto-updated, so compliance figures are overstated until fixed.'],
        'STATUS_CONFLICT' => ['Expired but permit valid', 'compliance', 'fa-random', 'medium',
            'Status says expired while the expiry date is still in the future — the status or the date is wrong.'],
        'REPEAT_OFFENDER' => ['Repeat enforcement actions', 'enforcement', 'fa-gavel', 'medium',
            'Three or more enforcement actions against the same rider in 180 days. Flagged for human review — never an automatic penalty.'],
        'LOGIN_BRUTE_FORCE' => ['Repeated failed logins (account)', 'security', 'fa-user-lock', 'high',
            'Five or more failed sign-in attempts for the same email within 24 hours — possible password guessing.'],
        'IP_BRUTE_FORCE' => ['Repeated failed logins (IP)', 'security', 'fa-network-wired', 'high',
            'Ten or more failed sign-ins from the same IP address within 24 hours across any accounts.'],
        'BAD_DATES' => ['Inconsistent dates', 'data_quality', 'fa-calendar-minus', 'low',
            'Registration date is in the future, or the permit expires before the rider was registered.'],
    ];
}

// ---------- availability / small helpers ----------

function anomaly_tables_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { fetchValue("SELECT 1 FROM anomaly_flags LIMIT 1"); $ready = true; }
    catch (\Throwable $e) { $ready = false; }
    return $ready;
}

function anomaly_last_run(): ?array {
    if (!anomaly_tables_ready()) return null;
    try { return fetchOne("SELECT * FROM anomaly_scan_runs ORDER BY id DESC LIMIT 1"); }
    catch (\Throwable $e) { return null; }
}

function anomaly_open_count(string $severity = 'high', ?string $cityId = null): int {
    if (!anomaly_tables_ready()) return 0;
    try {
        $sql = "SELECT COUNT(*) FROM anomaly_flags WHERE status IN (" . ANOMALY_ACTIVE_STATUSES . ") AND severity = ?";
        $params = [$severity];
        if ($cityId) { $sql .= " AND city_id = ?"; $params[] = $cityId; }
        return (int) fetchValue($sql, $params);
    } catch (\Throwable $e) { return 0; }
}

function anomaly_scan_if_stale(string $triggeredBy = 'auto'): ?array {
    if (!anomaly_tables_ready()) return null;
    $last = anomaly_last_run();
    if ($last && strtotime($last['started_at']) > time() - ANOMALY_STALE_HOURS * 3600) return null;
    return run_anomaly_scan($triggeredBy);
}

function _anomaly_row(string $rule, string $key, string $sev, string $etype, string $eid, ?int $rider, ?string $city, string $title, string $details = ''): array {
    $cat = anomaly_rules()[$rule][1];
    return [
        'fp' => $rule . ':' . substr(md5($key), 0, 24), 'rule' => $rule, 'cat' => $cat, 'sev' => $sev,
        'etype' => $etype, 'eid' => mb_substr($eid, 0, 80), 'rider' => $rider, 'city' => $city,
        'title' => mb_substr($title, 0, 200), 'details' => mb_substr($details, 0, 600),
    ];
}

// ---------- detectors (each returns a list of rows) ----------

function anomaly_detectors(): array {
    return [
    'DUP_PLATE' => function () {
        $out = [];
        $rows = fetchAll("SELECT UPPER(REPLACE(bike_plate,' ','')) AS norm, COUNT(*) AS c, MIN(id) AS first_id, MIN(city_id) AS city,
            COUNT(DISTINCT city_id) AS cities, GROUP_CONCAT(full_name ORDER BY id SEPARATOR ', ') AS names
            FROM riders WHERE deleted_at IS NULL AND bike_plate <> '' GROUP BY norm HAVING c > 1");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('DUP_PLATE', $r['norm'], 'high', 'plate', $r['norm'], (int) $r['first_id'], $r['city'],
                "Plate {$r['norm']} is registered to {$r['c']} riders",
                'Riders: ' . mb_substr($r['names'], 0, 300) . ($r['cities'] > 1 ? " — across {$r['cities']} cities." : '.'));
        }
        return $out;
    },
    'DUP_PHONE' => function () {
        $out = [];
        $rows = fetchAll("SELECT REPLACE(REPLACE(REPLACE(phone,' ',''),'+',''),'-','') AS norm, COUNT(*) AS c, MIN(id) AS first_id,
            MIN(city_id) AS city, GROUP_CONCAT(full_name ORDER BY id SEPARATOR ', ') AS names
            FROM riders WHERE deleted_at IS NULL AND phone <> '' GROUP BY norm HAVING c > 1");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('DUP_PHONE', $r['norm'], 'medium', 'phone', $r['norm'], (int) $r['first_id'], $r['city'],
                "Phone +{$r['norm']} is used by {$r['c']} riders", 'Riders: ' . mb_substr($r['names'], 0, 300) . '.');
        }
        return $out;
    },
    'DUP_PERSON' => function () {
        $out = [];
        $rows = fetchAll("SELECT UPPER(TRIM(full_name)) AS nm, date_of_birth AS dob, COUNT(*) AS c, MIN(id) AS first_id, MIN(city_id) AS city,
            COUNT(DISTINCT nin) AS nins, GROUP_CONCAT(id_number ORDER BY id SEPARATOR ', ') AS ids
            FROM riders WHERE deleted_at IS NULL AND date_of_birth IS NOT NULL GROUP BY nm, dob HAVING c > 1");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('DUP_PERSON', $r['nm'] . '|' . $r['dob'], 'high', 'person', $r['nm'], (int) $r['first_id'], $r['city'],
                "{$r['nm']} (born {$r['dob']}) has {$r['c']} rider records",
                "Rider IDs: {$r['ids']}. Same name and date of birth with {$r['nins']} different NIN(s).");
        }
        return $out;
    },
    'UNDERAGE' => function () {
        $out = [];
        $rows = fetchAll("SELECT id, full_name, city_id, date_of_birth, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age
            FROM riders WHERE deleted_at IS NULL AND date_of_birth IS NOT NULL AND date_of_birth > DATE_SUB(CURDATE(), INTERVAL 18 YEAR)");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('UNDERAGE', (string) $r['id'], 'high', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                "{$r['full_name']} is only {$r['age']} years old", "Date of birth {$r['date_of_birth']} — below the minimum age of 18.");
        }
        return $out;
    },
    'AGE_UNUSUAL' => function () {
        $out = [];
        $rows = fetchAll("SELECT id, full_name, city_id, date_of_birth, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age
            FROM riders WHERE deleted_at IS NULL AND date_of_birth IS NOT NULL AND date_of_birth < DATE_SUB(CURDATE(), INTERVAL 75 YEAR)");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('AGE_UNUSUAL', (string) $r['id'], 'low', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                "{$r['full_name']} is recorded as {$r['age']} years old", "Date of birth {$r['date_of_birth']} — confirm it is not a typing error.");
        }
        return $out;
    },
    'ACTIVE_NO_PAYMENT' => function () {
        $out = [];
        $rows = fetchAll("SELECT r.id, r.full_name, r.city_id, r.member_since FROM riders r
            WHERE r.deleted_at IS NULL AND r.status = 'active' AND r.member_since <= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.rider_id = r.id AND p.status = 'Confirmed')");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('ACTIVE_NO_PAYMENT', (string) $r['id'], 'high', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                "{$r['full_name']} is active with no confirmed payment", "Registered {$r['member_since']}; no confirmed payment exists.");
        }
        return $out;
    },
    'PAYMENT_AMOUNT' => function () {
        $out = [];
        $rows = fetchAll("SELECT p.id, p.rider_id, p.city_id, p.amount, c.annual_fee, p.receipt_number, DATE(p.paid_at) AS d
            FROM payments p JOIN cities c ON c.id = p.city_id
            WHERE p.status = 'Confirmed' AND p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY) AND p.amount <> c.annual_fee");
        foreach ($rows as $r) {
            $diff = (float) $r['amount'] - (float) $r['annual_fee'];
            $dir  = $diff < 0 ? 'under' : 'over';
            $out[] = _anomaly_row('PAYMENT_AMOUNT', (string) $r['id'], 'medium', 'payment', (string) $r['id'], (int) $r['rider_id'], $r['city_id'],
                'Receipt ' . $r['receipt_number'] . ' is ' . $dir . '-paid by UGX ' . number_format(abs($diff)),
                'Paid UGX ' . number_format($r['amount']) . ' on ' . $r['d'] . ' against an annual fee of UGX ' . number_format($r['annual_fee']) . '.');
        }
        return $out;
    },
    'PAYMENT_DUPLICATE' => function () {
        $out = [];
        $rows = fetchAll("SELECT MIN(id) AS pid, rider_id, amount, DATE(paid_at) AS d, COUNT(*) AS c, MIN(city_id) AS city
            FROM payments WHERE status = 'Confirmed' GROUP BY rider_id, amount, DATE(paid_at) HAVING c > 1");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('PAYMENT_DUPLICATE', $r['rider_id'] . '|' . $r['amount'] . '|' . $r['d'], 'medium', 'payment', (string) $r['pid'], (int) $r['rider_id'], $r['city'],
                "{$r['c']} identical payments of UGX " . number_format($r['amount']) . " on {$r['d']}",
                'Same rider, same amount, same day — check for a double charge or duplicate entry.');
        }
        return $out;
    },
    'COLLECTOR_BURST' => function () {
        $out = [];
        $rows = fetchAll("SELECT p.collected_by, u.name, DATE(p.paid_at) AS d, COUNT(*) AS c, SUM(p.amount) AS total, MIN(p.city_id) AS city
            FROM payments p LEFT JOIN users u ON u.id = p.collected_by
            WHERE p.status = 'Confirmed' AND p.collected_by IS NOT NULL AND p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY p.collected_by, u.name, DATE(p.paid_at) HAVING c >= ?", [ANOMALY_COLLECTOR_DAILY_LIMIT]);
        foreach ($rows as $r) {
            $sev = $r['c'] >= 2 * ANOMALY_COLLECTOR_DAILY_LIMIT ? 'high' : 'medium';
            $who = $r['name'] ?: ('user #' . $r['collected_by']);
            $out[] = _anomaly_row('COLLECTOR_BURST', $r['collected_by'] . '|' . $r['d'], $sev, 'user', (string) $r['collected_by'], null, $r['city'],
                "$who recorded {$r['c']} payments on {$r['d']}",
                'Total UGX ' . number_format($r['total']) . '. Reconcile against cash/mobile-money actually received.');
        }
        return $out;
    },
    'STATUS_STALE' => function () {
        $out = [];
        $rows = fetchAll("SELECT id, full_name, city_id, expiry_date, DATEDIFF(CURDATE(), expiry_date) AS od
            FROM riders WHERE deleted_at IS NULL AND status = 'active' AND expiry_date < CURDATE()");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('STATUS_STALE', (string) $r['id'], $r['od'] > 30 ? 'medium' : 'low', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                "{$r['full_name']} is active but the permit expired {$r['od']} days ago", "Permit expiry {$r['expiry_date']}. Update the status to expired or record the renewal.");
        }
        return $out;
    },
    'STATUS_CONFLICT' => function () {
        $out = [];
        $rows = fetchAll("SELECT id, full_name, city_id, expiry_date FROM riders
            WHERE deleted_at IS NULL AND status = 'expired' AND expiry_date >= CURDATE()");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('STATUS_CONFLICT', (string) $r['id'], 'medium', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                "{$r['full_name']} is expired but the permit is valid until {$r['expiry_date']}", 'Status and expiry date disagree.');
        }
        return $out;
    },
    'REPEAT_OFFENDER' => function () {
        $out = [];
        $rows = fetchAll("SELECT e.rider_id, COUNT(*) AS c, MIN(r.city_id) AS city, r.full_name, r.bike_plate
            FROM enforcement_actions e JOIN riders r ON r.id = e.rider_id
            WHERE e.rider_id IS NOT NULL AND e.action_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
            GROUP BY e.rider_id, r.full_name, r.bike_plate HAVING c >= 3");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('REPEAT_OFFENDER', (string) $r['rider_id'], $r['c'] >= 5 ? 'high' : 'medium', 'rider', (string) $r['rider_id'], (int) $r['rider_id'], $r['city'],
                "{$r['full_name']} ({$r['bike_plate']}) has {$r['c']} enforcement actions in 180 days",
                'For human review of the underlying incidents; this flag is not a penalty.');
        }
        return $out;
    },
    'LOGIN_BRUTE_FORCE' => function () {
        $out = [];
        $rows = fetchAll("SELECT user_name_snapshot AS ident, COUNT(*) AS c, COUNT(DISTINCT ip_address) AS ips
            FROM audit_logs WHERE action = 'LOGIN_FAILED' AND user_name_snapshot IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY user_name_snapshot HAVING c >= 5");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('LOGIN_BRUTE_FORCE', $r['ident'] . '|' . date('Y-m-d'), 'high', 'login', $r['ident'], null, null,
                "{$r['c']} failed sign-ins for {$r['ident']} in 24h", "Attempts came from {$r['ips']} IP address(es).");
        }
        return $out;
    },
    'IP_BRUTE_FORCE' => function () {
        $out = [];
        $rows = fetchAll("SELECT ip_address AS ip, COUNT(*) AS c, COUNT(DISTINCT user_name_snapshot) AS accts
            FROM audit_logs WHERE action = 'LOGIN_FAILED' AND ip_address IS NOT NULL AND ip_address <> '' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY ip_address HAVING c >= 10");
        foreach ($rows as $r) {
            $out[] = _anomaly_row('IP_BRUTE_FORCE', $r['ip'] . '|' . date('Y-m-d'), 'high', 'ip', $r['ip'], null, null,
                "{$r['c']} failed sign-ins from IP {$r['ip']} in 24h", "Targeted {$r['accts']} different account(s).");
        }
        return $out;
    },
    'BAD_DATES' => function () {
        $out = [];
        $rows = fetchAll("SELECT id, full_name, city_id, member_since, expiry_date FROM riders WHERE deleted_at IS NULL
            AND (member_since > CURDATE() OR (expiry_date IS NOT NULL AND expiry_date < member_since))");
        foreach ($rows as $r) {
            $future = $r['member_since'] > date('Y-m-d');
            $out[] = _anomaly_row('BAD_DATES', (string) $r['id'], 'low', 'rider', (string) $r['id'], (int) $r['id'], $r['city_id'],
                $future ? "{$r['full_name']} has a registration date in the future" : "{$r['full_name']}'s permit expires before registration",
                "Registered {$r['member_since']}; permit expiry " . ($r['expiry_date'] ?: 'none') . '.');
        }
        return $out;
    },
    ];
}

// ---------- scan ----------

function run_anomaly_scan(string $triggeredBy = 'manual'): array {
    if (!anomaly_tables_ready()) return ['error' => 'Anomaly tables are missing. Run database/add_anomaly_detection.sql.'];
    if (!(int) fetchValue("SELECT GET_LOCK('bodaerp_anomaly_scan', 0)")) return ['skipped' => true];

    $t0 = microtime(true);
    try {
        $scanTs = fetchValue("SELECT NOW()");
        $stmt = db()->prepare(
            "INSERT INTO anomaly_flags (fingerprint,rule_code,category,severity,entity_type,entity_id,rider_id,city_id,title,details,status,first_detected_at,last_seen_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,'open',?,?)
             ON DUPLICATE KEY UPDATE
               last_seen_at = VALUES(last_seen_at), title = VALUES(title), details = VALUES(details),
               severity = IF(status IN ('open','confirmed','resolved'), VALUES(severity), severity),
               resolved_at = IF(status = 'resolved', NULL, resolved_at),
               status = IF(status = 'resolved', 'open', status)");
        $fp = $rule = $cat = $sev = $etype = $eid = $city = $title = $details = null; $rider = null; $ts1 = $ts2 = $scanTs;
        $stmt->bind_param('ssssssisssss', $fp, $rule, $cat, $sev, $etype, $eid, $rider, $city, $title, $details, $ts1, $ts2);

        $new = 0; $errors = [];
        foreach (anomaly_detectors() as $code => $detect) {
            try {
                foreach ($detect() as $row) {
                    $fp = $row['fp']; $rule = $row['rule']; $cat = $row['cat']; $sev = $row['sev']; $etype = $row['etype']; $eid = $row['eid'];
                    $rider = $row['rider']; $city = $row['city']; $title = $row['title']; $details = $row['details'];
                    $stmt->execute();
                    if ($stmt->affected_rows === 1) $new++;
                }
            } catch (\Throwable $e) { $errors[$code] = $e->getMessage(); }
        }
        $stmt->close();

        $res = runQuery("UPDATE anomaly_flags SET status = 'resolved', resolved_at = NOW()
                         WHERE status IN (" . ANOMALY_ACTIVE_STATUSES . ") AND last_seen_at < ?", [$scanTs]);
        $resolved = max(0, $res->affected_rows);
        $res->close();

        $open = (int) fetchValue("SELECT COUNT(*) FROM anomaly_flags WHERE status IN (" . ANOMALY_ACTIVE_STATUSES . ")");
        $ms = (int) round((microtime(true) - $t0) * 1000);
        runQuery("INSERT INTO anomaly_scan_runs (started_at, finished_at, triggered_by, new_flags, resolved_flags, open_flags, duration_ms)
                  VALUES (?, NOW(), ?, ?, ?, ?, ?)", [$scanTs, mb_substr($triggeredBy, 0, 40), $new, $resolved, $open, $ms])->close();

        return ['new' => $new, 'resolved' => $resolved, 'open' => $open, 'ms' => $ms, 'errors' => $errors];
    } finally {
        fetchValue("SELECT RELEASE_LOCK('bodaerp_anomaly_scan')");
    }
}

// ---------- reading ----------

function anomaly_summary(?string $cityId = null): array {
    $cityWhere = $cityId ? ' AND city_id = ?' : '';
    $p = $cityId ? [$cityId] : [];
    $act = ANOMALY_ACTIVE_STATUSES;

    $bySev = array_column(fetchAll("SELECT severity, COUNT(*) AS c FROM anomaly_flags WHERE status IN ($act)$cityWhere GROUP BY severity", $p), 'c', 'severity');
    $byRule = array_column(fetchAll("SELECT rule_code, COUNT(*) AS c FROM anomaly_flags WHERE status IN ($act)$cityWhere GROUP BY rule_code ORDER BY c DESC", $p), 'c', 'rule_code');
    $byCat = array_column(fetchAll("SELECT category, COUNT(*) AS c FROM anomaly_flags WHERE status IN ($act)$cityWhere GROUP BY category", $p), 'c', 'category');
    $flaggedRiders = (int) fetchValue("SELECT COUNT(DISTINCT rider_id) FROM anomaly_flags WHERE status IN ($act) AND rider_id IS NOT NULL$cityWhere", $p);
    $totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL" . ($cityId ? ' AND city_id = ?' : ''), $p);

    $sev = ['high' => (int) ($bySev['high'] ?? 0), 'medium' => (int) ($bySev['medium'] ?? 0), 'low' => (int) ($bySev['low'] ?? 0)];
    return [
        'open' => array_sum($sev), 'sev' => $sev, 'by_rule' => $byRule, 'by_cat' => $byCat,
        'flagged_riders' => $flaggedRiders, 'total_riders' => $totalRiders,
        'health' => $totalRiders ? max(0, round(100 * (1 - $flaggedRiders / $totalRiders), 1)) : 100,
    ];
}

function anomaly_list(array $f, int $limit, int $offset): array {
    $where = ['1=1']; $p = [];
    $status = $f['status'] ?? 'active';
    if ($status === 'active')      $where[] = "f.status IN (" . ANOMALY_ACTIVE_STATUSES . ")";
    elseif ($status !== 'all')     { $where[] = 'f.status = ?'; $p[] = $status; }
    if (!empty($f['severity']))    { $where[] = 'f.severity = ?'; $p[] = $f['severity']; }
    if (!empty($f['rule']))        { $where[] = 'f.rule_code = ?'; $p[] = $f['rule']; }
    if (!empty($f['category']))    { $where[] = 'f.category = ?'; $p[] = $f['category']; }
    if (!empty($f['city']))        { $where[] = 'f.city_id = ?'; $p[] = $f['city']; }
    if (($f['q'] ?? '') !== '') {
        $where[] = '(f.title LIKE ? OR f.details LIKE ? OR f.entity_id LIKE ? OR r.full_name LIKE ? OR r.bike_plate LIKE ?)';
        $like = '%' . $f['q'] . '%'; array_push($p, $like, $like, $like, $like, $like);
    }
    $w = implode(' AND ', $where);
    $from = "FROM anomaly_flags f LEFT JOIN riders r ON r.id = f.rider_id LEFT JOIN cities c ON c.id = f.city_id LEFT JOIN users u ON u.id = f.reviewed_by WHERE $w";
    $total = (int) fetchValue("SELECT COUNT(*) $from", $p);
    $rows = fetchAll("SELECT f.*, r.full_name AS rider_name, r.bike_plate, c.name AS city_name, u.name AS reviewer_name $from
        ORDER BY FIELD(f.severity,'high','medium','low'), f.last_seen_at DESC, f.id DESC LIMIT ? OFFSET ?", array_merge($p, [$limit, $offset]));
    return [$rows, $total];
}

function anomaly_review(int $flagId, string $action, string $note, int $userId): bool {
    $map = ['confirm' => 'confirmed', 'dismiss' => 'dismissed', 'reopen' => 'open'];
    if (!isset($map[$action])) return false;
    $res = runQuery("UPDATE anomaly_flags SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?",
        [$map[$action], $userId, mb_substr(trim($note), 0, 300) ?: null, $flagId]);
    $ok = $res->affected_rows > 0; $res->close();
    return $ok;
}
