<?php
// My Account (every role): password, notifications (email, SMS, portal), optional two-step sign-in by SMS or
// email (no authenticator app, by decision), mobile number confirmation, language for riders, email confirmation,
// signed-in devices. Riders also download their data and send correction / deletion requests here.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/accounts.php';
$me = require_role(array_keys(ROLE_LABELS));
$uid = (int) $me['user_id'];
$sidebar = ['super_admin' => 'superadmin', 'city_admin' => 'citycouncil', 'chairperson' => 'chairperson', 'rider' => 'rider', 'government' => 'government'][$me['role']];
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$uid]);
$riderId = (int) ($me['rider_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if (in_array($action, ['photo_upload', 'photo_remove'], true) && $me['role'] !== 'rider') {
        // Staff profile photo: shown in the top bar and beside the name in staff lists. A rider's photo is the one
        // taken at registration, so riders change it through their chairperson.
        $old = (string) ($user['avatar_path'] ?? '');
        $new = null;
        if ($action === 'photo_upload') {
            $up = store_upload($_FILES['photo'] ?? [], 'avatars', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 2 * 1024 * 1024);
            if (!$up['ok']) { flash('error', $up['error']); redirect('/pages/account/settings.php'); }
            $new = $up['path'];
        }
        runQuery("UPDATE users SET avatar_path = ? WHERE id = ?", [$new, $uid]);
        if ($old !== '' && str_starts_with($old, '/uploads/avatars/')) delete_upload($old);
        $_SESSION['avatar_path'] = $new;
        audit_log('UPDATE', 'user', (string) $uid, $new ? 'Profile photo changed' : 'Profile photo removed');
        flash('success', $new ? 'Your profile photo is saved.' : 'Your profile photo was removed.');
        redirect('/pages/account/settings.php');
    }
    if ($action === 'password' && !rate_limit('pw-change:' . $uid, 5, 900)) {
        flash('error', 'Too many password change attempts. Wait 15 minutes and try again.');
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $problems = password_problems($new, (string) $user['email'], $user['name']);
        if (!password_verify($current, $user['password_hash'])) $problems = ['Your current password is not right.'];
        elseif (!$problems && !hash_equals($new, (string) ($_POST['confirm_password'] ?? ''))) $problems[] = 'The two new passwords do not match.';
        elseif (!$problems && password_verify($new, $user['password_hash'])) $problems[] = 'Choose a password you have not used for this account.';
        if ($problems) flash('error', implode(' ', $problems));
        else {
            set_user_password($uid, $new);
            session_regenerate_id(true);
            auth_session_register();                 // keep this device signed in; every other device is signed out
            audit_log('PASSWORD_CHANGED', 'user', (string) $uid, 'Changed own password');
            if ($user['email']) send_template_email('password_changed', $user['email'], ['name' => $user['name'], 'changed_at' => date('j M Y, H:i')]);
            flash('success', 'Password changed. Other devices signed in to your account have been signed out.');
        }
    } elseif ($action === 'notifications') {
        $language = language_or_default((string) ($_POST['language'] ?? $user['language']));
        runQuery("UPDATE users SET notify_email = ?, notify_in_app = ?, notify_sms = ?, language = ? WHERE id = ?",
            [!empty($_POST['notify_email']) ? 1 : 0, !empty($_POST['notify_in_app']) ? 1 : 0, !empty($_POST['notify_sms']) ? 1 : 0, $language, $uid]);
        if ($riderId) runQuery("UPDATE riders SET language = ? WHERE id = ?", [$language, $riderId]);
        audit_log('UPDATE', 'user', (string) $uid, 'Notification preferences changed');
        flash('success', 'Notification preferences saved.');
    } elseif ($action === 'phone_send') {
        $phone = sms_normalize_phone((string) ($_POST['phone'] ?? ''));
        if (!$phone) flash('error', 'Enter an MTN or Airtel number, for example 0772 123456.');
        elseif (!sms_ready()) flash('error', 'Text messages are not set up on this site yet. Ask your administrator.');
        elseif (!rate_limit('phone-code:' . $uid, 3, 900)) flash('error', 'A code was sent recently. Wait 15 minutes before asking for another.');
        else {
            $code = (string) random_int(100000, 999999);
            $_SESSION['phone_confirm'] = ['phone' => $phone, 'hash' => password_hash($code, PASSWORD_DEFAULT), 'expires' => time() + 600, 'tries' => 0];
            $ok = send_template_sms('two_factor_code', $phone, ['code' => $code]);
            flash($ok ? 'success' : 'error', $ok ? 'We texted a 6-digit code to the number ending ' . sms_phone_hint($phone) . '. Enter it below.' : 'The text message could not be sent. Check the number and try again.');
            if (!$ok) unset($_SESSION['phone_confirm']);
        }
    } elseif ($action === 'phone_confirm') {
        $p = $_SESSION['phone_confirm'] ?? null;
        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
        if (!$p || $p['expires'] < time() || $p['tries'] >= 5) { unset($_SESSION['phone_confirm']); flash('error', 'That code expired. Ask for a new one.'); }
        elseif (!password_verify($code, $p['hash'])) { $_SESSION['phone_confirm']['tries']++; flash('error', 'That code is not right. Check the latest text message.'); }
        else {
            unset($_SESSION['phone_confirm']);
            runQuery("UPDATE users SET phone = ?, phone_verified_at = NOW() WHERE id = ?", ['0' . substr($p['phone'], 3), $uid]);
            audit_log('UPDATE', 'user', (string) $uid, 'Confirmed mobile number ending ' . sms_phone_hint($p['phone']));
            flash('success', 'Mobile number confirmed. You can now get sign-in codes by SMS.');
        }
    } elseif ($action === 'two_factor') {
        $on = !empty($_POST['enable']);
        $channel = ($_POST['channel'] ?? $user['two_factor_channel']) === 'sms' ? 'sms' : 'email';
        $smsOk = sms_ready() && $user['phone_verified_at'] && sms_normalize_phone($user['phone']);
        if ($on && $channel === 'sms' && !$smsOk) flash('error', 'Confirm your mobile number first, so the codes reach your phone.');
        elseif ($on && $channel === 'email' && mail_transport_name() !== 'smtp') flash('error', 'Codes by email need the site\'s email to be set up. Choose SMS, or ask your administrator.');
        elseif ($on && $channel === 'email' && !$user['email_verified_at']) flash('error', 'Confirm your email address first, so the sign-in codes reach you.');
        else {
            runQuery("UPDATE users SET two_factor_enabled = ?, two_factor_channel = ? WHERE id = ?", [$on ? 1 : 0, $channel, $uid]);
            audit_log('UPDATE', 'user', (string) $uid, 'Two-step sign-in ' . ($on ? "turned on ($channel)" : 'turned off'));
            flash('success', $on ? 'Two-step sign-in is on. Next time you sign in we will ' . ($channel === 'sms' ? 'text' : 'email') . ' you a code.' : 'Two-step sign-in is off.');
        }
    } elseif ($action === 'verify_email') {
        if (!rate_limit('verify-mail:' . $uid, 3, 3600)) flash('error', 'A confirmation email was sent recently. Check your inbox and spam folder.');
        else { $ok = account_send_verification($uid); flash($ok ? 'success' : 'error', $ok ? 'Confirmation email sent. Open the link in it within 48 hours.' : 'The confirmation email could not be sent. Try again later.'); }
    } elseif ($action === 'sign_out_others') {
        runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ? AND id <> ?", [$uid, session_id()]);
        runQuery("DELETE FROM user_tokens WHERE user_id = ? AND purpose = 'remember'", [$uid]);
        audit_log('LOGOUT', 'user', (string) $uid, 'Signed out all other devices');
        flash('success', 'All other devices have been signed out.');
    } elseif ($action === 'data_request' && $riderId) {
        $type = in_array($_POST['type'] ?? '', ['correction', 'deletion', 'objection'], true) ? $_POST['type'] : 'correction';
        $details = trim((string) ($_POST['details'] ?? ''));
        if (mb_strlen($details) < 10) flash('error', 'Describe what you need in a few words (at least 10 characters).');
        elseif (fetchValue("SELECT COUNT(*) FROM data_requests WHERE user_id = ? AND status = 'open' AND type = ?", [$uid, $type])) flash('error', 'You already have an open request of this kind. We will reply to it first.');
        else {
            runQuery("INSERT INTO data_requests (user_id, rider_id, type, details) VALUES (?,?,?,?)", [$uid, $riderId, $type, mb_substr($details, 0, 3000)]);
            $rid = fetchOne("SELECT id_number FROM riders WHERE id = ?", [$riderId]);
            $inbox = get_setting('contact_inbox_email', get_setting('contact_email', ''));
            if ($inbox) send_template_email('data_request_alert', $inbox, ['name' => $user['name'], 'id_number' => $rid['id_number'] ?? '', 'type' => $type, 'details' => $details, 'admin_url' => app_url('pages/superadmin/data-requests.php')]);
            audit_log('CREATE', 'data_request', null, "Rider sent a $type request");
            flash('success', 'Request sent. You will see the answer in your notifications.');
        }
    }
    redirect('/pages/account/settings.php');
}

if (isset($_GET['download']) && $riderId) {
    $rider = fetchOne("SELECT r.id_number, r.full_name, r.nin, r.date_of_birth, r.gender, r.marital_status, r.phone, r.email, r.physical_address,
        r.next_of_kin_name, r.next_of_kin_contact, r.bike_plate, r.bike_model, r.route, r.status, r.member_since, r.expiry_date, r.annual_tax,
        s.name AS stage, c.name AS city FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id WHERE r.id = ?", [$riderId]);
    $export = [
        'exported_at' => date('c'), 'account' => ['name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone'], 'created_at' => $user['created_at'], 'last_login_at' => $user['last_login_at']],
        'rider' => $rider,
        'payments' => fetchAll("SELECT receipt_number, amount, payment_method, status, fiscal_year, paid_at FROM payments WHERE rider_id = ? ORDER BY paid_at", [$riderId]),
        'enforcement_actions' => fetchAll("SELECT action_code, type, amount, description, action_date, status FROM enforcement_actions WHERE rider_id = ? ORDER BY action_date", [$riderId]),
        'notifications' => fetchAll("SELECT type, message, created_at FROM notifications WHERE rider_id = ? ORDER BY created_at", [$riderId]),
        'requests' => fetchAll("SELECT type, details, status, response, created_at FROM data_requests WHERE user_id = ? ORDER BY created_at", [$uid]),
    ];
    audit_log('EXPORT', 'rider', (string) $riderId, 'Rider downloaded their data');
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bodaerp-my-data-' . date('Y-m-d') . '.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$sessions = fetchAll("SELECT id, ip_address, user_agent, last_activity, created_at FROM login_sessions WHERE user_id = ? AND is_active = 1 AND last_activity > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY last_activity DESC LIMIT 10", [$uid]);
$requests = $riderId ? fetchAll("SELECT type, status, response, created_at FROM data_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5", [$uid]) : [];
$minLen = max(8, setting_int('security_password_min_length', 8));
$smtp = mail_transport_name() === 'smtp';
$smsOn = sms_ready();
$phoneConfirmed = $user['phone_verified_at'] && sms_normalize_phone($user['phone']);
$confirmingPhone = !empty($_SESSION['phone_confirm']) && $_SESSION['phone_confirm']['expires'] > time();
$languages = languages_enabled();
$platformRequires = in_array($me['role'], ['super_admin', 'city_admin', 'government'], true)
    && (int) fetchValue("SELECT require_2fa FROM platform_settings WHERE id = 1") === 1;

portal_start($sidebar, 'my-account', 'My account', [
    'subtitle' => 'Password, notifications and security for ' . ($user['email'] ?: $user['phone']),
    'breadcrumbs' => [role_label($me['role']) => dashboard_url_for($me['role']), 'My account' => null],
]);
?>
<div class="row g-3">
    <div class="col-lg-6">
        <?php if ($me['role'] !== 'rider'): ?>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Profile photo</h2>
            <div class="d-flex align-items-center gap-3 mb-3">
                <?= person_avatar($user['avatar_path'] ?? null, $user['name'], 'is-lg') ?>
                <p class="small text-muted mb-0">Shown in the top bar and beside your name in staff lists, so colleagues recognise who did what. JPG, PNG or WebP, up to 2 MB.</p>
            </div>
            <form method="post" enctype="multipart/form-data" class="mb-2"><?= csrf_field() ?><input type="hidden" name="action" value="photo_upload">
                <label class="form-label" for="profilePhoto">New photo</label>
                <input type="file" id="profilePhoto" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="2" required>
                <button class="btn btn-primary btn-sm mt-2" data-busy-label="Uploading">Save photo</button>
            </form>
            <?php if (!empty($user['avatar_path'])): ?>
            <form method="post" data-confirm="Remove your profile photo? Your initials are shown instead." data-confirm-ok="Remove photo"><?= csrf_field() ?><input type="hidden" name="action" value="photo_remove"><button class="btn btn-outline-danger btn-sm">Remove photo</button></form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Change password</h2>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?><input type="hidden" name="action" value="password">
                <input type="text" name="username" value="<?= h($user['email'] ?: $user['phone']) ?>" autocomplete="username" hidden>
                <div class="mb-2"><label class="form-label small fw-semibold" for="cur">Current password</label><input class="form-control" type="password" id="cur" name="current_password" autocomplete="current-password" required></div>
                <div class="mb-2"><label class="form-label small fw-semibold" for="np">New password</label><input class="form-control" type="password" id="np" name="new_password" minlength="<?= $minLen ?>" autocomplete="new-password" required aria-describedby="npHint"><div class="form-text" id="npHint">At least <?= $minLen ?> characters, with letters and numbers.</div></div>
                <div class="mb-3"><label class="form-label small fw-semibold" for="cp">New password again</label><input class="form-control" type="password" id="cp" name="confirm_password" autocomplete="new-password" required></div>
                <button class="btn btn-primary" data-busy-label="Saving">Change password</button>
            </form>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Email address</h2>
            <?php if (!$user['email']): ?>
            <p class="mb-2">No email address on this account. You sign in with your phone number <?= h($user['phone']) ?>, and password links come by SMS.</p>
            <?php else: ?>
            <p class="mb-2"><?= h($user['email']) ?>
                <?= $user['email_verified_at'] ? '<span class="badge bg-success ms-1">Confirmed</span>' : '<span class="badge bg-warning text-dark ms-1">Not confirmed</span>' ?></p>
            <?php endif; ?>
            <?php if ($user['email'] && !$user['email_verified_at']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify_email"><button class="btn btn-outline-primary btn-sm" data-busy-label="Sending">Send confirmation email</button></form>
            <?php endif; ?>
            <p class="small text-muted mt-2 mb-0">To change your email address, ask <?= $me['role'] === 'rider' ? 'your stage chairperson' : 'the BodaERP administrator' ?>.</p>
        </div></div>

        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Mobile number</h2>
            <?php if ($phoneConfirmed): ?>
                <p class="mb-2">Number ending <?= h(sms_phone_hint($user['phone'])) ?> <span class="badge bg-success ms-1">Confirmed</span></p>
            <?php elseif ($user['phone']): ?>
                <p class="mb-2"><?= h($user['phone']) ?> <span class="badge bg-warning text-dark ms-1">Not confirmed</span></p>
            <?php endif; ?>
            <?php if (!$smsOn): ?>
                <p class="small text-muted mb-0">Text messages are not set up on this site yet, so the number cannot be confirmed.</p>
            <?php elseif ($confirmingPhone): ?>
                <form method="post" class="d-flex gap-2 align-items-end flex-wrap"><?= csrf_field() ?><input type="hidden" name="action" value="phone_confirm">
                    <div><label class="form-label small fw-semibold" for="pcode">Code from the text message</label><input class="form-control" id="pcode" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" data-pattern-message="Enter the 6 digits from the message." maxlength="6" required></div>
                    <button class="btn btn-primary" data-busy-label="Checking">Confirm number</button>
                </form>
            <?php else: ?>
                <form method="post" class="d-flex gap-2 align-items-end flex-wrap"><?= csrf_field() ?><input type="hidden" name="action" value="phone_send">
                    <div class="flex-grow-1"><label class="form-label small fw-semibold" for="pphone"><?= $phoneConfirmed ? 'Use a different number' : 'MTN or Airtel number' ?></label><input class="form-control" id="pphone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0772 123456" value="<?= $phoneConfirmed ? '' : h($user['phone']) ?>" required></div>
                    <button class="btn btn-outline-primary" data-busy-label="Sending">Text me a code</button>
                </form>
            <?php endif; ?>
        </div></div>

        <div class="card" id="notifications"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Notifications</h2>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="notifications">
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="ne" name="notify_email" value="1"<?= (int) $user['notify_email'] ? ' checked' : '' ?>><label class="form-check-label" for="ne">Email me receipts, renewal reminders and account notices</label></div>
                <?php if ($me['role'] === 'rider' || $me['role'] === 'chairperson'): ?>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="ns" name="notify_sms" value="1"<?= (int) $user['notify_sms'] ? ' checked' : '' ?>><label class="form-check-label" for="ns">Text me receipts and renewal reminders</label></div>
                <?php else: ?><input type="hidden" name="notify_sms" value="<?= (int) $user['notify_sms'] ?>"><?php endif; ?>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="ni" name="notify_in_app" value="1"<?= (int) $user['notify_in_app'] ? ' checked' : '' ?>><label class="form-check-label" for="ni">Show notifications in my portal</label></div>
                <?php if ($riderId && count($languages) > 1): ?>
                <div class="mb-3"><label class="form-label small fw-semibold" for="lang">Language for my portal and text messages</label>
                    <select class="form-select" id="lang" name="language"><?php foreach ($languages as $code => $label): ?><option value="<?= h($code) ?>"<?= $user['language'] === $code ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <p class="small text-muted">Security messages (password changes, sign-in codes) are always sent.</p>
                <button class="btn btn-outline-primary btn-sm">Save preferences</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-6">
        <?php if (setting_bool('feature_two_factor', true)): ?>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-2">Two-step sign-in (optional)</h2>
            <p class="small text-muted">After your password, we send a 6-digit code by text message or email. Someone who learns your password still cannot sign in without your phone or inbox.</p>
            <?php if ($platformRequires): ?><p class="small mb-2"><span class="badge bg-info text-dark">Required for your role</span> Your administrator has made codes compulsory for staff accounts. You can still choose where they go.</p><?php endif; ?>
            <?php if (!$smtp && !$smsOn): ?><div class="alert alert-light border small mb-2" data-keep>Not available yet: neither email nor text messages are set up on this site.</div><?php endif; ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="two_factor">
                <fieldset class="mb-3"><legend class="form-label small fw-semibold">Send my codes by</legend>
                    <div class="form-check"><input class="form-check-input" type="radio" name="channel" id="ch-sms" value="sms"<?= $user['two_factor_channel'] === 'sms' ? ' checked' : '' ?><?= $smsOn && $phoneConfirmed ? '' : ' disabled' ?>><label class="form-check-label" for="ch-sms">Text message<?= $phoneConfirmed ? ' to the number ending ' . h(sms_phone_hint($user['phone'])) : ' (confirm your mobile number first)' ?></label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="channel" id="ch-email" value="email"<?= $user['two_factor_channel'] !== 'sms' ? ' checked' : '' ?><?= $smtp && $user['email'] ? '' : ' disabled' ?>><label class="form-check-label" for="ch-email">Email to <?= h($user['email']) ?></label></div>
                </fieldset>
                <?php if ((int) $user['two_factor_enabled'] || $platformRequires): ?>
                    <p class="mb-2"><span class="badge bg-success">On</span><?= $platformRequires && !(int) $user['two_factor_enabled'] ? ' <span class="small text-muted">because your role requires it</span>' : '' ?></p>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary btn-sm" name="enable" value="1">Save choice</button>
                        <?php if (!$platformRequires): ?><button class="btn btn-outline-danger btn-sm" name="enable" value="0">Turn off</button><?php endif; ?>
                    </div>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" name="enable" value="1"<?= $smtp || ($smsOn && $phoneConfirmed) ? '' : ' disabled' ?>>Turn on two-step sign-in</button>
                <?php endif; ?>
            </form>
        </div></div>
        <?php endif; ?>

        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold mb-3">Signed-in devices (last 24 hours)</h2>
            <ul class="list-unstyled small mb-3">
                <?php foreach ($sessions as $s): ?>
                    <li class="d-flex justify-content-between border-bottom py-2"><span><?= h(mb_strimwidth((string) $s['user_agent'], 0, 60, '…')) ?><br><span class="text-muted"><?= h($s['ip_address']) ?></span></span><span class="text-muted text-end"><?= $s['id'] === session_id() ? '<strong class="text-success">This device</strong>' : h(timeAgo($s['last_activity'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" data-confirm="Sign out every other device signed in to your account?" data-confirm-ok="Sign them out"><?= csrf_field() ?><input type="hidden" name="action" value="sign_out_others"><button class="btn btn-outline-secondary btn-sm">Sign out all other devices</button></form>
        </div></div>

        <?php if ($riderId): ?>
        <div class="card" id="privacy"><div class="card-body">
            <h2 class="h6 fw-bold mb-2">Privacy and your data</h2>
            <p class="small text-muted">Under Uganda's Data Protection and Privacy Act you can get a copy of your data, ask for corrections, object to how it is used, or ask for data to be deleted. Read the <a href="<?= BASE_URL ?>/p/privacy-policy">privacy policy</a>.</p>
            <a class="btn btn-outline-primary btn-sm mb-3" href="?download=1">Download my data (JSON)</a>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="data_request">
                <div class="mb-2"><label class="form-label small fw-semibold" for="rtype">Request</label><select class="form-select form-select-sm" id="rtype" name="type"><option value="correction">Correct my details</option><option value="objection">Object to how my data is used</option><option value="deletion">Delete my data</option></select></div>
                <div class="mb-2"><label class="form-label small fw-semibold" for="rdet">Tell us what you need</label><textarea class="form-control form-control-sm" id="rdet" name="details" rows="3" maxlength="3000" required></textarea></div>
                <button class="btn btn-primary btn-sm" data-busy-label="Sending">Send request</button>
            </form>
            <?php if ($requests): ?>
                <h3 class="h6 fw-bold mt-3 small">Your requests</h3>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($requests as $r): ?><li class="border-bottom py-1"><?= h(ucfirst($r['type'])) ?>, <?= h(formatDate($r['created_at'])) ?>: <strong><?= h($r['status']) ?></strong><?= $r['response'] ? ' (' . h($r['response']) . ')' : '' ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php portal_end(); ?>
