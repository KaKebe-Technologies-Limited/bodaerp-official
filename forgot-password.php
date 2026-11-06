<?php
// Password reset request. Always answers the same way, so it cannot be used to find out which emails have accounts.
// The link is emailed (SMTP, or storage/mail/ on a machine without a mail server). It is shown on screen only on a
// developer's own computer, where there may be no way to read email.
require_once __DIR__ . '/packages/backend/site.php';

if (is_logged_in()) redirect(dashboard_url_for($_SESSION['role']));

$error = null;
$done = false;
$devLink = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = login_phone($email);
    if ($phone === null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address or phone number on your account.';
    } elseif (!rate_limit('reset-ip:' . client_ip(), 10, 3600) || !rate_limit('reset-email:' . ($phone ?? $email), 3, 3600)) {
        $error = 'Several reset links were requested recently. Check your inbox (and spam folder), or try again in an hour.';
    } else {
        $done = true;
        $user = login_find_user($email);
        if ($user) {
            $token = user_token_create((int) $user['id'], 'password_reset', 1800);
            $url = app_url('reset-password.php?token=' . $token);
            // A rider who typed their phone number gets the link by SMS; everyone else by email.
            if ($phone !== null) send_template_sms('password_reset', $phone, ['url' => $url]);
            elseif ($user['email']) send_template_email('password_reset', $user['email'], ['name' => $user['name'], 'reset_url' => $url, 'ip_address' => client_ip()]);
            runQuery("INSERT INTO audit_logs (user_id, user_name_snapshot, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?,?)",
                [$user['id'], $user['name'], 'PASSWORD_RESET_REQUESTED', 'user', (string) $user['id'], $phone !== null ? 'Reset link sent by SMS' : 'Reset link emailed', client_ip()]);
            if (db_is_dev_machine()) $devLink = $url;
        }
    }
}
$seo = ['title' => 'Reset your password | ' . get_setting('site_name', 'BodaERP'), 'noindex' => true];
require __DIR__ . '/packages/frontend/partials/auth-start.php';
?>
            <h1>Reset your password</h1>
            <?php if ($done): ?>
                <div class="notice notice-success" role="status"><?= h(get_setting('msg_reset_requested', 'If that email or phone number belongs to an account, a reset link is on its way.')) ?></div>
                <?php if ($devLink): ?>
                    <div class="notice notice-info">Developer machine only: <a href="<?= h($devLink) ?>">open the reset link</a>.</div>
                <?php endif; ?>
                <p class="sub">Nothing after a few minutes? Check your spam folder, or ask your stage chairperson or city office to send you a new link.</p>
                <a class="btn-login" href="<?= BASE_URL ?>/login.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to sign in</a>
            <?php else: ?>
                <p class="sub">Enter the email address on your account, or the phone number if you are a rider without email. We send a link to choose a new password.</p>
                <?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
                <form method="post" data-loading="Sending link">
                    <?= csrf_field() ?>
                    <div class="login-field">
                        <label for="email">Email address or phone number</label>
                        <div class="input-box">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <input type="text" id="email" name="email" value="<?= h($email) ?>" placeholder="name@bodaerp.site or 0772 123456" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn-login"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send me a reset link</button>
                </form>
                <p class="auth-links"><a href="<?= BASE_URL ?>/login.php">Back to sign in</a></p>
            <?php endif; ?>
<?php require __DIR__ . '/packages/frontend/partials/auth-end.php';
