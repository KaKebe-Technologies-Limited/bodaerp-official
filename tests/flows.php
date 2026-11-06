<?php
// BodaERP - critical-flow tests (command line only, never against a live server)
// php tests/flows.php [http://localhost/<folder>]
// (Docker: docker compose exec app php tests/flows.php)
// Creates its own throw-away accounts and records (all named flowtest-*), drives the site over HTTP like a
// browser, checks the database and the emails, then removes what it created. Needs the demo data (php
// database/seed_run.php).
require __DIR__ . '/lib.php';
require_once __DIR__ . '/../packages/backend/db_helpers.php';
require_once __DIR__ . '/../packages/backend/security.php';

$base = test_base($argv);
$t = new TestRun();
$tag = 'flowtest-' . bin2hex(random_bytes(3));
$created = ['users' => [], 'riders' => [], 'contact' => []];
echo "Testing critical flows on $base ($tag)\n\n";

function make_user(string $role, string $password, array $extra = []): array {
    global $tag, $created;
    $email = $tag . '-' . $role . '-' . count($created['users']) . '@bodaerp.site';
    runQuery("INSERT INTO users (name, email, password_hash, role, city_id, stage_id, status, email_verified_at) VALUES (?,?,?,?,?,?,'active',NOW())",
        [ucfirst($role) . ' ' . $tag, $email, password_hash($password, PASSWORD_DEFAULT), $role, $extra['city_id'] ?? null, $extra['stage_id'] ?? null]);
    $id = (int) db()->insert_id;
    $created['users'][] = $id;
    return ['id' => $id, 'email' => $email, 'password' => $password];
}
function post_form(string $url, string $jar, array $fields): array {
    $page = http($url, $jar);
    return http($url, $jar, $fields + ['csrf_token' => csrf($page['body'])]);
}

// 1. Lockout after repeated failures, and the correct password is refused while locked
$u = make_user('government', 'Corr3ct-horse-77');
$jar = test_jar();
for ($i = 0; $i < 5; $i++) post_form("$base/login.php", $jar, ['email' => $u['email'], 'password' => 'wrong-' . $i]);
$r = post_form("$base/login.php", $jar, ['email' => $u['email'], 'password' => $u['password']]);
$t->ok($r['code'] === 200 && str_contains($r['body'], 'paused'), 'account locks after 5 failed sign-ins', 'HTTP ' . $r['code']);
$t->ok((int) fetchValue("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0", [$u['email']]) >= 5, 'failed sign-ins are recorded');
runQuery("DELETE FROM login_attempts WHERE email = ?", [$u['email']]);
$why = '';
$t->ok((bool) test_login($base, $u['email'], $u['password'], $why), 'sign-in works again once the lockout clears', $why);

// 2. Forgot password → email → weak password refused → reset → old link dead → sign in with the new password
$u = make_user('rider', 'Start-pass-123');
$jar = test_jar();
$t0 = time();
$r = post_form("$base/forgot-password.php", $jar, ['email' => $u['email']]);
$t->ok($r['code'] === 200 && str_contains($r['body'], 'on its way'), 'reset request gives the neutral confirmation');
$mail = latest_mail_to($u['email'], $t0);
$link = $mail && preg_match('#reset-password\.php\?token=([a-f0-9]{24}\.[a-f0-9]{64})#', $mail, $m) ? $m[1] : null;
$t->ok((bool) $link, 'reset email arrives with a link', $mail ? 'no link in email' : 'no email found');
$r = post_form("$base/forgot-password.php", test_jar(), ['email' => 'nobody-' . $tag . '@bodaerp.site']);
$t->ok(str_contains($r['body'], 'on its way'), 'unknown email gets the same answer (no account discovery)');
if ($link) {
    $r = post_form("$base/reset-password.php?token=$link", $jar, ['token' => $link, 'password' => 'short', 'password_confirm' => 'short']);
    $t->ok(str_contains($r['body'], 'at least'), 'weak new password is refused');
    $r = post_form("$base/reset-password.php?token=$link", $jar, ['token' => $link, 'password' => 'New-Secure-pass-42', 'password_confirm' => 'New-Secure-pass-42']);
    $t->ok($r['code'] === 302 && str_contains($r['redirect'], 'reset=1'), 'reset with a strong password succeeds', 'HTTP ' . $r['code']);
    $r = http("$base/reset-password.php?token=$link", test_jar());
    $t->ok(str_contains($r['body'], 'expired'), 'a used reset link cannot be used again');
    $t->ok((bool) test_login($base, $u['email'], 'New-Secure-pass-42', $why), 'sign-in with the new password works', $why);
    $t->ok(!test_login($base, $u['email'], 'Start-pass-123', $why), 'the old password no longer works');
}
$t->ok((int) fetchValue("SELECT COUNT(*) FROM user_tokens WHERE user_id = ? AND token_hash IS NOT NULL AND LENGTH(token_hash) = 64", [$u['id']]) >= 0, 'reset tokens are stored hashed');

// 3. Keep me signed in, and password change signs other devices out
$st = fetchOne("SELECT id, city_id FROM stages ORDER BY id LIMIT 1");
$u = make_user('chairperson', 'Remember-me-55', ['city_id' => $st['city_id'], 'stage_id' => $st['id']]);   // a role without required two-step sign-in
$a = test_jar();
$r = post_form("$base/login.php", $a, ['email' => $u['email'], 'password' => $u['password'], 'remember' => '1']);
$cookies = (string) file_get_contents($a);
$t->ok(str_contains($cookies, 'bodaerp_remember'), '"Keep me signed in" sets the remember cookie');
$remember = preg_match('/bodaerp_remember\t(\S+)/', $cookies, $m) ? $m[1] : '';
$fresh = test_jar();
$r = http("$base/pages/chairperson/dashboard.php", $fresh, null, ['Cookie: bodaerp_remember=' . $remember]);
$t->ok($r['code'] === 200, 'remember cookie signs the user back in with no session', 'HTTP ' . $r['code'] . ' ' . $r['redirect']);
$b = test_login($base, $u['email'], $u['password'], $why);
$r = post_form("$base/pages/account/settings.php", $a, ['action' => 'password', 'current_password' => $u['password'], 'new_password' => 'Changed-pass-66', 'confirm_password' => 'Changed-pass-66', 'username' => $u['email']]);
$t->ok($r['code'] === 302, 'own password change is accepted');
$r = http("$base/pages/chairperson/dashboard.php", $b);
$t->ok($r['code'] === 302 && str_contains($r['redirect'], 'login'), 'other signed-in device is signed out after a password change', 'HTTP ' . $r['code']);
$r = http("$base/pages/chairperson/dashboard.php", $a);
$t->ok($r['code'] === 200, 'the device that changed the password stays signed in', 'HTTP ' . $r['code']);
for ($i = 0; $i < 5; $i++) post_form("$base/pages/account/settings.php", $a, ['action' => 'password', 'current_password' => 'guess-' . $i, 'new_password' => 'Guessed-pass-88', 'confirm_password' => 'Guessed-pass-88']);
post_form("$base/pages/account/settings.php", $a, ['action' => 'password', 'current_password' => 'Changed-pass-66', 'new_password' => 'Blocked-pass-77', 'confirm_password' => 'Blocked-pass-77']);
$t->ok(password_verify('Changed-pass-66', (string) fetchValue("SELECT password_hash FROM users WHERE id = ?", [$u['id']])), 'password change stops after repeated wrong current passwords, even with the right one');

// 4. Contact form: saved, emailed to the team and the sender; spam trap; CSRF
$jar = test_jar();
$page = http("$base/contact.php", $jar);
sleep(3);
$email = $tag . '-contact@bodaerp.site';
$t0 = time();
$r = http("$base/contact.php", $jar, ['csrf_token' => csrf($page['body']), 'name' => 'Flow Tester', 'email' => $email, 'phone' => '+256 772 000111',
    'organisation' => 'Test Council', 'subject' => "Subject $tag", 'message' => 'We would like to bring BodaERP to our city.', 'website' => '']);
$t->ok($r['code'] === 302 && str_contains($r['redirect'], 'sent=1'), 'contact form submits', 'HTTP ' . $r['code']);
$row = fetchOne("SELECT id, status FROM contact_messages WHERE email = ?", [$email]);
$t->ok($row && $row['status'] === 'new', 'contact message is saved to the inbox');
if ($row) $created['contact'][] = (int) $row['id'];
$t->ok((bool) latest_mail_to($email, $t0), 'sender receives the automatic reply');
$page = http("$base/contact.php", $jar);
sleep(3);
http("$base/contact.php", $jar, ['csrf_token' => csrf($page['body']), 'name' => 'Bot', 'email' => "bot-$email", 'subject' => 'Buy now', 'message' => 'Spam spam spam spam', 'website' => 'http://spam.example']);
$t->ok(!fetchValue("SELECT COUNT(*) FROM contact_messages WHERE email = ?", ["bot-$email"]), 'honeypot submission is silently dropped');
$r = http("$base/contact.php", test_jar(), ['name' => 'No token', 'email' => $email, 'subject' => 'x', 'message' => 'no csrf token here']);
$t->ok($r['code'] === 400 && str_contains($r['body'], 'expired'), 'form without a CSRF token is refused (400, expired-form page)', 'HTTP ' . $r['code']);

// 5. Payment callback (IPN) cannot be forged
$pending = fetchOne("SELECT id, status FROM payments WHERE status = 'Pending' LIMIT 1");
$r = http("$base/ipn_handler.php", test_jar(), null, ['Content-Type: application/json', 'X-Iotec-Secret: '], json_encode(['id' => 'x', 'externalId' => (string) ($pending['id'] ?? 1), 'status' => 'Success']));
$t->ok($r['code'] === 403, 'IPN without the shared secret is rejected', 'HTTP ' . $r['code']);
if ($pending) $t->ok(fetchValue("SELECT status FROM payments WHERE id = ?", [$pending['id']]) === 'Pending', 'forged IPN does not confirm the payment');
$t->ok(http("$base/ipn_handler.php", test_jar())['code'] === 405, 'IPN only accepts POST');

// 6. Permissions and object-level access
$chair = test_login($base, 'ocen@bodaerp.site', 'chair123', $why);
$otherStageRider = fetchValue("SELECT r.id FROM riders r JOIN users u ON u.stage_id <> r.stage_id WHERE u.email = 'ocen@bodaerp.site' AND r.deleted_at IS NULL LIMIT 1");
$r = http("$base/pages/chairperson/rider-profile.php?id=$otherStageRider", $chair);
$t->ok($r['code'] === 302 && !str_contains($r['body'], 'Rider profile'), 'chairperson cannot open a rider from another stage');
$r = http("$base/pages/citycouncil/dashboard.php", $chair);
$t->ok($r['code'] === 403, 'chairperson is refused the city council portal (403)', 'HTTP ' . $r['code']);
$rider = test_login($base, 'akello@bodaerp.site', 'rider123', $why);
$r = http("$base/api/payments.php?action=reconcile", $rider, ['csrf_token' => 'x']);
$t->ok(in_array($r['code'], [400, 403], true), 'rider cannot run the admin payment reconcile', 'HTTP ' . $r['code']);
$r = http("$base/api/media-upload.php", $rider, ['upload' => new CURLFile(__FILE__, 'image/png', 'x.png')], ['X-CSRF-Token: ' . csrf(http("$base/pages/account/settings.php", $rider)['body'])]);
$t->ok($r['code'] === 403, 'non-admin cannot upload to the media library', 'HTTP ' . $r['code']);

// 7. Cash payments: fixed fee, no double charge while the permit is valid
$stage = fetchOne("SELECT s.id, s.city_id, c.annual_fee FROM stages s JOIN cities c ON c.id = s.city_id JOIN users u ON u.stage_id = s.id WHERE u.email = 'ocen@bodaerp.site'");
runQuery("INSERT INTO riders (city_id, stage_id, id_number, verify_token, full_name, nin, phone, bike_plate, status, member_since, annual_tax)
          VALUES (?,?,?,?,?,?,?,?,'pending',CURDATE(),?)", [$stage['city_id'], $stage['id'], strtoupper($tag), bin2hex(random_bytes(10)), 'FLOW TEST RIDER', strtoupper(substr($tag . 'XXXXXXXX', 0, 14)), '+256772999888', strtoupper($tag), $stage['annual_fee']]);
$testRider = (int) db()->insert_id;
$created['riders'][] = $testRider;
$r = post_form("$base/pages/chairperson/collect-payments.php", $chair, ['rider_id' => $testRider, 'method' => 'Cash', 'amount' => '1']);
$pay = fetchOne("SELECT amount, status, receipt_number FROM payments WHERE rider_id = ? ORDER BY id DESC LIMIT 1", [$testRider]);
$t->ok($pay && (float) $pay['amount'] === (float) $stage['annual_fee'] && $pay['status'] === 'Confirmed', 'cash payment is recorded at the city fee, not the typed amount', json_encode($pay));
$t->ok($pay && str_starts_with((string) $pay['receipt_number'], 'RCP-'), 'confirmed payment gets a receipt number');
$exp = fetchValue("SELECT expiry_date FROM riders WHERE id = ?", [$testRider]);
$t->ok($exp === date('Y-m-d', strtotime('+1 year')), 'permit runs one year from today for a new rider', (string) $exp);
post_form("$base/pages/chairperson/collect-payments.php", $chair, ['rider_id' => $testRider, 'method' => 'Cash']);
$t->ok((int) fetchValue("SELECT COUNT(*) FROM payments WHERE rider_id = ?", [$testRider]) === 1, 'a second payment is refused while the permit is valid');
$r = http("$base/verify.php?code=" . strtoupper($tag), test_jar());
$t->ok(str_contains($r['body'], 'Permit valid') && !str_contains($r['body'], 'FLOW TEST RIDER') && str_contains($r['body'], 'FLOW T.'), 'permit-number lookup shows status and a masked name only');
$token = fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]);
$r = http("$base/verify.php?t=$token", test_jar());
$t->ok(str_contains($r['body'], 'FLOW TEST RIDER'), 'QR token lookup shows the full name for the officer');

// 8. Uploads: a PHP file disguised as an image is refused
$admin = test_login($base, 'admin@bodaerp.site', 'admin123', $why);
$fakeImage = tempnam(sys_get_temp_dir(), 'up') . '.png';
file_put_contents($fakeImage, "<?php echo 'owned'; ?>");
$r = http("$base/api/media-upload.php", $admin, ['upload' => new CURLFile($fakeImage, 'image/png', 'evil.php.png')], ['X-CSRF-Token: ' . csrf(http("$base/pages/superadmin/media.php", $admin)['body']), 'Accept: application/json']);
$t->ok($r['code'] === 422 && str_contains($r['body'], 'not allowed'), 'PHP code disguised as a PNG is refused', 'HTTP ' . $r['code'] . ' ' . substr($r['body'], 0, 120));
$png = tempnam(sys_get_temp_dir(), 'up') . '.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));
$r = http("$base/api/media-upload.php", $admin, ['upload' => new CURLFile($png, 'image/png', 'pixel.png')], ['X-CSRF-Token: ' . csrf(http("$base/pages/superadmin/media.php", $admin)['body'])]);
$up = json_decode($r['body'], true);
$t->ok($r['code'] === 200 && !empty($up['url']), 'a real PNG uploads to the media library', 'HTTP ' . $r['code']);
if (!empty($up['url'])) {
    $file = http((str_starts_with($up['url'], 'http') ? '' : preg_replace('#(https?://[^/]+).*#', '$1', $base)) . $up['url'], test_jar());
    $t->ok($file['code'] === 200 && stripos($file['headers'], 'Content-Type: image/png') !== false, 'uploaded image is served with its real type');
    require_once __DIR__ . '/../packages/backend/uploads.php';
    delete_upload($up['path']);
    runQuery("DELETE FROM media WHERE id = ?", [(int) $up['id']]);
}
@unlink($fakeImage); @unlink($png);

// 9b. Home hero slides (Super Admin -> Pages & content): a javascript: button link is refused, a normal slide saves and shows
$slidePage = http("$base/pages/superadmin/content.php?tab=home_slide", $admin);
$slideTitle = "Flowtest slide $tag";
http("$base/pages/superadmin/content.php?tab=home_slide", $admin, ['csrf_token' => csrf($slidePage['body']), 'action' => 'save_item', 'group_code' => 'home_slide', 'id' => '0',
    'title' => $slideTitle, 'body' => 'Slide text', 'image' => '/assets/images/home/lira-sunset.webp', 'link_label' => 'Go', 'link_url' => 'javascript:alert(1)', 'sort_order' => '99', 'is_active' => '1']);
$t->ok(!fetchOne("SELECT id FROM content_items WHERE title = ?", [$slideTitle]), 'a hero slide with a javascript: button link is refused');
http("$base/pages/superadmin/content.php?tab=home_slide", $admin, ['csrf_token' => csrf(http("$base/pages/superadmin/content.php?tab=home_slide", $admin)['body']), 'action' => 'save_item', 'group_code' => 'home_slide', 'id' => '0',
    'title' => $slideTitle, 'body' => 'Slide text', 'image' => '/assets/images/home/lira-sunset.webp', 'link_label' => 'Go', 'link_url' => '/faq.php', 'sort_order' => '99', 'is_active' => '1']);
$saved = fetchOne("SELECT id, image, link_url FROM content_items WHERE title = ?", [$slideTitle]);
$t->ok($saved && $saved['link_url'] === '/faq.php' && str_contains(http("$base/", test_jar())['body'], htmlspecialchars($slideTitle)), 'a new hero slide saves with its photo and button and appears on the home page', json_encode($saved));
runQuery("DELETE FROM content_items WHERE title = ?", [$slideTitle]);

// 10. Chairperson registers a rider with photo and email → rider + portal account + invite → rider sets password and signs in
$jpg = tempnam(sys_get_temp_dir(), 'ph') . '.png';
file_put_contents($jpg, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));
$riderEmail = $tag . '-newrider@bodaerp.site';
$plate = 'U' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
$page = http("$base/pages/chairperson/register-rider.php", $chair);
$t0 = time();
$r = http("$base/pages/chairperson/register-rider.php", $chair, ['csrf_token' => csrf($page['body']), 'full_name' => 'Flow Newrider', 'nin' => strtoupper(substr('CM' . bin2hex(random_bytes(7)), 0, 14)),
    'dob' => '1995-05-05', 'gender' => 'Male', 'marital_status' => 'Single', 'phone' => '0772 123 987', 'email' => $riderEmail, 'send_invite' => '1',
    'address' => 'Lira', 'nok_name' => 'Kin', 'nok_phone' => '0772000000', 'bike_plate' => $plate, 'bike_model' => 'Bajaj', 'route' => 'Town',
    'payment_status' => 'Unpaid', 'photo' => new CURLFile($jpg, 'image/png', 'rider.png')]);
$new = fetchOne("SELECT id, user_id, phone, photo_path, verify_token, status FROM riders WHERE email = ?", [$riderEmail]);
$t->ok($r['code'] === 302 && $new, 'chairperson registers a rider', 'HTTP ' . $r['code']);
if ($new) {
    $created['riders'][] = (int) $new['id'];
    if ($new['user_id']) $created['users'][] = (int) $new['user_id'];
    $t->ok($new['phone'] === '+256772123987', 'phone typed as 0772… is stored as +256772…', (string) $new['phone']);
    $t->ok((bool) preg_match('#^/uploads/riders/\d{4}/\d{2}/[a-f0-9]{24}\.png$#', (string) $new['photo_path']), 'rider photo stored with a random name', (string) $new['photo_path']);
    $t->ok(strlen((string) $new['verify_token']) === 20 && $new['status'] === 'pending', 'new rider gets a QR token and starts as pending');
    $t->ok((bool) $new['user_id'], 'rider portal account is created and linked');
    $mail = latest_mail_to($riderEmail, $t0);
    $link = $mail && preg_match('#reset-password\.php\?welcome=1&(?:amp;)?token=([a-f0-9]{24}\.[a-f0-9]{64})#', $mail, $m) ? $m[1] : null;
    $t->ok((bool) $link, 'rider receives the "set your password" invite');
    if ($link) {
        $jar = test_jar();
        $r = post_form("$base/reset-password.php?welcome=1&token=$link", $jar, ['token' => $link, 'welcome' => '1', 'password' => 'Rider-portal-77', 'password_confirm' => 'Rider-portal-77']);
        $riderJar = test_login($base, $riderEmail, 'Rider-portal-77', $why);
        $t->ok((bool) $riderJar, 'invited rider sets a password and signs in', $why);
        if ($riderJar) {
            $t->ok(str_contains(http("$base/pages/rider/dashboard.php", $riderJar)['body'], 'Flow Newrider') || http("$base/pages/rider/dashboard.php", $riderJar)['code'] === 200, 'new rider sees their own dashboard');
            $r = post_form("$base/pages/account/settings.php", $riderJar, ['action' => 'data_request', 'type' => 'correction', 'details' => 'My bike model is Bajaj Boxer, not Bajaj.']);
            $t->ok((bool) fetchValue("SELECT COUNT(*) FROM data_requests WHERE user_id = ? AND type = 'correction'", [$new['user_id']]), 'rider sends a correction request');
            $dl = http("$base/pages/account/settings.php?download=1", $riderJar);
            $json = json_decode($dl['body'], true);
            $t->ok(($json['rider']['full_name'] ?? '') === 'FLOW NEWRIDER', 'rider downloads their data as JSON');
            $reqId = (int) fetchValue("SELECT id FROM data_requests WHERE user_id = ?", [$new['user_id']]);
            post_form("$base/pages/superadmin/data-requests.php", $admin, ['id' => $reqId, 'status' => 'completed', 'response' => 'Corrected to Bajaj Boxer.']);
            $t->ok(fetchValue("SELECT status FROM data_requests WHERE id = ?", [$reqId]) === 'completed', 'Super Admin completes the request');
            $t->ok((bool) fetchValue("SELECT COUNT(*) FROM notifications WHERE rider_id = ? AND type = 'Data Request'", [$new['id']]), 'rider is notified of the answer');
            runQuery("DELETE FROM data_requests WHERE user_id = ?", [$new['user_id']]);
        }
    }
    require_once __DIR__ . '/../packages/backend/uploads.php';
    delete_upload($new['photo_path']);
}
@unlink($jpg);

// 11. Super Admin replies to a contact message, and edits a site setting that appears on the public site
$msgId = $created['contact'][0] ?? 0;
if ($msgId) {
    $t0 = time();
    post_form("$base/pages/superadmin/inbox.php?id=$msgId", $admin, ['action' => 'reply', 'id' => $msgId, 'reply' => '<p>Thanks, we will call you.</p>']);
    $t->ok(fetchValue("SELECT status FROM contact_messages WHERE id = ?", [$msgId]) === 'replied', 'inbox reply marks the message replied');
    $t->ok(str_contains((string) latest_mail_to($tag . '-contact@bodaerp.site', $t0), 'we will call you'), 'reply is emailed to the sender');
}
$old = fetchValue("SELECT value FROM settings WHERE `key` = 'home_hero_title'");
post_form("$base/pages/superadmin/site-settings.php?cat=home", $admin, ['s' => ['home_hero_title' => "Heading $tag"]]);
$t->ok(str_contains(http("$base/", test_jar())['body'], "Heading $tag"), 'a setting saved in Super Admin shows on the home page');
runQuery("UPDATE settings SET value = ? WHERE `key` = 'home_hero_title'", [$old]);

// 12. Mobile-money API is reachable from the rider portal, validates input and only reports the rider's own payments
$why = '';
$rj = test_login($base, 'akello@bodaerp.site', 'rider123', $why);
$t->ok((bool) $rj, 'demo rider signs in for the payment API checks', $why);
if ($rj) {
    $hist = http("$base/pages/rider/payment-history.php", $rj);
    $t->ok(str_contains($hist['body'], '/api/payments.php?action=initiate'), 'rider payment page calls the routed /api/payments.php');
    $r = http("$base/api/payments.php?action=initiate", $rj, ['csrf_token' => csrf($hist['body']), 'phone' => '12345']);
    $j = json_decode($r['body'], true);
    $t->ok($r['code'] === 200 && is_array($j) && $j['success'] === false && $j['message'] !== '', 'payment API answers the rider with JSON and refuses a bad number', 'HTTP ' . $r['code'] . ' ' . substr($r['body'], 0, 80));
    $r = http("$base/api/payments.php?action=initiate", $rj, ['phone' => '0772123456']);
    $t->ok($r['code'] === 400 || ($r['code'] === 200 && !str_contains($r['body'], '"success":true')), 'payment API refuses a request without the CSRF token', 'HTTP ' . $r['code']);
    $own = (int) fetchValue("SELECT p.id FROM payments p JOIN riders r ON r.id = p.rider_id JOIN users u ON u.id = r.user_id WHERE u.email = 'akello@bodaerp.site' ORDER BY p.id DESC LIMIT 1");
    if ($own) {
        $r = http("$base/api/payments.php?action=check_status&payment_id=$own", $rj);
        $j = json_decode($r['body'], true);
        $t->ok($r['code'] === 200 && is_array($j) && !empty($j['success']), "rider can poll their own payment's status", substr($r['body'], 0, 100));
    }
    @unlink($rj);
}

// 9. Maintenance mode: visitors see 503, the Super Admin keeps working
runQuery("UPDATE platform_settings SET maintenance_mode = 1 WHERE id = 1");
$r = http("$base/faq.php", test_jar());
$t->ok($r['code'] === 503, 'maintenance mode shows visitors the 503 page', 'HTTP ' . $r['code']);
$t->ok(http("$base/pages/superadmin/dashboard.php", $admin)['code'] === 200, 'Super Admin can still work during maintenance');
$t->ok(http("$base/login.php", test_jar())['code'] === 200, 'sign-in page stays reachable during maintenance');
runQuery("UPDATE platform_settings SET maintenance_mode = 0 WHERE id = 1");

// 13. Scheduled jobs: each notice goes out once, backups restore, housekeeping keeps what matters
require_once __DIR__ . '/../packages/backend/automation.php';
$stageRow = fetchOne("SELECT id, city_id FROM stages WHERE status = 'active' ORDER BY id LIMIT 1");
$make_rider = function (string $suffix, string $expiry) use ($tag, $stageRow, &$created): array {
    $email = "$tag-$suffix@bodaerp.site";
    runQuery("INSERT INTO riders (city_id, stage_id, id_number, full_name, phone, email, bike_plate, member_since, annual_tax, expiry_date, status)
              VALUES (?,?,?,?,?,?,?,CURDATE(),50000,?,'active')",
        [$stageRow['city_id'], $stageRow['id'], substr("$tag-$suffix", 0, 30), "Rider $tag", '+256772000000', $email, substr(strtoupper($suffix . $tag), 0, 20), $expiry]);
    $id = (int) db()->insert_id;
    $created['riders'][] = $id;
    return ['id' => $id, 'email' => $email];
};
$sentTo = fn(string $email, string $template): int => (int) fetchValue("SELECT COUNT(*) FROM email_log WHERE to_email = ? AND template_code = ?", [$email, $template]);

$soon = $make_rider('soon', date('Y-m-d', strtotime('+3 days')));
$quiet = $make_rider('quiet', date('Y-m-d', strtotime('+90 days')));
$quietUser = make_user('rider', 'Quiet-rider-31');
runQuery("UPDATE users SET notify_in_app = 0 WHERE id = ?", [$quietUser['id']]);
runQuery("UPDATE riders SET user_id = ? WHERE id = ?", [$quietUser['id'], $quiet['id']]);
$t->ok(!rider_notify($quiet['id'], 'Important Update', 'hello') && !fetchValue("SELECT COUNT(*) FROM notifications WHERE rider_id = ?", [$quiet['id']]),
    'no portal notification is added for a rider who switched them off');
runQuery("UPDATE users SET notify_in_app = 1 WHERE id = ?", [$quietUser['id']]);
$t->ok(rider_notify($quiet['id'], 'Data Request', str_repeat('Long reply. ', 60)) && mb_strlen((string) fetchValue("SELECT message FROM notifications WHERE rider_id = ?", [$quiet['id']])) <= 255,
    'a long notification is shortened to fit instead of failing');
$lapsed = $make_rider('late', date('Y-m-d', strtotime('-8 days')));
if (automation_email_ready()) {
    $result = automation_run_job('renewal_reminders', 'test');
    $t->ok($result['ok'] && $sentTo($soon['email'], 'permit_expiring') === 1, 'a rider three days from expiry gets a renewal email', $result['message']);
    automation_run_job('renewal_reminders', 'test');
    $t->ok($sentTo($soon['email'], 'permit_expiring') === 1, 'running the reminder job again sends nothing more');
    $result = automation_run_job('overdue_notices', 'test');
    $t->ok($result['ok'] && $sentTo($lapsed['email'], 'permit_overdue') === 1, 'a rider eight days overdue gets an overdue email', $result['message']);
    automation_run_job('overdue_notices', 'test');
    $t->ok($sentTo($lapsed['email'], 'permit_overdue') === 1, 'running the overdue job again sends nothing more');
    $chair = make_user('chairperson', 'Digest-pass-123', ['city_id' => $stageRow['city_id'], 'stage_id' => $stageRow['id']]);
    $result = automation_run_job('digest_chairpersons', 'test');
    $t->ok($result['ok'] && $sentTo($chair['email'], 'digest_chairperson') === 1, 'a chairperson gets the weekly stage summary', $result['message']);
    automation_run_job('digest_chairpersons', 'test');
    $t->ok($sentTo($chair['email'], 'digest_chairperson') === 1, 'the weekly summary goes out once a week, however often the job runs');
    $cityAdmin = make_user('city_admin', 'Digest-pass-123', ['city_id' => $stageRow['city_id']]);
    $result = automation_run_job('digest_city_admins', 'test');
    $t->ok($result['ok'] && $sentTo($cityAdmin['email'], 'digest_city') === 1, 'a city administrator gets the weekly council summary', $result['message']);
}
$state = automation_state();
$t->ok((int) $state['overdue_notices']['enabled'] === 0 || fetchValue("SELECT 1 FROM automation_jobs WHERE job_code = 'overdue_notices' AND last_triggered_by <> 'test'") !== null, 'jobs that contact riders start switched off');
$t->ok((int) $state['backup_database']['enabled'] === 1, 'the backup job starts switched on');

$result = automation_run_job('backup_database', 'test');
$newest = backup_list()[0] ?? null;
$t->ok($result['ok'] && $newest !== null, 'the backup job writes a file', $result['message']);
if ($newest && !$newest['encrypted']) {
    $t->ok(backup_is_complete($newest['path']), 'the backup reads through to its closing marker');
    $sql = implode('', gzfile($newest['path']));
    $t->ok(str_contains($sql, 'CREATE TABLE `payments`') && str_contains($sql, 'INSERT INTO `riders`'), 'the backup holds table structure and rider data');
    unset($sql);
}
$secret = str_repeat('ab', 32);
$plainFile = tempnam(sys_get_temp_dir(), 'bodaplain');
file_put_contents($plainFile, random_bytes(BACKUP_CHUNK_BYTES * 2 + 77));
backup_encrypt_file($plainFile, "$plainFile.enc", hex2bin($secret));
backup_decrypt_file("$plainFile.enc", "$plainFile.out", hex2bin($secret));
$t->ok(hash_file('sha256', $plainFile) === hash_file('sha256', "$plainFile.out"), 'an encrypted backup decrypts back to the same bytes');
$wrongKeyRefused = false;
try { backup_decrypt_file("$plainFile.enc", "$plainFile.bad", hex2bin(str_repeat('cd', 32))); } catch (RuntimeException $e) { $wrongKeyRefused = true; }
$t->ok($wrongKeyRefused, 'the wrong key is refused');
$cutFile = "$plainFile.cut";
file_put_contents($cutFile, substr((string) file_get_contents("$plainFile.enc"), 0, BACKUP_CHUNK_BYTES + 60));
$cutRefused = false;
try { backup_decrypt_file($cutFile, "$plainFile.bad", hex2bin($secret)); } catch (RuntimeException $e) { $cutRefused = true; }
$t->ok($cutRefused, 'a backup cut short is refused instead of restoring partly');
foreach (['', '.enc', '.out', '.bad', '.cut'] as $ext) @unlink($plainFile . $ext);

$oldEmail = "$tag-old@bodaerp.site"; $newEmail = "$tag-new@bodaerp.site";
runQuery("INSERT INTO login_attempts (email, ip_address, success, attempted_at) VALUES (?, '127.0.0.1', 0, DATE_SUB(NOW(), INTERVAL 200 DAY)), (?, '127.0.0.1', 0, NOW())", [$oldEmail, $newEmail]);
$auditBefore = (int) fetchValue("SELECT COUNT(*) FROM audit_logs");
$result = automation_run_job('retention_cleanup', 'test');
$t->ok($result['ok'] && !fetchValue("SELECT COUNT(*) FROM login_attempts WHERE email = ?", [$oldEmail]) && fetchValue("SELECT COUNT(*) FROM login_attempts WHERE email = ?", [$newEmail]), 'housekeeping removes old sign-in attempts and keeps recent ones', $result['message']);
$t->ok((int) fetchValue("SELECT COUNT(*) FROM audit_logs") >= $auditBefore, 'housekeeping leaves the audit log alone');

runQuery("INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?,?,?,?, 'new', DATE_SUB(NOW(), INTERVAL 5 DAY))", ["Old $tag", "$tag-old@bodaerp.site", 'Old', 'Waiting']);
$created['contact'][] = (int) db()->insert_id;
$t->ok(in_array('messages_unread', array_column(automation_health_problems(), 'key'), true), 'the problem check notices a message left unread');

$r = http("$base/health.php", test_jar());
$t->ok($r['code'] === 200 && str_contains($r['body'], '"ok"') && strlen($r['body']) < 40, 'the health check answers 200 and reveals nothing else', 'HTTP ' . $r['code']);
$r = http("$base/cron/run.php?token=wrong", test_jar());
$t->ok($r['code'] === 403, 'the scheduler URL refuses a wrong token', 'HTTP ' . $r['code']);

// 14. A chairperson can record a payment under every method on the form
require_once __DIR__ . '/../packages/backend/permits.php';
$bankRider = $make_rider('bank', date('Y-m-d', strtotime('-40 days')));
$bankRow = fetchOne("SELECT * FROM riders WHERE id = ?", [$bankRider['id']]);
foreach (PAYMENT_METHODS as $method) {
    $methodOk = true;
    try { record_manual_payment($bankRow, $method, null); } catch (Throwable $e) { $methodOk = false; }
    $t->ok($methodOk, "a payment by $method can be recorded");
}

// 15. Round 3: late fees, SMS, cash handover, duplicates, USSD, roadside checks, rider import
$chair = test_login($base, 'ocen@bodaerp.site', 'chair123', $why);   // section 13 reused the name for a test user
require_once __DIR__ . '/../packages/backend/sms.php';
require_once __DIR__ . '/../packages/backend/rider_import.php';
$city = fetchOne("SELECT id, grace_period_days, late_fee, late_fee_type FROM cities WHERE id = ?", [$stage['city_id']]);
runQuery("UPDATE cities SET grace_period_days = 30, late_fee = 10, late_fee_type = 'percent' WHERE id = ?", [$city['id']]);
$lateRider = ['annual_tax' => 50000, 'city_id' => $city['id'], 'expiry_date' => date('Y-m-d', strtotime('-45 days'))];
$graceRider = ['expiry_date' => date('Y-m-d', strtotime('-10 days'))] + $lateRider;
$due = permit_amount_due($lateRider, true);
$t->ok($due['late_fee'] == 5000 && $due['total'] == 55000, 'a permit 45 days expired owes the fee plus a 10% late fee', json_encode($due));
$t->ok(permit_amount_due($graceRider)['late_fee'] == 0, 'no late fee inside the grace period');
runQuery("UPDATE cities SET grace_period_days = ?, late_fee = ?, late_fee_type = ? WHERE id = ?", [$city['grace_period_days'], $city['late_fee'], $city['late_fee_type'], $city['id']]);

$smsBefore = (int) fetchValue("SELECT COUNT(*) FROM sms_log");
$t->ok(send_template_sms('payment_receipt', '0772 000111', ['amount' => 'UGX 1', 'receipt_number' => 'RCP-TEST', 'id_number' => 'X', 'expiry_date' => 'today']), 'a template SMS is accepted');
$smsRow = fetchOne("SELECT * FROM sms_log ORDER BY id DESC LIMIT 1");
$t->ok((int) fetchValue("SELECT COUNT(*) FROM sms_log") === $smsBefore + 1 && $smsRow['to_phone'] === '256772000111' && $smsRow['status'] === (sms_transport_name() === 'egosms' ? 'sent' : 'logged'),
    'every SMS is written to the SMS log with the number in 2567... form', json_encode($smsRow));
$t->ok(!send_sms('12345', 'hello'), 'an SMS to a number that is not a Ugandan mobile is refused');
$t->ok(sms_segments(str_repeat('a', 160)) === 1 && sms_segments(str_repeat('a', 161)) === 2 && sms_segments('ŋ') === 1 && sms_segments(str_repeat('ŋ', 71)) === 2, 'SMS parts are counted for plain and Unicode text');
runQuery("DELETE FROM sms_log WHERE id = ?", [$smsRow['id']]);

// Cash handover: stage cash is listed, handed over, and the city confirms it
$handRider = $make_rider('hand', date('Y-m-d', strtotime('-20 days')));
runQuery("UPDATE riders SET stage_id = ?, city_id = ? WHERE id = ?", [$stage['id'], $stage['city_id'], $handRider['id']]);
post_form("$base/pages/chairperson/collect-payments.php", $chair, ['rider_id' => $handRider['id'], 'method' => 'Cash']);
$handPay = fetchOne("SELECT id, amount, channel, handover_id FROM payments WHERE rider_id = ? ORDER BY id DESC LIMIT 1", [$handRider['id']]);
$t->ok($handPay && $handPay['channel'] === 'stage' && $handPay['handover_id'] === null, 'cash taken at the stage waits for a handover', json_encode($handPay));
$page = http("$base/pages/chairperson/cash-handover.php", $chair);
preg_match('/name="payments_count" value="(\d+)"/', $page['body'], $pc);
$r = http("$base/pages/chairperson/cash-handover.php", $chair, ['csrf_token' => csrf($page['body']), 'payments_count' => $pc[1] ?? '0', 'handed_amount' => '1', 'note' => '']);
$t->ok(!fetchValue("SELECT handover_id FROM payments WHERE id = ?", [$handPay['id']]), 'a handover short of the receipts is refused without a note');
$page = http("$base/pages/chairperson/cash-handover.php", $chair);
$expectedCash = (float) fetchValue("SELECT SUM(p.amount) FROM payments p JOIN riders r ON r.id = p.rider_id WHERE r.stage_id = ? AND p.payment_method = 'Cash' AND p.status = 'Confirmed' AND p.channel = 'stage' AND p.handover_id IS NULL", [$stage['id']]);
http("$base/pages/chairperson/cash-handover.php", $chair, ['csrf_token' => csrf($page['body']), 'payments_count' => $pc[1] ?? '0', 'handed_amount' => (string) $expectedCash, 'note' => '']);
$ho = fetchOne("SELECT h.* FROM cash_handovers h JOIN payments p ON p.handover_id = h.id WHERE p.id = ?", [$handPay['id']]);
$t->ok($ho && $ho['status'] === 'submitted' && (float) $ho['expected_amount'] === $expectedCash, 'the handover bundles the stage cash and waits for the city', json_encode($ho));
$cityJar = test_login($base, 'lira@bodaerp.site', 'lira2025', $why);
if ($ho && $cityJar) {
    $page = http("$base/pages/citycouncil/cash-handovers.php", $cityJar);
    http("$base/pages/citycouncil/cash-handovers.php", $cityJar, ['csrf_token' => csrf($page['body']), 'handover_id' => $ho['id'], 'decision' => 'confirm', 'received_amount' => (string) $expectedCash, 'review_note' => '']);
    $t->ok(fetchValue("SELECT status FROM cash_handovers WHERE id = ?", [$ho['id']]) === 'confirmed', 'the city confirms the handover');
} else {
    $t->ok(false, 'the city admin can sign in to confirm the handover', (string) $why);
}

// Enforcement codes come from the row id, so they never repeat after a deletion
if ($cityJar) {
    $codes = [];
    foreach ([1, 2] as $n) {
        $page = http("$base/pages/citycouncil/enforcement-actions.php", $cityJar);
        http("$base/pages/citycouncil/enforcement-actions.php", $cityJar, ['csrf_token' => csrf($page['body']), 'rider_id' => $handRider['id'], 'type' => 'warning', 'amount' => '', 'description' => "flowtest $tag $n", 'status' => 'pending']);
        $row = fetchOne("SELECT id, action_code FROM enforcement_actions WHERE description = ?", ["flowtest $tag $n"]);
        $codes[] = $row;
        if ($n === 1 && $row) runQuery("DELETE FROM enforcement_actions WHERE id = ?", [$row['id']]);
    }
    $t->ok($codes[0] && $codes[1] && $codes[0]['action_code'] !== $codes[1]['action_code'] && preg_match('/^ENF-\d{4}-\d{3,}$/', $codes[1]['action_code']),
        'a new enforcement action gets a fresh ENF code even right after one was deleted', json_encode($codes));
    $page = http("$base/pages/citycouncil/enforcement-actions.php", $cityJar);
    $bad = http("$base/pages/citycouncil/enforcement-actions.php", $cityJar, ['csrf_token' => csrf($page['body']), 'rider_id' => $handRider['id'], 'type' => 'arrest', 'description' => "flowtest $tag bad", 'status' => 'whatever']);
    $t->ok(!fetchOne("SELECT id FROM enforcement_actions WHERE description = ?", ["flowtest $tag bad"]) && str_contains($bad['body'], 'Choose an action type'),
        'an enforcement action with a type or status outside the list (and no amount field) is refused with a message');
    runQuery("DELETE FROM enforcement_actions WHERE description LIKE ?", ["flowtest $tag%"]);
}

// Duplicate check, live and on submit
$dupJson = json_decode(http("$base/pages/chairperson/check-duplicate.php?field=bike_plate&value=" . urlencode(fetchValue("SELECT bike_plate FROM riders WHERE id = ?", [$handRider['id']])), $chair)['body'], true);
$t->ok(!empty($dupJson['matches']) && !isset($dupJson['matches'][0]['full_name']), 'the live duplicate check finds a registered plate and returns only where it is');
$t->ok(rider_duplicates(['bike_plate' => 'NOPE ' . $tag]) === [], 'an unknown plate is not a duplicate');

// USSD: refused without the token; with it, the menu answers
$r = http("$base/ussd.php", test_jar(), ['sessionId' => 's1', 'phoneNumber' => '+256772000000', 'text' => '']);
$t->ok($r['code'] === 403, 'USSD requests without the provider token are refused', 'HTTP ' . $r['code']);
if (env('USSD_TOKEN', '') !== '') {
    $ussd = fn(string $phone, string $text) => http("$base/ussd.php?token=" . urlencode(env('USSD_TOKEN')), test_jar(), ['sessionId' => "flow-$tag-" . md5($phone . $text), 'phoneNumber' => $phone, 'text' => $text])['body'];
    $t->ok(str_starts_with($ussd('+256701999001', ''), 'CON ') && str_contains($ussd('+256701999001', ''), '1. Renew'), 'USSD opens with the renew / check menu');
    $t->ok(str_starts_with($ussd('+256701999001', '1*BODA-NOPE-0'), 'END No permit'), 'USSD answers a wrong permit number with END');
    $own = fetchOne("SELECT id_number, phone FROM riders WHERE id = ?", [$testRider]);
    $checkReply = $ussd($own['phone'], '2');
    $t->ok(str_contains($checkReply, $own['id_number']) && !str_contains($checkReply, 'FLOW TEST RIDER') && str_contains($checkReply, 'F*** T***'), 'USSD "check my permit" shows the caller\'s permit with a masked name', $checkReply);
    $t->ok(str_starts_with($ussd($own['phone'], '1'), 'CON Permit ' . $own['id_number']), 'a caller with one permit goes straight to its confirmation');
    $t->ok(str_contains($ussd($own['phone'], '1*1'), 'valid until'), 'USSD refuses to charge a permit that is not due yet');
    $t->ok($ussd('+256701999001', '1*' . $own['id_number'] . '*2') === 'END Cancelled. Nothing was charged.', 'cancelling on USSD charges nothing');
    runQuery("DELETE FROM ussd_sessions WHERE session_id LIKE ?", ["flow-$tag-%"]);
    runQuery("DELETE FROM rate_limits WHERE bucket LIKE 'ussd%'");
}

// Roadside check by a signed-in chairperson
$vt = fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]);
$page = http("$base/verify.php?t=$vt", $chair);
$t->ok(str_contains($page['body'], 'Record this check'), 'signed-in staff see "Record this check" on a scanned card');
http("$base/verify.php", $chair, ['csrf_token' => csrf($page['body']), 't' => $vt, 'outcome' => 'warned', 'note' => 'flowtest', 'lat' => '2.2499', 'lng' => '32.8999', 'acc' => '12']);
$check = fetchOne("SELECT * FROM permit_checks WHERE rider_id = ? ORDER BY id DESC LIMIT 1", [$testRider]);
$t->ok($check && $check['outcome'] === 'warned' && (float) $check['latitude'] === 2.2499, 'the check is saved with its outcome and location', json_encode($check));
$t->ok(!str_contains(http("$base/verify.php?t=$vt", test_jar())['body'], 'Record this check'), 'visitors do not see the check form');

// ID cards: reprint keeps the QR code, a lost card stops it, the old QR then says "no longer valid"
$cardUrl = "$base/pages/chairperson/id-preview.php?id=$testRider";
$page = http($cardUrl, $chair);
$t->ok($page['code'] === 200 && str_contains($page['body'], 'Card history'), 'the chairperson card page opens with its history');
$tokenBefore = (string) fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]);
http($cardUrl, $chair, ['csrf_token' => csrf($page['body']), 'card_action' => 'issue', 'reason' => 'reprint', 'note' => 'flowtest']);
$live = fetchAll("SELECT * FROM id_cards WHERE rider_id = ? AND status IN ('issued','printed','collected')", [$testRider]);
$t->ok(count($live) === 1 && fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]) === $tokenBefore && $live[0]['verify_token'] === $tokenBefore,
    'a reprint makes a new card version, keeps one valid card and the same QR code', json_encode($live));
http($cardUrl, $chair, ['csrf_token' => csrf(http($cardUrl, $chair)['body']), 'card_action' => 'collected']);
$t->ok(fetchValue("SELECT status FROM id_cards WHERE rider_id = ? ORDER BY version DESC LIMIT 1", [$testRider]) === 'collected', 'handing the card over marks it collected');
http($cardUrl, $chair, ['csrf_token' => csrf(http($cardUrl, $chair)['body']), 'card_action' => 'lost', 'note' => 'flowtest']);
$tokenAfter = (string) fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]);
$t->ok($tokenAfter !== $tokenBefore && !fetchValue("SELECT COUNT(*) FROM id_cards WHERE rider_id = ? AND status IN ('issued','printed','collected')", [$testRider]),
    'a lost card leaves no valid card and gives the rider a new QR token');
$old = http("$base/verify.php?t=$tokenBefore", test_jar())['body'];
$t->ok(str_contains($old, 'This card is no longer valid') && str_contains($old, 'reported lost') && !str_contains($old, 'FLOW TEST RIDER'), 'scanning the lost card says it is no longer valid, with a masked name');
http($cardUrl, $chair, ['csrf_token' => csrf(http($cardUrl, $chair)['body']), 'card_action' => 'issue', 'reason' => 'lost']);
$t->ok(str_contains(http("$base/verify.php?t=" . fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]), test_jar())['body'], 'Permit'), 'the new card verifies');
$signImg = imagecreatetruecolor(300, 90);
imagefill($signImg, 0, 0, imagecolorallocate($signImg, 255, 255, 255));
imageline($signImg, 12, 70, 288, 18, imagecolorallocate($signImg, 0, 0, 0));
ob_start(); imagepng($signImg); $signPng = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
http($cardUrl, $chair, ['csrf_token' => csrf(http($cardUrl, $chair)['body']), 'card_action' => 'signature', 'signature_drawn' => $signPng]);
$signPath = (string) fetchValue("SELECT signature_path FROM riders WHERE id = ?", [$testRider]);
$cardPage = http($cardUrl, $chair)['body'];
$t->ok($signPath !== "" && str_contains($cardPage, h($signPath) . "\""), 'a signature drawn on the card page is kept with the rider and printed on the card');
$t->ok(http($base . $signPath, $chair)['code'] === 200 && http($base . $signPath, test_jar())['code'] !== 200, 'the signature image is served to staff of the stage, not to visitors');
http($cardUrl, $chair, ['csrf_token' => csrf($cardPage), 'card_action' => 'signature', 'signature_drawn' => 'data:image/png;base64,iVBORw0KGgo=']);
$t->ok((string) fetchValue("SELECT signature_path FROM riders WHERE id = ?", [$testRider]) === $signPath, 'a broken signature image is refused and the old one is kept');
http($cardUrl, $chair, ['csrf_token' => csrf(http($cardUrl, $chair)['body']), 'card_action' => 'signature_remove']);
$t->ok(fetchValue("SELECT signature_path FROM riders WHERE id = ?", [$testRider]) === null && http($base . $signPath, $chair)['code'] !== 200, 'removing the signature clears it and deletes the file');
$t->ok(http("$base/pages/chairperson/id-cards.php?show=to_print", $chair)['code'] === 200,'the card register opens filtered to cards waiting to be printed');
$otherCity = (int) fetchValue("SELECT id FROM riders WHERE city_id <> (SELECT city_id FROM riders WHERE id = ?) AND deleted_at IS NULL LIMIT 1", [$testRider]);
$t->ok(http("$base/pages/chairperson/id-preview.php?id=$otherCity", $chair)['code'] === 302, 'a chairperson cannot open the card of a rider from another stage');

// Enforcement: a pending suspension suspends the permit at the roadside; an upheld appeal lifts it
$riderCity = (string) fetchValue("SELECT city_id FROM riders WHERE id = ?", [$testRider]);
runQuery("INSERT INTO enforcement_actions (action_code, rider_id, city_id, stage_id, plate, type, description, action_date, status, appeal_text, appeal_at, appeal_status)
    VALUES (?, ?, ?, NULL, 'FLOW', 'suspension', 'flowtest suspension', CURDATE(), 'pending', 'flowtest appeal text long enough', NOW(), 'open')", ['EF-' . substr(md5($tag), 0, 10), $testRider, $riderCity]);
$suspId = (int) db()->insert_id;
$vt = (string) fetchValue("SELECT verify_token FROM riders WHERE id = ?", [$testRider]);
$t->ok(str_contains(http("$base/verify.php?t=$vt", test_jar())['body'], 'Permit suspended'), 'a pending suspension shows the permit as suspended on a QR check');
if ($riderCity === (string) fetchValue("SELECT city_id FROM users WHERE email = 'lira@bodaerp.site'") && !empty($cityJar)) {
    $enfPage = http("$base/pages/citycouncil/enforcement-actions.php", $cityJar)['body'];
    $t->ok(str_contains($enfPage, 'waiting for an answer') && str_contains($enfPage, 'flowtest appeal text'), 'the city sees the appeal waiting on its Enforcement page');
    http("$base/pages/citycouncil/enforcement-actions.php", $cityJar, ['csrf_token' => csrf($enfPage), 'action' => 'appeal_answer', 'id' => $suspId, 'decision' => 'upheld', 'response' => 'flowtest accepted']);
    $t->ok(fetchValue("SELECT status FROM enforcement_actions WHERE id = ?", [$suspId]) === 'closed' && !str_contains(http("$base/verify.php?t=$vt", test_jar())['body'], 'Permit suspended'),
        'accepting the appeal closes the action and lifts the suspension');
}

// Rider record correction: every field, old and new values in the audit log; chairpersons cannot set the status
$before = fetchOne("SELECT * FROM riders WHERE id = ?", [$testRider]);
$editUrl = "$base/pages/chairperson/edit-rider.php?id=$testRider";
$form = ['full_name' => $before['full_name'], 'nin' => 'CM9' . strtoupper(substr(md5($tag), 0, 11)), 'date_of_birth' => '1990-04-02', 'gender' => 'Female', 'marital_status' => 'Single',
    'phone' => preg_replace('/^\+256/', '', (string) $before['phone']), 'email' => '', 'physical_address' => 'Flowtest Lane 4', 'next_of_kin_name' => 'Flow Kin',
    'next_of_kin_contact' => '772000111', 'bike_plate' => $before['bike_plate'], 'bike_model' => 'Bajaj', 'route' => '', 'status' => 'active', 'expiry_date' => '2099-01-01'];
http($editUrl, $chair, ['csrf_token' => csrf(http($editUrl, $chair)['body'])] + $form);
$after = fetchOne("SELECT * FROM riders WHERE id = ?", [$testRider]);
$t->ok($after['physical_address'] === 'Flowtest Lane 4' && $after['date_of_birth'] === '1990-04-02' && $after['next_of_kin_contact'] === '+256772000111', 'a chairperson corrects birth date, address and next of kin', json_encode([$after['physical_address'], $after['date_of_birth'], $after['next_of_kin_contact']]));
$t->ok($after['status'] === $before['status'] && (string) $after['expiry_date'] === (string) $before['expiry_date'], 'a chairperson cannot change the permit status or expiry by posting them');
$log = (string) fetchValue("SELECT details FROM audit_logs WHERE entity_type = 'rider' AND entity_id = ? ORDER BY id DESC LIMIT 1", [$before['id_number']]);
$t->ok(str_contains($log, 'address: ') && str_contains($log, '-> Flowtest Lane 4'), 'the audit log keeps the old and new value of each changed field', $log);
$http = http($editUrl, $chair, ['csrf_token' => csrf(http($editUrl, $chair)['body'])] + ['nin' => 'SHORT'] + $form);
$t->ok(str_contains($http['body'], '14 letters and digits'), 'a malformed NIN is refused');
require_once __DIR__ . '/../packages/backend/rider_records.php';
runQuery("INSERT INTO riders (city_id, stage_id, id_number, verify_token, full_name, nin, phone, email, bike_plate, status, member_since, annual_tax)
    VALUES (?, ?, ?, ?, 'FLOW ERASE ME', 'CM00000000ERAS', '+256700000999', ?, 'ERASE 1', 'pending', CURDATE(), 0)",
    [$before['city_id'], $before['stage_id'], 'FLOW-ERASE-' . substr(md5($tag), 0, 6), bin2hex(random_bytes(10)), "$tag-erase@bodaerp.site"]);
$eraseId = (int) db()->insert_id; $created['riders'][] = $eraseId;
$_SESSION['user_id'] = null;
rider_anonymise($eraseId, 0, 'flowtest');
$gone = fetchOne("SELECT full_name, nin, phone, email, deleted_at FROM riders WHERE id = ?", [$eraseId]);
$t->ok(str_starts_with($gone['full_name'], 'REMOVED RIDER') && $gone['nin'] === null && $gone['phone'] === '' && $gone['email'] === null && $gone['deleted_at'] !== null,
    'removing personal data erases name, NIN, phone and email and keeps the record for the accounts', json_encode($gone));

// Riders with only a phone number: the chairperson sends an SMS link, the rider sets a password and signs in by phone
$phoneOnly = '+2567' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
runQuery("INSERT INTO riders (city_id, stage_id, id_number, verify_token, full_name, nin, phone, bike_plate, status, member_since, annual_tax)
    VALUES (?, ?, ?, ?, 'FLOW PHONE RIDER', NULL, ?, 'PHONE 1', 'pending', CURDATE(), 0)", [$before['city_id'], $before['stage_id'], 'FLOW-PH-' . substr(md5($tag), 0, 6), bin2hex(random_bytes(10)), $phoneOnly]);
$phoneRider = (int) db()->insert_id; $created['riders'][] = $phoneRider;
$profile = "$base/pages/chairperson/rider-profile.php?id=$phoneRider";
$smsBefore = glob(storage_path('sms') . '/*.txt') ?: [];
http($profile, $chair, ['csrf_token' => csrf(http($profile, $chair)['body']), 'action' => 'invite']);
$phoneUser = fetchOne("SELECT u.id, u.email, u.phone FROM riders r JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$phoneRider]);
if ($phoneUser) $created['users'][] = (int) $phoneUser['id'];
$t->ok($phoneUser && $phoneUser['email'] === null && $phoneUser['phone'] === $phoneOnly, 'a rider without email gets a portal account tied to their phone', json_encode($phoneUser));
$newSms = array_diff(glob(storage_path('sms') . '/*.txt') ?: [], $smsBefore);
$smsText = '';
foreach ($newSms as $file) { $body = (string) file_get_contents($file); if (str_contains($body, substr($phoneOnly, 1))) $smsText = $body; @unlink($file); }
if (!sms_ready()) {
    $t->ok(preg_match('/token=([a-f0-9]{24}\.[a-f0-9]{64})/', $smsText, $tm) === 1, 'the set-password link goes to the rider by SMS', $smsText);
    if (!empty($tm[1])) {
        $resetUrl = "$base/reset-password.php?welcome=1&token={$tm[1]}";
        $jarR = test_jar();
        $resetResp = http($resetUrl, $jarR, ['csrf_token' => csrf(http($resetUrl, $jarR)['body']), 'token' => $tm[1], 'welcome' => 1, 'password' => 'Lane-Kite-2026!x', 'password_confirm' => 'Lane-Kite-2026!x']);
        $why = '';
        $setAt = fetchValue("SELECT password_changed_at FROM users WHERE id = ?", [(int) ($phoneUser['id'] ?? 0)]);
        $t->ok($setAt !== null, 'the SMS link sets the password', substr(preg_replace('/\s+/', ' ', strip_tags((string) ($resetResp['body'] ?? ''))), 0, 400) . ' | ' . $smsText . (preg_match('/notice-error[^>]*>([^<]+)/', (string) ($resetResp['body'] ?? ''), $em) ? $em[1] : 'HTTP ' . ($resetResp['code'] ?? '?') . ' ' . ($resetResp['redirect'] ?? '')));
        $pj = test_login($base, '0' . substr($phoneOnly, 4), 'Lane-Kite-2026!x', $why);
        $t->ok((bool) $pj, 'the rider signs in with their phone number (0772... form) and the new password', $why);
        if ($pj) $t->ok(str_contains(http("$base/pages/account/settings.php", $pj)['body'], 'No email address on this account'), 'the account page explains sign-in by phone');
    }
}

// Rider import: problems per row, nothing saved until confirmed
$csv = tempnam(sys_get_temp_dir(), 'imp');
$stageName = (string) fetchValue("SELECT name FROM stages WHERE id = ?", [$stage['id']]);
file_put_contents($csv, "Full name,NIN,Phone,Bike plate,Stage,Permit valid until\n"
    . "IMPORT ONE,CM90012345$tag[0]AAA,0772111222,IMP " . strtoupper(substr($tag, 0, 4)) . ",$stageName,30/06/2027\n"
    . "IMPORT TWO,short,0772111333,IMP2,No such stage,\n");
$checked = import_check(import_read_cells($csv, 'register.csv'), $stage['city_id']);
$t->ok($checked['total'] === 2 && count($checked['ready']) === 1 && isset($checked['problems'][3]) && $checked['ready'][0]['expiry_date'] === '2027-06-30',
    'the import check passes a good row, lists the bad row by its Excel row number and reads dd/mm/yyyy dates', json_encode($checked['problems']));
unlink($csv);

// Remove everything this run created, so the suite can run again on the same database.
foreach ($created['riders'] as $id) {
    runQuery("DELETE h FROM cash_handovers h JOIN payments p ON p.handover_id = h.id WHERE p.rider_id = ?", [$id]);
    foreach (['notifications', 'payments', 'permit_checks', 'id_cards', 'enforcement_actions'] as $tbl) runQuery("DELETE FROM $tbl WHERE rider_id = ?", [$id]);
    runQuery("DELETE FROM riders WHERE id = ?", [$id]);
}
runQuery("DELETE FROM rate_limits WHERE bucket LIKE 'dup-check:%' OR bucket LIKE 'permit-check:%' OR bucket LIKE 'reminder-sms:%'");
runQuery("DELETE FROM automation_sends WHERE target IN ('" . implode("','", array_map('intval', array_merge($created['riders'], $created['users']))) . "')");
runQuery("DELETE FROM email_log WHERE to_email LIKE ?", ["$tag%"]);
foreach ($created['contact'] as $id) runQuery("DELETE FROM contact_messages WHERE id = ?", [$id]);
foreach ($created['users'] as $id) {
    foreach (['user_tokens', 'login_sessions'] as $tbl) runQuery("DELETE FROM $tbl WHERE user_id = ?", [$id]);
    runQuery("DELETE FROM users WHERE id = ?", [$id]);
}
runQuery("DELETE FROM login_attempts WHERE email LIKE ?", ["$tag%"]);
runQuery("DELETE FROM rate_limits WHERE bucket LIKE 'contact:%' OR bucket LIKE 'reset-%' OR bucket LIKE 'login-ip:%'");
foreach ($created['users'] as $id) runQuery("DELETE FROM rate_limits WHERE bucket = ?", ["pw-change:$id"]);

$t->finish();
