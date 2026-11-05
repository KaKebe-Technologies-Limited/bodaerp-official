<?php
// Confirms an email address from the link in the "Confirm email address" email.
require_once __DIR__ . '/packages/backend/site.php';

$row = user_token_check(trim((string) ($_GET['token'] ?? '')), 'email_verify');
if ($row && user_token_consume((int) $row['id'])) {
    runQuery("UPDATE users SET email_verified_at = NOW() WHERE id = ?", [$row['user_id']]);
    runQuery("INSERT INTO audit_logs (user_id, user_name_snapshot, action, entity_type, entity_id, details, ip_address)
              SELECT id, name, 'EMAIL_VERIFIED', 'user', id, 'Email address confirmed', ? FROM users WHERE id = ?", [client_ip(), $row['user_id']]);
    if (is_logged_in()) { flash('success', 'Thank you, your email address is confirmed.'); redirect(dashboard_url_for($_SESSION['role'])); }
    redirect('/login.php?verified=1');
}
$seo = ['title' => 'Email confirmation | ' . get_setting('site_name', 'BodaERP'), 'noindex' => true];
require __DIR__ . '/packages/frontend/partials/auth-start.php';
?>
            <h1>This confirmation link has expired</h1>
            <p class="sub">Confirmation links work once, for 48 hours. Sign in and use "Send confirmation email" on your account page to get a new one.</p>
            <a class="btn-login" href="<?= BASE_URL ?>/login.php"><i class="fas fa-right-to-bracket" aria-hidden="true"></i> Sign in</a>
<?php require __DIR__ . '/packages/frontend/partials/auth-end.php';
