<?php
// Second step of two-step sign-in: the 6-digit code emailed by attempt_login().
require_once __DIR__ . '/packages/backend/site.php';

if (is_logged_in()) redirect(dashboard_url_for($_SESSION['role']));
$pending = $_SESSION['pending_2fa'] ?? null;
if (!$pending) redirect('/login.php');

$error = null;
$resent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['resend'])) {
        $user = fetchOne("SELECT id, name, email FROM users WHERE id = ? AND status = 'active'", [$pending['user_id']]);
        if ($user && rate_limit('2fa-resend:' . $user['id'], 3, 900)) {
            $code = (string) random_int(100000, 999999);
            $_SESSION['pending_2fa']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
            $_SESSION['pending_2fa']['expires'] = time() + 600;
            $_SESSION['pending_2fa']['tries'] = 0;
            send_template_email('two_factor_code', $user['email'], ['name' => $user['name'], 'code' => $code]);
            $resent = true;
        } else {
            $error = 'A new code was sent recently. Check your email, or wait 15 minutes.';
        }
    } else {
        $remember = !empty($pending['remember']);
        $next = $pending['next'] ?? null;
        $r = verify_login_code(preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')));
        if ($r['ok']) {
            if ($remember) remember_me_issue((int) $r['user']['id']);
            redirect($next ?? dashboard_url_for($r['user']['role']));
        }
        if (!empty($r['restart'])) { flash('error', $r['error']); redirect('/login.php'); }
        $error = $r['error'];
    }
}
$email = (string) fetchValue("SELECT email FROM users WHERE id = ?", [$pending['user_id']]);
$masked = preg_replace('/(?<=.).(?=[^@]*@)/', '•', $email);
$seo = ['title' => 'Enter your sign-in code | ' . get_setting('site_name', 'BodaERP'), 'noindex' => true];
require __DIR__ . '/packages/frontend/partials/auth-start.php';
?>
            <h1>Check your email</h1>
            <p class="sub">We sent a 6-digit sign-in code to <strong><?= h($masked) ?></strong>. It works for 10 minutes.</p>
            <?php if ($resent): ?><div class="notice notice-success" role="status">A new code is on its way.</div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
            <form method="post" data-loading="Checking">
                <?= csrf_field() ?>
                <div class="login-field">
                    <label for="code">Sign-in code</label>
                    <div class="input-box code">
                        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-shield-halved" aria-hidden="true"></i> Confirm and sign in</button>
            </form>
            <form method="post" class="auth-links">
                <?= csrf_field() ?>
                <button type="submit" name="resend" value="1" class="btn-plain">Send a new code</button>
                · <a href="<?= BASE_URL ?>/login.php">Use a different account</a>
            </form>
<?php require __DIR__ . '/packages/frontend/partials/auth-end.php';
