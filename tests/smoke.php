<?php
// BodaERP - smoke test (command line only)
// php tests/smoke.php
// uses http://localhost/<this folder name> php tests/smoke.php http://localhost/boda
// or give the address of the site explicitly (Docker: docker compose exec app php tests/smoke.php)
// Opens every public page, checks secrets and backend files are blocked, then signs in as each demo role
// (accounts from `php database/seed_run.php`) and opens every page of that role. Fails on a PHP error, a
// server error or an unexpected redirect. Never run it against a live server.
require __DIR__ . '/lib.php';

$root = dirname(__DIR__);
$base = test_base($argv);
$t = new TestRun();

$roles = [
    'superadmin'  => ['admin@bodaerp.site',            'admin123'],
    'government'  => ['works@bodaerp.site',        'Gov@2026'],
    'citycouncil' => ['lira@bodaerp.site', 'lira2025'],
    'chairperson' => ['ocen@bodaerp.site',         'chair123'],
    'rider'       => ['akello@bodaerp.site',           'rider123'],
];
$skip = ['db-backup.php'];                                 // a file download, not a page
$needsId = ['rider-profile.php', 'edit-rider.php', 'id-preview.php', 'id-card.php'];

echo "Testing $base\n\n";

$pub = test_jar();
foreach (['', 'index.php', 'login.php', 'forgot-password.php', 'faq.php', 'contact.php', 'verify.php', 'verify.php?code=BODA-LIR-000001',
          'p/about', 'p/privacy-policy', 'p/terms-of-service', 'p/cookie-policy', 'p/photo-credits', 'reset-password.php?token=x', 'verify-email.php'] as $p) {
    $t->page('public/' . ($p ?: '(home)'), http("$base/$p", $pub));
}
foreach (['assets/css/style.css', 'assets/css/public.css', 'assets/css/controls.css', 'assets/js/main.js', 'assets/js/public.js', 'assets/js/controls.js', 'robots.txt', 'sitemap.xml'] as $a) {
    $t->page($a, http("$base/$a", $pub));
}
// Home page hero: every slide comes from the database with a photo that exists and loads.
$home = http("$base/", $pub)['body'];
preg_match_all('/<img class="hero-photo" src="([^"]+)"/', $home, $slidePhotos);
$slideCount = (int) fetchValue("SELECT COUNT(*) FROM content_items WHERE group_code = 'home_slide' AND is_active = 1 AND image IS NOT NULL AND image <> ''");
$t->ok(count($slidePhotos[1]) === $slideCount && $slideCount > 0, 'home page shows one hero slide per active slide in Pages & content', count($slidePhotos[1]) . " shown, $slideCount in the database");
foreach ($slidePhotos[1] as $src) {
    $img = http(preg_replace('#^(https?://[^/]+).*#', '$1', $base) . $src, $pub);
    $t->ok($img['code'] === 200 && strlen($img['body']) > 1000, "hero photo loads: $src", 'HTTP ' . $img['code']);
}
$t->ok(str_contains($home, 'data-hero-next') === ($slideCount > 1) && !str_contains($home, 'data-hero-pause') && !str_contains($home, 'hero-eyebrow'), 'hero slider has previous/next arrows when there is more than one slide, and no badge or pause button (owner, 2026-11-06)');
$t->ok(!preg_match('/Built in Lira|runs BodaERP from|Who builds BodaERP/i', $home . http("$base/p/about", $pub)['body']), 'public pages do not say where the system is built (owner, 2026-11-06)');
$t->ok(!preg_match('/<(details|datalist|progress|meter)\b/i', $home . http("$base/faq.php", $pub)['body']), 'public pages use no browser-drawn details, datalist, progress or meter elements');
$r = http("$base/this-page-does-not-exist", $pub);
$t->ok($r['code'] === 404 && str_contains($r['body'], 'could not find'), 'unknown URL shows the branded 404', 'HTTP ' . $r['code']);
foreach (['.env', '.env.example', 'database/schema.snapshot.json', 'database/bodaerp_full.sql', 'packages/backend/auth.php',
          'packages/backend/database/database.php', 'packages/frontend/partials/topheader.php', 'tests/smoke.php', 'storage/logs/mail.log',
          'docker-compose.yml', 'CLAUDE.md', 'README.md', '.git/config', 'ipn_logs.txt'] as $secret) {
    $r = http("$base/$secret", $pub);
    $t->ok($r['code'] === 403 || $r['code'] === 404, "$secret is not downloadable", 'HTTP ' . $r['code'] . ' (.htaccess not active? enable mod_rewrite / AllowOverride All)');
}
$h = http("$base/login.php", $pub)['headers'];
foreach (['X-Content-Type-Options', 'X-Frame-Options', 'Content-Security-Policy', 'Referrer-Policy'] as $hdr) {
    $t->ok(stripos($h, $hdr . ':') !== false, "security header $hdr");
}
@unlink($pub);

foreach ($roles as $dir => [$email, $password]) {
    $why = '';
    $jar = test_login($base, $email, $password, $why);
    if (!$jar) { $t->ok(false, "$dir/login", "sign-in failed for $email: $why (run: php database/seed_run.php)"); continue; }
    $count = 0; $riderId = null;
    $files = glob("$root/packages/frontend/pages/$dir/*.php");
    sort($files);
    foreach ($files as $f) {
        $name = basename($f);
        if (in_array($name, $skip, true)) continue;
        $url = "$base/pages/$dir/$name";
        if ($name === 'receipt.php') {
            $listPage = ['rider' => 'rider/payment-history.php', 'chairperson' => 'chairperson/collect-payments.php', 'citycouncil' => 'citycouncil/payment-transactions.php'][$dir] ?? null;
            $payId = $listPage && preg_match('/receipt\.php\?id=(\d+)/', http("$base/pages/$listPage", $jar)['body'], $pm) ? $pm[1] : '';
            if ($payId === '') continue;
            $t->page("$dir/$name", http("$url?id=$payId", $jar)); $count++;
            continue;
        }
        if (in_array($name, $needsId, true)) {
            if ($riderId === null) {
                $list = http($dir === 'citycouncil' ? "$base/pages/citycouncil/city-riders.php" : "$base/pages/chairperson/my-riders.php", $jar);
                $riderId = preg_match('/(?:rider-profile|id-card)\.php\?id=(\d+)/', $list['body'], $m) ? $m[1] : '';
            }
            if ($riderId === '') continue;
            $url .= "?id=$riderId";
        }
        $t->page("$dir/$name", http($url, $jar)); $count++;
    }
    $t->page("$dir/public home while signed in", http("$base/", $jar));
    $t->page("$dir/account/settings.php", http("$base/pages/account/settings.php", $jar)); $count++;
    if ($dir === 'citycouncil') {
        foreach (['cash-handovers.php?status=confirmed', 'cash-handovers.php?status=queried', 'permit-checks.php?days=365&outcome=warned'] as $extra) {
            $t->page("$dir/$extra", http("$base/pages/citycouncil/$extra", $jar)); $count++;
        }
    }
    if ($dir === 'superadmin') {
        foreach (['content.php?tab=menus', 'content.php?tab=home_feature', 'content.php?tab=home_step', 'content.php?tab=home_role', 'content.php?tab=faq', 'content-edit.php', 'content-edit.php?id=1',
                  'site-settings.php?cat=home', 'site-settings.php?cat=branding', 'site-settings.php?cat=features', 'email-templates.php?tab=log',
                  'email-templates.php?id=1', 'inbox.php?status=archived', 'data-requests.php?status=completed', 'media.php?type=image', 'sms.php?tab=log', 'languages.php?lang=ach'] as $extra) {
            $t->page("$dir/$extra", http("$base/pages/superadmin/$extra", $jar)); $count++;
        }
    }
    if ($dir === 'government') {
        $body = http("$base/pages/government/registry.php", $jar)['body'];
        $leaked = array_filter(['"nin"', '"phone"', 'verify_token', 'next_of_kin', 'physical_address', 'date_of_birth'], fn($k) => str_contains($body, $k));
        $t->ok(!$leaked, 'government registry holds no NIN, phone, birth date, address, next of kin or QR token', 'found: ' . implode(', ', $leaked));
        $flags = http("$base/pages/government/data-flags.php", $jar)['body'];
        $t->ok(!preg_match('/\+?256\d{6,}|\b\d{4}-\d{2}-\d{2}\b(?=\))/', strip_tags($flags)), 'government data checks hide phone numbers and birth dates');
        $r = http("$base/pages/citycouncil/dashboard.php", $jar);
        $t->ok($r['code'] === 403, 'government is refused the city council portal (403)', 'HTTP ' . $r['code']);
    }
    if ($dir === 'rider') {
        $r = http("$base/pages/superadmin/dashboard.php", $jar);
        $t->ok($r['code'] === 403, 'rider is refused the Super Admin dashboard (403)', 'HTTP ' . $r['code']);
    }
    printf("  %-12s signed in as %-32s %d pages opened\n", $dir, $email, $count);
    @unlink($jar);
}

$t->finish();
