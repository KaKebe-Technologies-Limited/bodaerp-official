<?php
// Choose a new password from an emailed link: "forgot password" (30 minutes) or a new account's
// "set your password" link (48 hours, ?welcome=1). Using the link also confirms the email address.
require_once __DIR__ . '/packages/backend/site.php';

if (is_logged_in()) redirect(dashboard_url_for($_SESSION['role']));

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$welcome = !empty($_GET['welcome']) || !empty($_POST['welcome']);
$row = $token !== '' ? user_token_check($token, 'password_reset') : null;
$user = $row ? fetchOne("SELECT id, name, email, phone FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$row['user_id']]) : null;

$errors = [];
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = (string) ($_POST['password'] ?? '');
    $errors = password_problems($password, (string) $user['email'], $user['name']);
    if (!$errors && !hash_equals($password, (string) ($_POST['password_confirm'] ?? ''))) $errors[] = 'The two passwords do not match.';
    if (!$errors) {
        if (!user_token_consume((int) $row['id'])) {
            $errors[] = 'This link has just been used. Request a new one.';
        } else {
            set_user_password((int) $user['id'], $password);
            if ($user['email']) runQuery("UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?", [$user['id']]);
            runQuery("INSERT INTO audit_logs (user_id, user_name_snapshot, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?,?)",
                [$user['id'], $user['name'], 'PASSWORD_RESET', 'user', (string) $user['id'], $welcome ? 'Password set from welcome link' : 'Password reset completed', client_ip()]);
            if ($user['email']) send_template_email('password_changed', $user['email'], ['name' => $user['name'], 'changed_at' => date('j M Y, H:i')]);
            redirect('/login.php?reset=1');
        }
    }
}
$minLen = max(8, setting_int('security_password_min_length', 8));
$seo = ['title' => ($welcome ? 'Set your password' : 'Choose a new password') . ' | ' . get_setting('site_name', 'BodaERP'), 'noindex' => true];
require __DIR__ . '/packages/frontend/partials/auth-start.php';
?>
            <?php if (!$user): ?>
                <h1>This link has expired</h1>
                <p class="sub">Password links work once, for a limited time. Request a new one and use it straight away.</p>
                <a class="btn-login" href="<?= BASE_URL ?>/forgot-password.php"><i class="fas fa-rotate" aria-hidden="true"></i> Request a new link</a>
                <p class="auth-links"><a href="<?= BASE_URL ?>/login.php">Back to sign in</a></p>
            <?php else: ?>
                <h1><?= $welcome ? 'Welcome, ' . h(strtok($user['name'], ' ')) : 'Choose a new password' ?></h1>
                <p class="sub"><?= $welcome ? 'Set a password for' : 'For' ?> <strong><?= h($user['email'] ?: ('the phone ending ' . substr((string) $user['phone'], -3))) ?></strong>.</p>
                <?php if ($errors): ?><div class="notice notice-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
                <form method="post" data-loading="Saving">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <?php if ($welcome): ?><input type="hidden" name="welcome" value="1"><?php endif; ?>
                    <div class="login-field">
                        <label for="password">New password</label>
                        <div class="input-box">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" minlength="<?= $minLen ?>" autocomplete="new-password" required autofocus aria-describedby="pwHint">
                            <button type="button" class="reveal" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        </div>
                        <div class="strength" data-strength-for="password" aria-hidden="true"><span></span></div>
                        <div class="field-hint" id="pwHint">At least <?= $minLen ?> characters, with letters and numbers.</div>
                    </div>
                    <div class="login-field">
                        <label for="password_confirm">Type it again</label>
                        <div class="input-box">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-login"><i class="fas fa-check" aria-hidden="true"></i> Save password</button>
                </form>
            <?php endif; ?>
<?php require __DIR__ . '/packages/frontend/partials/auth-end.php';
