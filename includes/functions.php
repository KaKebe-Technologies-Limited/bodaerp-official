<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db_helpers.php';

define('CURRENT_FISCAL_YEAR', '2026/2027');

function formatCurrency($amount, string $currency = 'UGX'): string {
    return $currency . ' ' . number_format((float) $amount);
}

function formatDate(?string $date, string $fmt = 'M d, Y'): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date($fmt, $ts) : '—';
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
    return 'ENF-2026-' . str_pad((string) $actionId, 3, '0', STR_PAD_LEFT);
}

function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function verifyUrl(string $idNumber): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . '/verify.php?code=' . urlencode($idNumber);
}
