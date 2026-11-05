<?php
// BodaERP - email send_template_email('payment_receipt', $to, [...]) renders a DB template (Super Admin →
// Emails) inside the branded _layout template and sends it. Transport: SMTP when SMTP_HOST is set in the
// environment / .env (Hostinger: smtp.hostinger.com, port 465, SMTP_SECURE=ssl). Without SMTP (a stock
// XAMPP install) every email is written to storage/mail/ as an .eml file and listed in Super Admin → Emails
// → Sent log, so password reset, receipts and the contact form can all be tested without a mail server. No
// Composer: a small built-in SMTP client (STARTTLS / SSL, AUTH LOGIN).
require_once __DIR__ . '/db_helpers.php';

function mail_config(): array {
    return [
        'host'   => env('SMTP_HOST', ''),
        'port'   => (int) env('SMTP_PORT', '587'),
        'user'   => env('SMTP_USER', ''),
        'pass'   => env('SMTP_PASS', ''),
        'secure' => strtolower(env('SMTP_SECURE', 'tls')),          // tls (STARTTLS) | ssl | none
        'from'   => env('MAIL_FROM', env('SMTP_USER', 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME'] ?? 'localhost'))),
        'from_name' => get_setting('mail_from_name', get_setting('site_name', 'BodaERP')),
        'timeout' => 12,
    ];
}

function mail_transport_name(): string { return mail_config()['host'] !== '' ? 'smtp' : 'log'; }

function mail_clean_header(string $v): string { return trim(str_replace(["\r", "\n", "\0"], ' ', $v)); }

/** Replaces {{placeholders}}; values are HTML-escaped unless the key ends in _html or is "content". */
function mail_fill(string $tpl, array $vars, bool $html): string {
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $html) {
        $key = $m[1];
        if (!array_key_exists($key, $vars)) return '';
        $val = (string) $vars[$key];
        if (!$html || $key === 'content' || str_ends_with($key, '_html')) return $val;
        return nl2br(htmlspecialchars($val, ENT_QUOTES, 'UTF-8'));
    }, $tpl);
}

function mail_layout_css(): string {
    $brand = get_setting('brand_color_primary', '#056CFE');
    if (!preg_match('/^#[0-9a-f]{3,8}$/i', $brand)) $brand = '#056CFE';
    return "body{margin:0;background:#f1f4f9;font-family:Segoe UI,Arial,sans-serif;color:#0f1b33}"
        . ".wrap{max-width:600px;margin:0 auto;padding:24px 12px}.card{background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e3e8f0}"
        . ".head{padding:16px 24px;border-bottom:3px solid $brand;font-weight:700;font-size:18px}.head img{vertical-align:middle;border-radius:6px}"
        . ".body{padding:24px;font-size:15px;line-height:1.6}.button{display:inline-block;background:$brand;color:#fff !important;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600}"
        . "table.details{border-collapse:collapse;width:100%;margin:12px 0}table.details td{padding:8px;border-bottom:1px solid #eee}"
        . "blockquote{margin:12px 0;padding:10px 14px;background:#f4f7fb;border-left:3px solid $brand}"
        . ".foot{font-size:12px;color:#5f6b80;text-align:center;line-height:1.5;margin-top:16px}";
}

/** @return array{subject:string,html:string,text:string}|null */
function render_email_template(string $code, array $vars): ?array {
    $tpl = fetchOne("SELECT subject, body_html, is_active FROM email_templates WHERE code = ?", [$code]);
    if (!$tpl || !(int) $tpl['is_active']) return null;
    $vars += [
        'site_name' => get_setting('site_name', 'BodaERP'),
        'operator_name' => get_setting('operator_name', 'Kakebe Technologies Limited'),
        'support_email' => get_setting('contact_email', get_setting('support_email', '')),
    ];
    $subject = mail_clean_header(html_entity_decode(strip_tags(mail_fill($tpl['subject'], $vars, false)), ENT_QUOTES, 'UTF-8'));
    $content = mail_fill($tpl['body_html'], $vars, true);

    $layout = fetchOne("SELECT body_html FROM email_templates WHERE code = '_layout' AND is_active = 1");
    $frame = $layout['body_html'] ?? '{{content}}';
    $html = mail_fill($frame, $vars + [
        'content' => $content, 'subject' => $subject,
        'logo_url' => app_url(ltrim(get_setting('logo_path', '/assets/images/logo-mark.png'), '/')),
        'footer_text' => get_setting('mail_footer_text', ''),
    ], true);
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($subject) . '</title><style>' . mail_layout_css() . '</style></head><body>' . $html . '</body></html>';
    return ['subject' => $subject, 'html' => $html, 'text' => mail_html_to_text($content)];
}

function mail_html_to_text(string $html): string {
    $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
    $html = preg_replace('#<(br|/p|/h\d|/li|/tr|/blockquote)[^>]*>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
}

/** Sends a template email. Never throws: failures are logged and reported by the return value. */
function send_template_email(string $code, string $to, array $vars = []): bool {
    try {
        $msg = render_email_template($code, $vars);
        if ($msg === null) { app_log('mail', "template $code missing or disabled", ['to' => $to]); return false; }
        return send_email($to, $msg['subject'], $msg['html'], $msg['text'], $code);
    } catch (Throwable $e) {
        app_log('mail', "template $code failed: " . $e->getMessage(), ['to' => $to]);
        return false;
    }
}

/** @param list<array{name:string,path:string,type?:string}> $attachments files attached as-is (backups, exports) */
function send_email(string $to, string $subject, string $html, string $text = '', ?string $templateCode = null, ?string $replyTo = null, array $attachments = []): bool {
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $cfg = mail_config();
    $subject = mail_clean_header($subject);
    $mime = mail_build_message($cfg, $to, $subject, $html, $text !== '' ? $text : mail_html_to_text($html), $replyTo, $attachments);

    $status = 'logged'; $error = null;
    if ($cfg['host'] !== '') {
        try { smtp_send($cfg, $to, $mime); $status = 'sent'; }
        catch (Throwable $e) { $status = 'failed'; $error = substr($e->getMessage(), 0, 500); app_log('mail', 'SMTP failed: ' . $e->getMessage(), ['to' => $to]); }
    }
    if ($status !== 'sent' && !$attachments) {
        $dir = storage_path('mail');
        if (ensure_dir($dir)) @file_put_contents($dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml', $mime);
    }
    try {
        runQuery("INSERT INTO email_log (to_email, subject, template_code, transport, status, error) VALUES (?,?,?,?,?,?)",
            [$to, mb_substr($subject, 0, 200), $templateCode, $cfg['host'] !== '' ? 'smtp' : 'log', $status, $error]);
    } catch (Throwable $e) { app_log('mail', 'email_log insert failed: ' . $e->getMessage()); }
    return $status !== 'failed';
}

function mail_encode_header(string $v): string {
    return preg_match('/[^\x20-\x7e]/', $v) ? '=?UTF-8?B?' . base64_encode($v) . '?=' : $v;
}

function mail_build_message(array $cfg, string $to, string $subject, string $html, string $text, ?string $replyTo, array $attachments = []): string {
    $boundary = 'b' . bin2hex(random_bytes(12));
    $mixed = 'm' . bin2hex(random_bytes(12));
    $domain = substr(strrchr($cfg['from'], '@') ?: '@localhost', 1);
    $headers = [
        'Date: ' . date('r'),
        'From: ' . mail_encode_header(mail_clean_header($cfg['from_name'])) . ' <' . mail_clean_header($cfg['from']) . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        $attachments ? 'Content-Type: multipart/mixed; boundary="' . $mixed . '"' : 'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: <' . mail_clean_header($replyTo) . '>';
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "--$boundary--\r\n";
    if ($attachments) {
        $parts = "--$mixed\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $body;
        foreach ($attachments as $file) {
            $fileBytes = @file_get_contents($file['path']);
            if ($fileBytes === false) throw new RuntimeException('Cannot read attachment ' . basename($file['path']));
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
            $parts .= "--$mixed\r\nContent-Type: " . ($file['type'] ?? 'application/octet-stream') . "; name=\"$name\"\r\n"
                . "Content-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($fileBytes));
        }
        $body = $parts . "--$mixed--\r\n";
    }
    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

function smtp_send(array $cfg, string $to, string $mime): void {
    $remote = ($cfg['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . $cfg['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $cfg['host']]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $cfg['timeout'], STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("cannot connect to $remote ($errstr)");
    stream_set_timeout($fp, $cfg['timeout']);

    $read = function () use ($fp): array {
        $response = '';
        while (($line = fgets($fp, 1024)) !== false) { $response .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        return [(int) substr($response, 0, 3), $response];
    };
    $cmd = function (string $line, array $expect) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        [$code, $resp] = $read();
        if (!in_array($code, $expect, true)) throw new RuntimeException('SMTP ' . strtok($line, ' ') . " rejected: " . trim($resp));
        return $resp;
    };

    try {
        [$code, $greet] = $read();
        if ($code !== 220) throw new RuntimeException('SMTP greeting: ' . trim($greet));
        $helo = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? 'localhost') ?: 'localhost';
        $ehlo = $cmd("EHLO $helo", [250]);
        if ($cfg['secure'] === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) throw new RuntimeException('server does not offer STARTTLS (set SMTP_SECURE=ssl or none)');
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed');
            }
            $cmd("EHLO $helo", [250]);
        }
        if ($cfg['user'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($cfg['user']), [334]);
            $cmd(base64_encode($cfg['pass']), [235]);
        }
        $cmd('MAIL FROM:<' . $cfg['from'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $payload = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $mime));
        $cmd($payload . "\r\n.", [250]);
        $cmd('QUIT', [221]);
    } finally {
        fclose($fp);
    }
}
