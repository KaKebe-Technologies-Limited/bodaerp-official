<?php
// SMS through EgoSMS (egosms.co, JSON API). Same shape as mailer.php: templates live in the database
// (sms_templates, one row per code and language), every send is written to sms_log, and without
// credentials messages are written to storage/sms/ instead, so development never texts a real phone.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/i18n.php';

const EGOSMS_LIVE_URL = 'https://www.egosms.co/api/v1/json/';
const EGOSMS_SANDBOX_URL = 'http://sandbox.egosms.co/api/v1/json/';

function sms_config(): array {
    static $cfg = null;
    return $cfg ??= [
        'username' => trim((string) env('EGOSMS_USERNAME', '')),
        'password' => (string) env('EGOSMS_PASSWORD', ''),
        'sender'   => mb_substr(trim((string) env('EGOSMS_SENDER', '')) ?: 'BodaERP', 0, 11),
        'mode'     => strtolower((string) env('EGOSMS_MODE', 'sandbox')) === 'live' ? 'live' : 'sandbox',
    ];
}

/** 'egosms' when credentials are set, otherwise 'log' (messages go to storage/sms/). */
function sms_transport_name(): string {
    $c = sms_config();
    return $c['username'] !== '' && $c['password'] !== '' ? 'egosms' : 'log';
}

/** True when SMS really leaves the server and the feature switch is on. */
function sms_ready(): bool { return sms_transport_name() === 'egosms' && setting_bool('feature_sms', true); }

/** Ugandan mobile number as 2567XXXXXXXX, or null when it is not one. */
function sms_normalize_phone(?string $phone): ?string {
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (str_starts_with($digits, '256')) $digits = substr($digits, 3);
    elseif (str_starts_with($digits, '0')) $digits = substr($digits, 1);
    return preg_match('/^7\d{8}$/', $digits) ? '256' . $digits : null;
}

/** Last three digits for messages such as "we sent a code to the number ending 123". */
function sms_phone_hint(string $phone): string { return substr(preg_replace('/\D/', '', $phone), -3); }

/** Number of SMS parts the message will be billed as (GSM-7: 160/153, otherwise UCS-2: 70/67). */
function sms_segments(string $text): int {
    $gsm = preg_match('/^[A-Za-z0-9 @£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!"#¤%&\'()*+,\-.\/:;<=>?¡ÄÖÑÜ§¿äöñüà\r\n^{}\\\\\[~\]|€]*$/u', $text) === 1;
    $len = mb_strlen($text);
    if ($gsm) return $len <= 160 ? 1 : (int) ceil($len / 153);
    return $len <= 70 ? 1 : (int) ceil($len / 67);
}

/** Template text for $code in $language, falling back to English; null when missing or switched off. */
function sms_template(string $code, string $language = 'en'): ?array {
    $rows = fetchAll("SELECT * FROM sms_templates WHERE code = ? AND language IN (?, 'en') AND is_active = 1", [$code, $language]);
    $byLang = array_column($rows, null, 'language');
    $row = $byLang[$language] ?? $byLang['en'] ?? null;
    if ($row && $row['language'] !== 'en' && (int) $row['needs_review'] === 1) $row = $byLang['en'] ?? null;
    return $row;
}

function sms_render(string $code, array $vars, string $language = 'en'): ?string {
    $tpl = sms_template($code, $language);
    if (!$tpl) return null;
    $vars += ['site_name' => get_setting('site_name', 'BodaERP')];
    $text = preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', fn($m) => (string) ($vars[$m[1]] ?? ''), $tpl['body']);
    return trim(preg_replace('/[ \t]+/', ' ', $text));
}

/** Sends a template SMS. Never throws: the result is in the return value and sms_log. */
function send_template_sms(string $code, ?string $phone, array $vars = [], string $language = 'en'): bool {
    try {
        $text = sms_render($code, $vars, $language);
        if ($text === null) { app_log('sms', "template $code missing or switched off"); return false; }
        return send_sms((string) $phone, $text, $code, $language);
    } catch (Throwable $e) {
        app_log('sms', "template $code failed: " . $e->getMessage());
        return false;
    }
}

function send_sms(string $phone, string $text, ?string $templateCode = null, string $language = 'en'): bool {
    $to = sms_normalize_phone($phone);
    if ($to === null || trim($text) === '' || !setting_bool('feature_sms', true)) return false;
    $cfg = sms_config();
    $status = 'logged'; $error = null; $cost = null; $ref = null;
    if (sms_transport_name() === 'egosms') {
        try {
            $reply = egosms_request([
                'method' => 'SendSms',
                'userdata' => ['username' => $cfg['username'], 'password' => $cfg['password']],
                'msgdata' => [['number' => $to, 'message' => $text, 'senderid' => $cfg['sender'], 'priority' => '0']],
            ]);
            if (strcasecmp((string) ($reply['Status'] ?? ''), 'OK') === 0) {
                $status = 'sent';
                $cost = isset($reply['Cost']) && is_numeric($reply['Cost']) ? (float) $reply['Cost'] : null;
                $ref = isset($reply['MsgFollowUpUniqueCode']) ? mb_substr((string) $reply['MsgFollowUpUniqueCode'], 0, 100) : null;
            } else {
                $status = 'failed';
                $error = mb_substr((string) ($reply['Message'] ?? 'EgoSMS refused the message'), 0, 500);
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 500);
        }
        if ($status === 'failed') app_log('sms', 'EgoSMS failed: ' . $error, ['to' => sms_phone_hint($to), 'template' => $templateCode]);
    } else {
        $dir = storage_path('sms');
        if (ensure_dir($dir)) @file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt', "To: +$to\nTemplate: $templateCode\n\n$text\n");
    }
    try {
        runQuery("INSERT INTO sms_log (to_phone, template_code, language, transport, status, segments, cost, provider_ref, error) VALUES (?,?,?,?,?,?,?,?,?)",
            [$to, $templateCode, $language, sms_transport_name(), $status, sms_segments($text), $cost, $ref, $error])->close();
    } catch (Throwable $e) { app_log('sms', 'sms_log insert failed: ' . $e->getMessage()); }
    return $status !== 'failed';
}

/** Account balance as EgoSMS reports it, or null when not configured or unreachable. */
function egosms_balance(): ?string {
    if (sms_transport_name() !== 'egosms') return null;
    $cfg = sms_config();
    try {
        $reply = egosms_request(['method' => 'Balance', 'userdata' => ['username' => $cfg['username'], 'password' => $cfg['password']]]);
        return isset($reply['Balance']) ? (string) $reply['Balance'] : null;
    } catch (Throwable $e) {
        app_log('sms', 'EgoSMS balance failed: ' . $e->getMessage());
        return null;
    }
}

function egosms_request(array $payload): array {
    $url = sms_config()['mode'] === 'live' ? EGOSMS_LIVE_URL : EGOSMS_SANDBOX_URL;
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if ($raw === false) throw new RuntimeException('Could not reach EgoSMS: ' . $err);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n", 'content' => $body]]));
        if ($raw === false) throw new RuntimeException('Could not reach EgoSMS');
        $code = 200;
    }
    $reply = json_decode((string) $raw, true);
    if (!is_array($reply)) throw new RuntimeException("EgoSMS answered HTTP $code with no JSON");
    return $reply;
}

/**
 * Who should get an SMS about a rider, and in which language. Null when the rider has no usable mobile
 * number, opted out, or their portal account switched SMS off.
 */
function rider_sms_target(int $riderId): ?array {
    $r = fetchOne("SELECT r.phone, r.language, r.sms_opt_out, u.phone AS account_phone, u.notify_sms
        FROM riders r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$riderId]);
    if (!$r || (int) $r['sms_opt_out'] === 1 || ($r['notify_sms'] !== null && (int) $r['notify_sms'] !== 1)) return null;
    $phone = sms_normalize_phone($r['phone']) ?? sms_normalize_phone($r['account_phone']);
    return $phone ? ['phone' => $phone, 'language' => language_or_default($r['language'])] : null;
}

/** " or dial *217*55#" when a USSD code is configured, so templates read naturally either way. */
function sms_ussd_hint(): string {
    $code = trim(get_setting('ussd_code', ''));
    return $code === '' ? '' : ' or dial ' . $code;
}

/**
 * A reminder pressed by a chairperson or city admin: portal notification plus one SMS. The SMS is limited to
 * one per rider per day, so repeated clicks cannot run up the SMS bill. Returns a sentence for the flash message.
 */
function rider_send_reminder(int $riderId, string $message): string {
    $inApp = rider_notify($riderId, 'Renewal Reminder', $message);
    $texted = false;
    if (sms_ready() && ($target = rider_sms_target($riderId)) && rate_limit('reminder-sms:' . $riderId, 1, 86400)) {
        $texted = send_template_sms('rider_reminder', $target['phone'], ['message' => $message], $target['language']);
    }
    if ($inApp && $texted) return 'Reminder sent to the rider\'s portal and by SMS.';
    if ($texted) return 'Reminder sent by SMS.';
    if ($inApp) return sms_ready() ? 'Reminder sent to the rider\'s portal. No SMS: no mobile number, SMS switched off, or already texted today.' : 'Reminder sent to the rider\'s portal.';
    return 'Nothing could be sent: the rider has no portal account with notifications on and no number to text.';
}
