<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/i18n.php';

/** Uganda's fiscal year runs 1 July to 30 June, e.g. "2026/2027". */
function current_fiscal_year(?int $ts = null): string {
    $ts ??= time();
    $y = (int) date('Y', $ts);
    $start = (int) date('n', $ts) >= 7 ? $y : $y - 1;
    return $start . '/' . ($start + 1);
}
define('CURRENT_FISCAL_YEAR', current_fiscal_year());

/** Money in the city's currency: the signed-in user's city unless one is given. */
function formatCurrency($amount, ?string $currency = null): string {
    $currency ??= $_SESSION['city_currency'] ?? 'UGX';
    return $currency . ' ' . number_format((float) $amount);
}

function formatDate(?string $date, string $fmt = 'M d, Y'): string {
    if (!$date) return '–';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '–';
}

function daysUntil(?string $date): ?int {
    if (!$date) return null;
    return (int) ceil((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', strtotime($datetime));
}

function prevFiscalYear(string $fy): string {
    [$a, $b] = explode('/', $fy);
    return ($a - 1) . '/' . ($b - 1);
}


function nextIdNumber(int $riderId, string $cityId): string {
    return 'BODA-' . $cityId . '-' . str_pad((string) $riderId, 6, '0', STR_PAD_LEFT);
}

function nextReceiptNumber(int $paymentId): string {
    return 'RCP-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
}

function nextActionCode(int $actionId): string {
    return 'ENF-' . date('Y') . '-' . str_pad((string) $actionId, 3, '0', STR_PAD_LEFT);
}

/**
 * One CSV row for a download. Cells that start with = + - @ (or a tab/CR) get a leading quote so a
 * spreadsheet shows them as text instead of running them as a formula. RFC 4180 quoting, no escape char.
 */
function csv_put($out, array $cells): void {
    fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $cells), ',', '"', '');
}

/** JSON for embedding inside a <script> block or an HTML attribute: "<", ">", "&" and quotes are escaped. */
function js_json(mixed $value, int $flags = 0): string {
    return json_encode($value, $flags | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?: 'null';
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/** Link printed in an ID card's QR code. Uses the rider's random token (creating one if missing). */
function verifyUrl(int $riderId): string {
    $token = fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$riderId]);
    if (!$token) {
        $token = bin2hex(random_bytes(10));
        runQuery("UPDATE riders SET verify_token = ? WHERE id = ? AND verify_token IS NULL", [$token, $riderId]);
        $token = fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$riderId]);
    }
    return app_url('verify.php?t=' . urlencode((string) $token));
}

/**
 * Adds a notification to a rider's portal, unless the rider's account has "Show notifications in my portal"
 * switched off. Riders without an account still get it, so it is waiting when their invite is accepted.
 */
function rider_notify(int $riderId, string $type, string $message): bool {
    $wants = fetchValue("SELECT COALESCE(u.notify_in_app, 1) FROM riders r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$riderId]);
    if ($wants === null || (int) $wants !== 1) return false;
    runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, ?, ?)", [$riderId, $type, mb_strimwidth($message, 0, 255, '…', 'UTF-8')])->close();
    return true;
}

/**
 * What a rider owes for the next renewal: the permit fee fixed at registration, plus the city's late fee
 * once the permit has been expired for longer than the city's grace period. Every payment path uses this,
 * so cash, mobile money and USSD always charge the same amount.
 * @return array{fee: float, late_fee: float, total: float, days_late: int, grace_days: int}
 */
function permit_amount_due(array $rider, bool $freshTerms = false): array {
    $fee = round((float) ($rider['annual_tax'] ?? 0), 2);
    static $cities = [];      // pages list many riders of the same city; one lookup per city per request
    $cityKey = (string) ($rider['city_id'] ?? '');
    if ($freshTerms) unset($cities[$cityKey]);
    $city = $cities[$cityKey] ??= fetchOne("SELECT grace_period_days, late_fee, late_fee_type FROM cities WHERE id = ?", [$cityKey]) ?? [];
    $grace = (int) ($city['grace_period_days'] ?? 0);
    $daysLate = !empty($rider['expiry_date']) && strtotime($rider['expiry_date']) < strtotime('today')
        ? (int) floor((strtotime('today') - strtotime($rider['expiry_date'])) / 86400) : 0;
    $late = 0.0;
    if ($daysLate > $grace && (float) ($city['late_fee'] ?? 0) > 0) {
        $late = ($city['late_fee_type'] ?? 'flat') === 'percent'
            ? round($fee * min(100, (float) $city['late_fee']) / 100, 0)
            : round((float) $city['late_fee'], 2);
    }
    return ['fee' => $fee, 'late_fee' => $late, 'total' => round($fee + $late, 2), 'days_late' => $daysLate, 'grace_days' => $grace];
}

/**
 * Riders already registered with the same NIN, mobile number or plate, in any city. Used at registration and
 * import. Returns where each match is (stage and city) and nothing personal, because the chairperson asking
 * may be in another city. @return list<array{field:string, label:string, where:string, status:string}>
 */
function rider_duplicates(array $values, ?int $exceptRiderId = null): array {
    $checks = [
        'nin'        => ['National ID number', strtoupper(trim((string) ($values['nin'] ?? '')))],
        'bike_plate' => ['Bike plate', strtoupper(preg_replace('/\s+/', ' ', trim((string) ($values['bike_plate'] ?? ''))))],
        'phone'      => ['Mobile number', (string) ($values['phone'] ?? '')],
    ];
    $found = [];
    foreach ($checks as $field => [$label, $value]) {
        if ($value === '' || $value === '+256') continue;
        $params = [$value];
        $where = "r.$field = ?";
        if ($field === 'phone') {
            $digits = substr(preg_replace('/\D/', '', $value), -9);
            if (strlen($digits) !== 9) continue;
            $where = "r.phone IN (?, ?, ?)";
            $params = ['+256' . $digits, '0' . $digits, '256' . $digits];
        }
        if ($field === 'bike_plate') { $where = "REPLACE(r.bike_plate, ' ', '') = ?"; $params = [str_replace(' ', '', $value)]; }
        $sql = "SELECT r.status, r.deleted_at, s.name AS stage, c.name AS city FROM riders r
                LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id WHERE $where";
        if ($exceptRiderId) { $sql .= " AND r.id <> ?"; $params[] = $exceptRiderId; }
        foreach (fetchAll($sql . " LIMIT 3", $params) as $row) {
            $found[] = ['field' => $field, 'label' => $label, 'where' => trim(($row['stage'] ?: 'no stage') . ', ' . ($row['city'] ?: 'unknown city')),
                'status' => $row['deleted_at'] ? 'removed' : $row['status']];
        }
    }
    return $found;
}

/** Notification types are stored as "Payment Confirmed"; people read "Payment confirmed". Short acronyms stay. */
function notification_label(string $type): string {
    $words = explode(' ', $type);
    foreach ($words as $i => $word) if ($i > 0 && !preg_match('/^[A-Z]{2,4}$/', $word)) $words[$i] = mb_strtolower($word);
    return implode(' ', $words);
}
