<?php
// ============================================================
// BodaERP — smoke test (command line only)
//
//   php tests/smoke.php                          uses http://localhost/<this folder name>
//   php tests/smoke.php http://localhost/boda    or give the address of the site explicitly
//
// Signs in as each demo role (the accounts created by `php database/seed_run.php`),
// opens every page of that role and fails on a PHP error, a server error or an
// unexpected redirect. Run it after setting up, and after every change.
// Never run it against a live server: it signs in with the demo passwords.
// ============================================================
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Run from the command line: php tests/smoke.php'); }
if (!function_exists('curl_init')) { fwrite(STDERR, "The PHP curl extension is required (enable extension=curl in php.ini).\n"); exit(2); }

$root = dirname(__DIR__);
$base = rtrim($argv[1] ?? ('http://localhost/' . basename($root)), '/');

$roles = [
    'superadmin' => ['admin@bodaerp.com',            'admin123'],
    'government' => ['works@gov.bodaerp.com',        'Gov@2026'],
    'citycouncil' => ['council@liracityuganda.go.ug', 'lira2025'],
    'chairperson' => ['ocen@railway.lira.ug',         'chair123'],
    'rider'      => ['akello@bodaerp.com',           'rider123'],
];
$skip = ['db-backup.php'];                                 // a file download, not a page
$needsId = ['rider-profile.php', 'edit-rider.php', 'id-preview.php'];
$errorPattern = '/(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace)/i';

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = (string) curl_exec($ch);
    $out = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'redirect' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL), 'body' => $body];
    curl_close($ch);
    return $out;
}
function csrf(string $html): string { return preg_match('/name="csrf_token" value="([^"]*)"/', $html, $m) ? $m[1] : ''; }

$pass = 0; $fail = 0; $problems = [];
$check = function (string $label, array $r, bool $expectOk = true) use (&$pass, &$fail, &$problems, $errorPattern) {
    $bad = $expectOk && ($r['code'] !== 200 || preg_match($errorPattern, $r['body']));
    if ($bad) { $fail++; $problems[] = sprintf('%-46s HTTP %d %s', $label, $r['code'], $r['redirect'] ? '→ ' . $r['redirect'] : (preg_match($errorPattern, $r['body'], $m) ? $m[1] : '')); }
    else $pass++;
};

echo "Testing $base\n\n";

// public pages
$pub = tempnam(sys_get_temp_dir(), 'smk');
foreach (['login.php', 'index.php', 'forgot-password.php'] as $p) $check("public/$p", http("$base/$p", $pub));
$check('assets/css/style.css', http("$base/assets/css/style.css", $pub));
$check('assets/js/main.js', http("$base/assets/js/main.js", $pub));
foreach (['.env', 'database/schema.snapshot.json'] as $secret) {
    $r = http("$base/$secret", $pub);
    if ($r['code'] === 200) { $fail++; $problems[] = "$secret is downloadable (HTTP 200) — .htaccess is not active (enable mod_rewrite / AllowOverride All)"; } else $pass++;
}
@unlink($pub);

foreach ($roles as $dir => [$email, $password]) {
    $jar = tempnam(sys_get_temp_dir(), 'smk');
    $login = http("$base/login.php", $jar);
    $r = http("$base/login.php", $jar, ['csrf_token' => csrf($login['body']), 'email' => $email, 'password' => $password]);
    if ($r['code'] !== 302 || str_contains($r['redirect'], 'login.php')) {
        $fail++; $problems[] = sprintf('%-46s sign-in failed for %s (run: php database/seed_run.php)', "$dir/login", $email);
        @unlink($jar); continue;
    }
    $count = 0; $riderId = null;
    $files = glob("$root/packages/frontend/pages/$dir/*.php");
    sort($files);
    foreach ($files as $f) {
        $name = basename($f);
        if (in_array($name, $skip, true)) continue;
        $url = "$base/pages/$dir/$name";
        if (in_array($name, $needsId, true)) {
            if ($riderId === null) {
                $list = http("$base/pages/chairperson/my-riders.php", $jar);
                $riderId = preg_match('/rider-profile\.php\?id=(\d+)/', $list['body'], $m) ? $m[1] : '';
            }
            if ($riderId === '') continue;
            $url .= "?id=$riderId";
        }
        $check("$dir/$name", http($url, $jar)); $count++;
    }
    printf("  %-12s signed in as %-32s %d pages opened\n", $dir, $email, $count);
    @unlink($jar);
}

echo "\n";
foreach ($problems as $p) echo "  FAIL  $p\n";
printf("%d checks passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
