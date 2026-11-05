<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/db_helpers.php';

/** Uganda's fiscal year runs 1 July to 30 June, e.g. "2026/2027". */
function current_fiscal_year(?int $ts = null): string {
    $ts ??= time();
    $y = (int) date('Y', $ts);
    $start = (int) date('n', $ts) >= 7 ? $y : $y - 1;
    return $start . '/' . ($start + 1);
}
define('CURRENT_FISCAL_YEAR', current_fiscal_year());

function formatCurrency($amount, string $currency = 'UGX'): string {
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

function statusBadgeClass(string $status): string {
    return match (strtolower($status)) {
        'active', 'confirmed', 'resolved', 'paid' => 'bg-success',
        'expired', 'failed', 'suspension', 'impound' => 'bg-danger',
        'pending', 'warning' => 'bg-warning text-dark',
        'closed', 'fine' => 'bg-secondary',
        default => 'bg-secondary',
    };
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
