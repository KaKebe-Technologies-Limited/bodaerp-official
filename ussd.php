<?php
/**
 * USSD renewal. A USSD provider (Africa's Talking style: POST sessionId, serviceCode, phoneNumber, text) calls
 * this on every key press; `text` holds all answers so far joined by "*". Replies start with CON (ask more) or
 * END (close the menu). The money step is an ordinary ioTec collection: the caller still approves it with their
 * mobile-money PIN, so knowing a permit number lets you pay for it, never take money from someone else.
 * Callers are told only a masked name and the amount, never a rider's details.
 */
require_once __DIR__ . '/packages/backend/functions.php';
require_once __DIR__ . '/packages/backend/iotec_functions.php';
require_once __DIR__ . '/packages/backend/permits.php';
require_once __DIR__ . '/packages/backend/enforcement.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

function ussd_reply(string $kind, string $text): never {
    echo $kind . ' ' . $text;
    exit;
}

/** "J*** O***": enough for the caller to recognise the permit, not enough to look anyone up. */
function ussd_mask_name(string $name): string {
    return implode(' ', array_map(fn($part) => mb_substr($part, 0, 1) . '***', array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2)));
}

function ussd_rider_line(array $rider): string {
    $due = permit_amount_due($rider);
    $line = 'Permit ' . $rider['id_number'] . ' (' . ussd_mask_name($rider['full_name']) . ")\n";
    if (rider_suspension((int) $rider['id'])) $line .= "SUSPENDED by the city council\n";
    $line .= $rider['expiry_date'] ? (strtotime($rider['expiry_date']) < strtotime('today') ? 'Expired ' : 'Valid to ') . formatDate($rider['expiry_date']) . "\n" : '';
    $line .= 'Amount: ' . formatCurrency($due['total']) . ($due['late_fee'] > 0 ? ' (incl. late fee ' . formatCurrency($due['late_fee']) . ')' : '');
    return $line;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

$token = (string) env('USSD_TOKEN', '');
if ($token === '' || !hash_equals($token, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit('END Service unavailable.'); }
$allowed = array_filter(array_map('trim', explode(',', (string) env('USSD_ALLOWED_IPS', ''))));
if ($allowed && !in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed, true)) { http_response_code(403); exit('END Service unavailable.'); }

$site = get_setting('site_name', 'BodaERP');
if (!setting_bool('feature_ussd', true)) ussd_reply('END', "$site renewals by USSD are paused. Pay your stage chairperson.");

$sessionId = mb_substr(trim((string) ($_POST['sessionId'] ?? '')), 0, 100);
$phone = sms_normalize_phone((string) ($_POST['phoneNumber'] ?? ''));
$text = trim((string) ($_POST['text'] ?? ''));
if ($sessionId === '' || !$phone) ussd_reply('END', 'This service works from an MTN or Airtel Uganda line.');
if (!rate_limit('ussd:' . $phone, 40, 3600)) ussd_reply('END', 'Too many requests from this number. Try again in an hour.');

runQuery("INSERT INTO ussd_sessions (session_id, phone, steps, last_input) VALUES (?, ?, 1, ?)
          ON DUPLICATE KEY UPDATE steps = steps + 1, last_input = VALUES(last_input)", [$sessionId, $phone, mb_substr($text, 0, 200)])->close();

$answers = $text === '' ? [] : explode('*', $text);
$local = '0' . substr($phone, 3);
$riderCols = "id, id_number, full_name, status, expiry_date, annual_tax, city_id";
$ownRiders = fetchAll("SELECT $riderCols FROM riders WHERE deleted_at IS NULL AND phone IN (?, ?, ?) ORDER BY id LIMIT 3", [$local, $phone, '+' . $phone]);

if (!$answers) {
    ussd_reply('CON', "$site permits\n1. Renew a permit\n2. Check my permit");
}

if ($answers[0] === '2') {
    if (!$ownRiders) ussd_reply('END', 'No permit is registered to this number. Ask your stage chairperson to update your phone number.');
    ussd_reply('END', implode("\n\n", array_map('ussd_rider_line', $ownRiders)));
}

if ($answers[0] !== '1') ussd_reply('END', 'Choose 1 or 2. Dial again to start over.');

// Menus after "1. Renew":
//   one permit on this number: 1 -> confirm it (1 pay, 2 another permit) -> 1*2 -> permit number -> confirm -> pay
//   otherwise:                 1 -> permit number -> confirm (1 pay, 2 cancel) -> pay
$own = count($ownRiders) === 1 ? $ownRiders[0] : null;
if ($own && !isset($answers[1])) ussd_reply('CON', ussd_rider_line($own) . "\n1. Pay from this number\n2. A different permit");
if ($own && $answers[1] === '1') {
    $rider = $own;
} else {
    if ($own && $answers[1] !== '2') ussd_reply('END', 'Choose 1 or 2. Dial again to start over.');
    $rest = array_slice($answers, $own ? 2 : 1);            // [permit number, confirmation]
    if (!isset($rest[0])) ussd_reply('CON', 'Enter the permit number shown on the ID card, for example BODA-LIR-004521:');
    $entered = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $rest[0]));
    if (!isset($rest[1]) && !rate_limit('ussd-lookup:' . $phone, 10, 3600)) ussd_reply('END', 'Too many permit numbers tried. Try again in an hour.');
    $rider = $entered === '' ? null : fetchOne("SELECT $riderCols FROM riders WHERE deleted_at IS NULL AND id_number = ?", [$entered]);
    if (!$rider) ussd_reply('END', "No permit $entered was found. Check the number on the ID card and dial again.");
    if (!isset($rest[1])) ussd_reply('CON', ussd_rider_line($rider) . "\n1. Pay from this number\n2. Cancel");
    if ($rest[1] !== '1') ussd_reply('END', 'Cancelled. Nothing was charged.');
}

$full = fetchOne("SELECT * FROM riders WHERE id = ?", [$rider['id']]);
if ($reason = permit_payment_block_reason($full)) ussd_reply('END', $reason);
if (!setting_bool('feature_online_payments', true)) ussd_reply('END', 'Mobile-money payments are switched off. Pay your stage chairperson.');
if (!rate_limit('pay:' . $rider['id'], 5, 900)) ussd_reply('END', 'Several payment requests were sent recently. Wait 15 minutes.');

$result = initiateRiderPayment((int) $rider['id'], $local, 'ussd');
if (isset($result['error'])) {
    app_log('ussd', 'payment start failed', ['rider' => $rider['id'], 'error' => $result['error']]);
    ussd_reply('END', 'The payment could not be started. Try again later or pay your stage chairperson.');
}
runQuery("UPDATE ussd_sessions SET rider_id = ?, payment_id = ? WHERE session_id = ?", [$rider['id'], $result['payment_id'], $sessionId])->close();
ussd_reply('END', $result['status'] === 'completed'
    ? 'Payment received. Your receipt comes by SMS.'
    : 'Approve the payment with your mobile-money PIN when the prompt appears. Your receipt comes by SMS.');
