<?php
// Live duplicate check for the registration form: answers whether a NIN, mobile number or plate is already
// registered, and at which stage. Rate limited, and it never returns names or other personal details.
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['chairperson']);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$field = (string) ($_GET['field'] ?? '');
$value = trim((string) ($_GET['value'] ?? ''));
if (!in_array($field, ['nin', 'bike_plate', 'phone'], true) || $value === '' || mb_strlen($value) > 40) { echo '{"matches":[]}'; exit; }
if (!rate_limit('dup-check:' . (int) $_SESSION['user_id'], 120, 3600)) { http_response_code(429); echo '{"matches":[]}'; exit; }

$matches = array_values(array_filter(rider_duplicates([$field => $field === 'phone' ? '+256' . substr(preg_replace('/\D/', '', $value), -9) : $value]),
    fn($m) => $m['field'] === $field));
echo json_encode(['matches' => array_map(fn($m) => ['where' => $m['where'], 'status' => $m['status']], $matches)]);
