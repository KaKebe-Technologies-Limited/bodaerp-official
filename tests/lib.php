<?php
// Shared helpers for tests/smoke.php and tests/flows.php (plain PHP + curl, no Composer).
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Run from the command line.'); }
if (!function_exists('curl_init')) { fwrite(STDERR, "The PHP curl extension is required (enable extension=curl in php.ini).\n"); exit(2); }

require_once __DIR__ . '/../packages/backend/config/config.php';   // storage_path(), env()

const TEST_ERROR_PATTERN = '/(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace)/i';

function test_base(array $argv): string {
    $root = dirname(__DIR__);
    return rtrim($argv[1] ?? env('TEST_BASE_URL', 'http://localhost/' . basename($root)), '/');
}

function test_jar(): string { return tempnam(sys_get_temp_dir(), 'bodatest'); }

/** @return array{code:int,redirect:string,body:string,headers:string} */
function http(string $url, string $jar, ?array $post = null, array $headers = [], ?string $rawBody = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers]);
    if ($rawBody !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody); }
    elseif ($post !== null) {
        $hasFile = (bool) array_filter($post, fn($v) => $v instanceof CURLFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $post : http_build_query($post));
    }
    $raw = (string) curl_exec($ch);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $out = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'redirect' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        'headers' => substr($raw, 0, $hsize), 'body' => substr($raw, $hsize)];
    return $out;
}

function csrf(string $html): string { return preg_match('/name="csrf_token" value="([^"]*)"/', $html, $m) ? $m[1] : ''; }

/** Newest email sent to $to: from Mailpit (Docker, MAILPIT_API) or from storage/mail/*.eml (no SMTP). Returns decoded text. */
function latest_mail_to(string $to, int $sinceTs = 0): ?string {
    $api = env('MAILPIT_API');
    if ($api) {
        for ($i = 0; $i < 10; $i++) {
            $redirect = env('MAIL_REDIRECT_TO', '');
            $query = $redirect !== '' && strcasecmp($redirect, $to) !== 0 ? 'subject:"[for ' . $to . ']"' : 'to:' . $to;
            $list = json_decode((string) @file_get_contents(rtrim($api, '/') . '/api/v1/search?query=' . urlencode($query) . '&limit=1'), true);
            $id = $list['messages'][0]['ID'] ?? null;
            $created = isset($list['messages'][0]['Created']) ? strtotime($list['messages'][0]['Created']) : 0;
            if ($id && $created >= $sinceTs - 2) {
                $msg = json_decode((string) @file_get_contents(rtrim($api, '/') . '/api/v1/message/' . $id), true);
                return ($msg['Subject'] ?? '') . "\n" . ($msg['Text'] ?? '') . "\n" . ($msg['HTML'] ?? '');
            }
            usleep(300000);
        }
        return null;
    }
    $files = glob(storage_path('mail') . '/*.eml') ?: [];
    rsort($files);
    foreach ($files as $f) {
        if (filemtime($f) < $sinceTs - 2) break;
        $raw = (string) file_get_contents($f);
        if (stripos($raw, "To: <$to>") === false && stripos($raw, "X-BodaERP-Original-To: <$to>") === false) continue;
        $decoded = preg_replace_callback('/Content-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+\/=\r\n]+)/', fn($m) => base64_decode(str_replace(["\r", "\n"], '', $m[1])), $raw);
        $subject = preg_match('/^Subject: (.*)$/m', $raw, $s) ? (str_starts_with($s[1], '=?UTF-8?B?') ? base64_decode(substr($s[1], 10, -2)) : $s[1]) : '';
        return $subject . "\n" . $decoded;
    }
    return null;
}

/** Signs in (completing two-step sign-in from the emailed code when it is on). Returns the jar or null. */
function test_login(string $base, string $email, string $password, ?string &$why = null): ?string {
    // The suites sign in dozens of times from one address; clear that address's sign-in throttle (30 per 15 min)
    // so back-to-back runs are not refused. Real visitors keep the limit.
    if (!function_exists('runQuery')) require_once __DIR__ . '/../packages/backend/db_helpers.php';
    runQuery("DELETE FROM rate_limits WHERE bucket LIKE 'login-ip:%'");
    $jar = test_jar();
    $page = http("$base/login.php", $jar);
    $t0 = time();
    $r = http("$base/login.php", $jar, ['csrf_token' => csrf($page['body']), 'email' => $email, 'password' => $password]);
    if ($r['code'] !== 302) { $why = 'no redirect after sign-in (HTTP ' . $r['code'] . ')'; return null; }
    if (str_contains($r['redirect'], 'login-code.php')) {
        $mail = latest_mail_to($email, $t0);
        if (!$mail || !preg_match('/sign-in code: (\d{6})/', $mail, $m)) { $why = 'two-step sign-in code email not found'; return null; }
        $codePage = http("$base/login-code.php", $jar);
        $r = http("$base/login-code.php", $jar, ['csrf_token' => csrf($codePage['body']), 'code' => $m[1]]);
    }
    if ($r['code'] !== 302 || str_contains($r['redirect'], 'login')) { $why = 'redirected back to sign-in'; return null; }
    return $jar;
}

final class TestRun {
    public function __construct() {
        // Suites run back to back from one address: start each with a clear sign-in throttle (see test_login()).
        if (!function_exists('runQuery')) require_once __DIR__ . '/../packages/backend/db_helpers.php';
        runQuery("DELETE FROM rate_limits WHERE bucket LIKE 'login-ip:%'");
    }

    public int $pass = 0;
    public int $fail = 0;
    public array $problems = [];

    public function ok(bool $condition, string $label, string $detail = ''): void {
        if ($condition) { $this->pass++; return; }
        $this->fail++;
        $this->problems[] = sprintf('%-52s %s', $label, $detail);
    }

    /** Page opened with HTTP 200 and no PHP error text. */
    public function page(string $label, array $r): void {
        $bad = $r['code'] !== 200 || preg_match(TEST_ERROR_PATTERN, $r['body'], $m);
        $this->ok(!$bad, $label, 'HTTP ' . $r['code'] . ($r['redirect'] ? ' -> ' . $r['redirect'] : '') . (isset($m[1]) ? ' ' . $m[1] : ''));
    }

    public function finish(): never {
        echo "\n";
        foreach ($this->problems as $p) echo "  FAIL  $p\n";
        printf("%d checks passed, %d failed\n", $this->pass, $this->fail);
        exit($this->fail ? 1 : 0);
    }
}
