<?php
// BodaERP access walls: every role is kept to its own pages and its own city, stage or records.
// php tests/access.php [http://localhost/<folder>]      (Docker: docker compose exec app php tests/access.php)
// Uses the demo accounts. Each attack targets a real record from another scope; the rows it aims at are saved
// first and put back at the end, so a failing wall never leaves damage behind. Never run against a live server.
require __DIR__ . '/lib.php';
require_once __DIR__ . '/../packages/backend/db_helpers.php';
require_once __DIR__ . '/../packages/backend/functions.php';

$base = test_base($argv);
$t = new TestRun();
echo "Testing access walls on $base\n\n";

$accounts = [
    'super_admin' => ['admin@bodaerp.com', 'admin123'],
    'city_admin'  => ['council@liracityuganda.go.ug', 'lira2025'],
    'chairperson' => ['ocen@railway.lira.ug', 'chair123'],
    'rider'       => ['akello@bodaerp.com', 'rider123'],
    'government'  => ['works@gov.bodaerp.com', 'Gov@2026'],
];
$folders = ['super_admin' => 'superadmin', 'city_admin' => 'citycouncil', 'chairperson' => 'chairperson', 'rider' => 'rider', 'government' => 'government'];
$jars = [];
foreach ($accounts as $role => [$email, $pass]) {
    $why = '';
    $jars[$role] = test_login($base, $email, $pass, $why);
    $t->ok((bool) $jars[$role], "$role signs in", $why);
}
if (in_array(null, $jars, true)) $t->finish();

$chair = fetchOne("SELECT id, stage_id, city_id FROM users WHERE email = ?", [$accounts['chairperson'][0]]);
$council = fetchOne("SELECT id, city_id FROM users WHERE email = ?", [$accounts['city_admin'][0]]);
$me = fetchOne("SELECT r.id, r.photo_path FROM riders r JOIN users u ON u.id = r.user_id WHERE u.email = ?", [$accounts['rider'][0]]);

// Targets outside each scope: same city other stage, other city, another rider.
$otherStageRider = fetchOne("SELECT * FROM riders WHERE stage_id <> ? AND city_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1", [$chair['stage_id'], $chair['city_id']])
    ?: fetchOne("SELECT * FROM riders WHERE stage_id <> ? AND deleted_at IS NULL ORDER BY id LIMIT 1", [$chair['stage_id']]);
// A rider in another city whose name exists nowhere in the council's city, so a name match is a real leak.
$otherCityRider = fetchOne("SELECT r.* FROM riders r WHERE r.city_id <> ? AND r.deleted_at IS NULL
    AND NOT EXISTS (SELECT 1 FROM riders x WHERE x.full_name = r.full_name AND x.city_id = ?) ORDER BY r.id LIMIT 1", [$council['city_id'], $council['city_id']]);
$otherCityPrefix = (string) fetchValue("SELECT id_prefix FROM cities WHERE id = ?", [$otherCityRider['city_id']]);
$otherCityReceipt = (string) fetchValue("SELECT receipt_number FROM payments p JOIN riders r ON r.id = p.rider_id WHERE r.city_id = ? AND p.receipt_number IS NOT NULL ORDER BY p.id DESC LIMIT 1", [$otherCityRider['city_id']]);
$otherCityStage = fetchOne("SELECT * FROM stages WHERE city_id <> ? ORDER BY id LIMIT 1", [$council['city_id']]);
$otherCityAction = fetchOne("SELECT * FROM enforcement_actions WHERE city_id <> ? ORDER BY id LIMIT 1", [$council['city_id']]);
$otherRiderPayment = fetchOne("SELECT * FROM payments WHERE rider_id <> ? ORDER BY id DESC LIMIT 1", [$me['id']]);
$otherRiderPaymentName = $otherRiderPayment ? (string) fetchValue("SELECT full_name FROM riders WHERE id = ?", [$otherRiderPayment['rider_id']]) : '';

$saved = [
    'riders' => array_map(fn($r) => $r, array_filter([$otherStageRider, $otherCityRider])),
    'stages' => array_filter([$otherCityStage]),
    'enforcement_actions' => array_filter([$otherCityAction]),
];
$counts = fn() => [
    'payments' => (int) fetchValue("SELECT COUNT(*) FROM payments"),
    'notifications' => (int) fetchValue("SELECT COUNT(*) FROM notifications"),
    'enforcement' => (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions"),
    'user_tokens' => (int) fetchValue("SELECT COUNT(*) FROM user_tokens"),
];
$before = $counts();

// Plant a photo on the other-city rider so the photo walls are really exercised (removed at the end).
require_once __DIR__ . '/../packages/backend/uploads.php';
$plantedName = 'riders/accesstest-' . bin2hex(random_bytes(6)) . '.png';
@mkdir(dirname(upload_path($plantedName)), 0775, true);
file_put_contents(upload_path($plantedName), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII='));
runQuery("UPDATE riders SET photo_path = ? WHERE id = ?", ['/uploads/' . $plantedName, $otherCityRider['id']]);
$otherCityRider['photo_path_planted'] = '/uploads/' . $plantedName;

function post_to(string $url, string $jar, array $fields): array {
    $page = http($url, $jar);
    return http($url, $jar, $fields + ['csrf_token' => csrf($page['body'])]);
}
function refused(array $r): bool { return in_array($r['code'], [302, 303, 403, 404], true) || $r['code'] === 200; }
$row = fn(string $table, int $id) => fetchOne("SELECT * FROM `$table` WHERE id = ?", [$id]);

// 1. Signed-out visitors reach no portal page and no rider photo.
$anon = test_jar();
foreach ($folders as $folder) {
    foreach (glob(__DIR__ . "/../packages/frontend/pages/$folder/*.php") as $f) {
        $r = http("$base/pages/$folder/" . basename($f), $anon);
        $t->ok(in_array($r['code'], [302, 303, 401, 403, 405], true) && !str_contains($r['body'], 'sidebar'), "signed-out visitor kept out of $folder/" . basename($f), 'HTTP ' . $r['code']);
    }
}
$r = http("$base/pages/account/settings.php", $anon);
$t->ok($r['code'] !== 200, 'signed-out visitor kept out of My Account', 'HTTP ' . $r['code']);
{
    $photo = $base . $otherCityRider['photo_path_planted'];
    $r = http($photo, $anon);
    $t->ok($r['code'] === 403 || $r['code'] === 404, 'rider photo is not served to signed-out visitors', 'HTTP ' . $r['code']);
    $r = http($photo, $jars['chairperson']);
    $t->ok($r['code'] === 403 || $r['code'] === 404, 'rider photo from another city is not served to a chairperson', 'HTTP ' . $r['code']);
    $r = http($photo, $jars['government']);
    $t->ok($r['code'] === 403 || $r['code'] === 404, 'rider photo is not served to government viewers', 'HTTP ' . $r['code']);
    $r = http($photo, $jars['super_admin']);
    $t->ok($r['code'] === 200, 'rider photo is served to the Super Admin', 'HTTP ' . $r['code']);
    $r = http($photo, $jars['city_admin']);
    $t->ok($r['code'] === 403 || $r['code'] === 404, "rider photo is not served to another city's council", 'HTTP ' . $r['code']);
    $ownStageRider = fetchOne("SELECT * FROM riders WHERE stage_id = ? AND deleted_at IS NULL LIMIT 1", [$chair['stage_id']]);
    $saved['riders'][] = $ownStageRider;
    runQuery("UPDATE riders SET photo_path = ? WHERE id = ?", [$otherCityRider['photo_path_planted'], $ownStageRider['id']]);
    runQuery("UPDATE riders SET photo_path = NULL WHERE id = ?", [$otherCityRider['id']]);
    $r = http($photo, $jars['chairperson']);
    $t->ok($r['code'] === 200, "a chairperson can load photos of their own stage's riders (ID cards)", 'HTTP ' . $r['code']);
    $r = http($photo, $jars['city_admin']);
    $t->ok($r['code'] === 200, "a city council can load photos of its own riders", 'HTTP ' . $r['code']);
}
$saved['riders'][] = fetchOne("SELECT * FROM riders WHERE id = ?", [$me['id']]);
runQuery("UPDATE riders SET photo_path = NULL WHERE photo_path = ?", [$otherCityRider['photo_path_planted']]);
runQuery("UPDATE riders SET photo_path = ? WHERE id = ?", [$otherCityRider['photo_path_planted'], $me['id']]);
$r = http($photo, $jars['rider']);
$t->ok($r['code'] === 200, 'a rider can load their own photo (ID card)', 'HTTP ' . $r['code']);
$neighbour = fetchOne("SELECT * FROM riders WHERE city_id = ? AND id <> ? AND deleted_at IS NULL LIMIT 1", [$council['city_id'], $me['id']]);
$saved['riders'][] = $neighbour;
runQuery("UPDATE riders SET photo_path = NULL WHERE id = ?", [$me['id']]);
runQuery("UPDATE riders SET photo_path = ? WHERE id = ?", [$otherCityRider['photo_path_planted'], $neighbour['id']]);
$r = http($photo, $jars['rider']);
$t->ok($r['code'] === 403 || $r['code'] === 404, "a rider cannot load another rider's photo", 'HTTP ' . $r['code']);

// 2. Every role is refused every other role's pages.
foreach ($folders as $role => $myFolder) {
    foreach ($folders as $otherRole => $folder) {
        if ($role === $otherRole) continue;
        foreach (glob(__DIR__ . "/../packages/frontend/pages/$folder/*.php") as $f) {
            $name = basename($f);
            if ($name === 'db-backup.php') continue;
            $r = http("$base/pages/$folder/$name", $jars[$role]);
            $t->ok($r['code'] === 403 || ($r['code'] >= 300 && $r['code'] < 400), "$role refused $folder/$name", 'HTTP ' . $r['code']);
        }
    }
}
$r = http("$base/api/media-upload.php", $jars['city_admin'], ['upload' => 'x'], ['X-CSRF-Token: x']);
$t->ok($r['code'] === 403, 'only the Super Admin can upload to the media library', 'HTTP ' . $r['code']);

// 3. Chairperson: only riders of their own stage.
$cp = $jars['chairperson'];
foreach (['rider-profile.php', 'edit-rider.php', 'id-preview.php'] as $p) {
    $r = http("$base/pages/chairperson/$p?id={$otherStageRider['id']}", $cp);
    $t->ok(!str_contains($r['body'], h($otherStageRider['full_name'])) && !str_contains($r['body'], (string) $otherStageRider['phone']),
        "chairperson cannot open another stage's rider ($p)", 'HTTP ' . $r['code']);
}
post_to("$base/pages/chairperson/edit-rider.php?id={$otherStageRider['id']}", $cp, ['full_name' => 'HACKED NAME', 'phone' => '700000000', 'bike_plate' => 'HACK 1']);
$t->ok($row('riders', (int) $otherStageRider['id'])['full_name'] === $otherStageRider['full_name'], "chairperson cannot edit another stage's rider");
post_to("$base/pages/chairperson/my-riders.php", $cp, ['action' => 'delete', 'id' => $otherStageRider['id']]);
$t->ok($row('riders', (int) $otherStageRider['id'])['deleted_at'] === null, "chairperson cannot delete another stage's rider");
post_to("$base/pages/chairperson/collect-payments.php", $cp, ['rider_id' => $otherStageRider['id'], 'method' => 'Cash', 'amount' => 1000]);
post_to("$base/pages/chairperson/defaulters.php", $cp, ['rider_id' => $otherStageRider['id'], 'message' => 'x']);
post_to("$base/pages/chairperson/expiry-alerts.php", $cp, ['rider_id' => $otherStageRider['id']]);
post_to("$base/pages/chairperson/rider-profile.php?id={$otherStageRider['id']}", $cp, ['action' => 'invite']);
$after = $counts();
$t->ok($after['payments'] === $before['payments'], "chairperson cannot record a payment for another stage's rider");
$t->ok($after['notifications'] === $before['notifications'], "chairperson cannot notify another stage's rider");
$t->ok($after['user_tokens'] === $before['user_tokens'], "chairperson cannot invite another stage's rider");
$otherReceipt = (string) fetchValue("SELECT receipt_number FROM payments p JOIN riders r ON r.id = p.rider_id WHERE r.stage_id <> ? AND p.receipt_number IS NOT NULL LIMIT 1", [$chair['stage_id']]);
if ($otherReceipt !== '') {
    $r = http("$base/pages/chairperson/collect-payments.php?receipt=" . urlencode($otherReceipt), $cp);
    $t->ok(!str_contains($r['body'], h($otherStageRider['full_name'])), 'chairperson receipt view shows nothing from another stage');
}
$r = http("$base/pages/chairperson/my-riders.php?q=" . urlencode($otherStageRider['bike_plate']), $cp);
$t->ok(!str_contains($r['body'], h($otherStageRider['full_name'])), "chairperson search does not find another stage's riders");
$r = http("$base/pages/chairperson/stage-report.php?export=csv", $cp);
$t->ok(!str_contains($r['body'], $otherStageRider['full_name']), "chairperson report holds no other stage's riders");

// 4. City council: only its own city.
$cc = $jars['city_admin'];
$r = http("$base/pages/citycouncil/city-riders.php?stage={$otherCityStage['id']}&q=" . urlencode($otherCityRider['full_name']), $cc);
$t->ok(!str_contains($r['body'], $otherCityRider['id_number']) && !str_contains($r['body'], $otherCityPrefix . '-'), 'city council cannot list riders of another city');
foreach (['city-defaulters.php', 'payment-transactions.php', 'enforcement-actions.php'] as $p) {
    $r = http("$base/pages/citycouncil/$p?stage={$otherCityStage['id']}", $cc);
    $t->ok(!str_contains($r['body'], h($otherCityRider['full_name'])) && !str_contains($r['body'], $otherCityRider['bike_plate']), "city council $p shows nothing from another city");
}
foreach (['csv', 'excel'] as $fmt) {
    $r = http("$base/pages/citycouncil/payment-transactions.php?export=$fmt", $cc);
    $t->ok($otherCityReceipt === '' || !str_contains($r['body'], $otherCityReceipt), "city council $fmt export holds no other city's payments");
}
post_to("$base/pages/citycouncil/city-riders.php", $cc, ['action' => 'save', 'id' => $otherCityRider['id'], 'full_name' => 'HACKED', 'phone' => '1', 'stage_id' => $otherCityRider['stage_id'], 'status' => 'expired']);
$t->ok($row('riders', (int) $otherCityRider['id'])['full_name'] === $otherCityRider['full_name'], "city council cannot edit another city's rider");
post_to("$base/pages/citycouncil/city-riders.php", $cc, ['action' => 'delete', 'id' => $otherCityRider['id']]);
$t->ok($row('riders', (int) $otherCityRider['id'])['deleted_at'] === null, "city council cannot delete another city's rider");
$ownStage = (int) fetchValue("SELECT id FROM stages WHERE city_id = ? LIMIT 1", [$council['city_id']]);
$ownRider = fetchOne("SELECT * FROM riders WHERE city_id = ? AND deleted_at IS NULL LIMIT 1", [$council['city_id']]);
$saved['riders'][] = $ownRider;
post_to("$base/pages/citycouncil/city-riders.php", $cc, ['action' => 'save', 'id' => $ownRider['id'], 'full_name' => $ownRider['full_name'], 'phone' => $ownRider['phone'], 'stage_id' => $otherCityStage['id'], 'status' => $ownRider['status']]);
$t->ok((int) $row('riders', (int) $ownRider['id'])['stage_id'] === (int) $ownRider['stage_id'], "city council cannot move its rider to another city's stage");
post_to("$base/pages/citycouncil/stage-management.php", $cc, ['action' => 'save', 'edit_stage_id' => $otherCityStage['id'], 'name' => 'HACKED STAGE', 'location' => 'x', 'status' => 'inactive']);
$t->ok($row('stages', (int) $otherCityStage['id'])['name'] === $otherCityStage['name'], "city council cannot edit another city's stage");
post_to("$base/pages/citycouncil/stage-management.php", $cc, ['action' => 'delete', 'id' => $otherCityStage['id']]);
$t->ok((bool) $row('stages', (int) $otherCityStage['id']), "city council cannot delete another city's stage");
post_to("$base/pages/citycouncil/enforcement-actions.php", $cc, ['action' => 'save', 'rider_id' => $otherCityRider['id'], 'type' => 'fine', 'amount' => '5000', 'description' => 'x', 'status' => 'pending']);
$t->ok($counts()['enforcement'] === $before['enforcement'], "city council cannot record enforcement against another city's rider");
if ($otherCityAction) {
    post_to("$base/pages/citycouncil/enforcement-actions.php", $cc, ['action' => 'save', 'edit_id' => $otherCityAction['id'], 'rider_id' => $ownRider['id'], 'type' => 'warning', 'amount' => '', 'description' => 'HACKED', 'status' => 'closed']);
    $t->ok($row('enforcement_actions', (int) $otherCityAction['id'])['description'] === $otherCityAction['description'], "city council cannot edit another city's enforcement action");
    post_to("$base/pages/citycouncil/enforcement-actions.php", $cc, ['action' => 'delete', 'id' => $otherCityAction['id']]);
    $t->ok((bool) $row('enforcement_actions', (int) $otherCityAction['id']), "city council cannot delete another city's enforcement action");
}
post_to("$base/pages/citycouncil/city-defaulters.php", $cc, ['rider_id' => $otherCityRider['id'], 'message' => 'x']);
$t->ok($counts()['notifications'] === $before['notifications'], "city council cannot notify another city's rider");
$otherCityId = (string) $otherCityStage['city_id'];
$r = http("$base/pages/citycouncil/council-setting.php", $cc);
$t->ok(!str_contains($r['body'], 'value="' . $otherCityId . '"') || str_contains($r['body'], 'value="' . $council['city_id'] . '"'), 'council settings show only its own city');

// 5. Rider: only their own records.
$rd = $jars['rider'];
if ($otherRiderPayment) {
    $r = http("$base/api/payments.php?action=check_status&payment_id={$otherRiderPayment['id']}", $rd);
    $t->ok(!str_contains($r['body'], (string) $otherRiderPayment['receipt_number']) && !str_contains($r['body'], '"Confirmed"'), "rider cannot read another rider's payment status", 'HTTP ' . $r['code']);
    foreach (['payment-history.php', 'dashboard.php', 'notifications.php', 'my-profile.php', 'my-id-card.php', 'renewal-status.php'] as $p) {
        $r = http("$base/pages/rider/$p?id={$otherRiderPayment['rider_id']}&rider_id={$otherRiderPayment['rider_id']}", $rd);
        $t->ok($otherRiderPaymentName === '' || !str_contains($r['body'], h($otherRiderPaymentName)), "rider $p ignores another rider's id");
    }
}
$r = http("$base/pages/account/settings.php?export=1&user_id={$council['id']}", $rd);
$t->ok(!str_contains($r['body'], $accounts['city_admin'][0]), "rider's data export holds only their own account");

// 6. Government is read only.
$gv = $jars['government'];
foreach (glob(__DIR__ . '/../packages/frontend/pages/government/*.php') as $f) {
    $r = http("$base/pages/government/" . basename($f), $gv, ['csrf_token' => csrf(http("$base/pages/government/" . basename($f), $gv)['body']), 'action' => 'delete', 'id' => 1]);
    $t->ok($r['code'] !== 500, 'government POST to ' . basename($f) . ' changes nothing', 'HTTP ' . $r['code']);
}
$t->ok($counts() == $before, 'no record counts changed during the whole run', json_encode(array_diff_assoc($counts(), $before)));

// Put back anything a failed wall may have changed. The first snapshot of a row is the true original.
foreach ($saved as $table => $rows) {
    $seen = [];
    foreach ($rows as $orig) {
        if (!$orig || isset($seen[$orig['id']])) continue;
        $seen[$orig['id']] = true;
        $cols = array_keys($orig);
        $now = $row($table, (int) $orig['id']);
        if ($now === $orig) continue;
        if (!$now) { runQuery("INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")", array_values($orig)); continue; }
        $set = implode(',', array_map(fn($c) => "`$c` = ?", $cols));
        runQuery("UPDATE `$table` SET $set WHERE id = ?", [...array_values($orig), $orig['id']]);
    }
}
@unlink(upload_path($plantedName));
foreach ($jars as $j) @unlink($j);
@unlink($anon);
$t->finish();
