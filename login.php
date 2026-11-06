<?php
// One sign-in page for every role; the account's role decides which portal opens.
require_once __DIR__ . '/packages/backend/site.php';

if (is_logged_in()) redirect(dashboard_url_for($_SESSION['role']));

$error = null;
$email = '';
$next = safe_next_path($_POST['next'] ?? $_GET['next'] ?? null);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Enter your email address or phone number, and your password.';
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok'] && !empty($result['needs_code'])) {
            $_SESSION['pending_2fa']['remember'] = !empty($_POST['remember']) && setting_bool('feature_remember_me', true);
            $_SESSION['pending_2fa']['next'] = $next;
            redirect('/login-code.php');
        }
        if ($result['ok']) {
            if (!empty($_POST['remember']) && setting_bool('feature_remember_me', true)) remember_me_issue((int) $result['user']['id']);
            redirect($next ?? dashboard_url_for($result['user']['role']));
        }
        $error = $result['error'];
    }
}

$notice = match (true) {
    isset($_GET['timeout']) => ['info', get_setting('msg_session_expired', 'You were signed out after a period without activity.')],
    isset($_GET['revoked']) => ['info', 'You were signed out because your password was changed or an administrator ended your session.'],
    isset($_GET['reset'])   => ['success', get_setting('msg_reset_done', 'Your password has been changed. Sign in with the new one.')],
    isset($_GET['verified']) => ['success', 'Thank you, your email address is confirmed.'],
    default => null,
};
// Demo passwords appear only on a developer's own machine, or with SHOW_DEMO_LOGINS=1 (the Docker dev stack).
$showDemoLogins = db_is_dev_machine() || env('SHOW_DEMO_LOGINS') === '1';
// Role buttons fill the first account of their role; every row of the demo list fills its own account.
$demoRoles = [
    'super' => ['Super Admin', 'Full access', 'fa-user-shield'],
    'stage' => ['Stage', 'Chairperson', 'fa-users-gear'],
    'city'  => ['City', 'Council', 'fa-building-columns'],
    'rider' => ['Rider', 'Operator', 'fa-motorcycle'],
    'gov'   => ['Government', 'National', 'fa-landmark'],
];
$demo = [
    ['super', 'Super Admin', 'admin@bodaerp.site', 'admin123'],
    ['city', 'City (Lira)', 'lira@bodaerp.site', 'lira2025'],
    ['city', 'City (Gulu)', 'gulu@bodaerp.site', 'gulu2025'],
    ['city', 'City (Kampala)', 'kampala@bodaerp.site', 'kampala2025'],
    ['city', 'City (Mbarara)', 'mbarara@bodaerp.site', 'mbarara2025'],
    ['stage', 'Chairperson (Lira)', 'ocen@bodaerp.site', 'chair123'],
    ['stage', 'Chairperson (Gulu)', 'komakech@bodaerp.site', 'chair123'],
    ['stage', 'Chairperson (Kampala)', 'nakato@bodaerp.site', 'chair123'],
    ['stage', 'Chairperson (Mbarara)', 'tumwine@bodaerp.site', 'chair123'],
    ['rider', 'Rider (Lira)', 'akello@bodaerp.site', 'rider123'],
    ['rider', 'Rider (Gulu)', 'aciro@bodaerp.site', 'rider123'],
    ['rider', 'Rider (Kampala)', 'mukasa@bodaerp.site', 'rider123'],
    ['rider', 'Rider (Mbarara)', 'kyomuhendo@bodaerp.site', 'rider123'],
    ['gov', 'Government (Works & Transport)', 'works@bodaerp.site', 'Gov@2026'],
    ['gov', 'Government (URA)', 'ura@bodaerp.site', 'Gov@2026'],
    ['gov', 'Government (Local Govt.)', 'localgov@bodaerp.site', 'Gov@2026'],
    ['gov', 'Government (ICT)', 'ict@bodaerp.site', 'Gov@2026'],
];
$firstOfRole = [];
foreach ($demo as [$r, , $de, $dp]) $firstOfRole[$r] ??= [$de, $dp];

$seo = ['title' => 'Sign in | ' . get_setting('site_name', 'BodaERP'),
    'description' => 'Sign in to BodaERP: city council staff, stage chairpersons, riders and government officers.'];
require __DIR__ . '/packages/frontend/partials/auth-start.php';
?>
            <h1><?= h(get_setting('login_heading', 'Sign in')) ?></h1>
            <?php if ($sub = get_setting('login_subheading', '')): ?><p class="sub"><?= h($sub) ?></p><?php endif; ?>

            <?php if ($notice): ?><div class="notice notice-<?= $notice[0] ?>" role="status"><?= h($notice[1]) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice notice-error" role="alert"><?= h($error) ?></div><?php endif; ?>
            <?php foreach (flash_take() as [$ft, $fm]): ?><div class="notice notice-<?= $ft === 'error' ? 'error' : 'success' ?>" role="status"><?= h($fm) ?></div><?php endforeach; ?>

            <?php if ($showDemoLogins): ?>
                <span class="role-label-text" id="roleLabel">Select your role</span>
                <div class="role-selector" role="group" aria-labelledby="roleLabel" data-role-selector>
                    <?php foreach ($demoRoles as $rk => [$rName, $rBadge, $rIcon]): [$re, $rp] = $firstOfRole[$rk]; ?>
                        <button type="button" class="role-btn r-<?= $rk ?>" aria-pressed="false" data-demo-role="<?= $rk ?>" data-demo-email="<?= h($re) ?>" data-demo-pass="<?= h($rp) ?>">
                            <i class="fas <?= $rIcon ?>" aria-hidden="true"></i><span class="role-name"><?= h($rName) ?></span><span class="role-badge"><?= h($rBadge) ?></span>
                            <span class="check" aria-hidden="true"><i class="fas fa-check"></i></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>/login.php" data-loading="Signing in">
                <?= csrf_field() ?>
                <?php if ($next): ?><input type="hidden" name="next" value="<?= h(BASE_URL . $next) ?>"><?php endif; ?>
                <div class="login-field">
                    <label for="email">Email address or phone number</label>
                    <div class="input-box">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <input type="text" id="email" name="email" value="<?= h($email) ?>" placeholder="name@bodaerp.site or 0772 123456" autocomplete="username" inputmode="email" required autofocus>
                    </div>
                </div>
                <div class="login-field">
                    <label for="password">Password</label>
                    <div class="input-box">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="reveal" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="form-options">
                    <?php if (setting_bool('feature_remember_me', true)): ?>
                        <label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                    <?php else: ?><span></span><?php endif; ?>
                    <a href="<?= BASE_URL ?>/forgot-password.php">Forgot password?</a>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-right-to-bracket" aria-hidden="true"></i> Sign in</button>
            </form>
            <?php if ($help = get_setting('login_help_text', '')): ?><p class="auth-help"><?= h($help) ?></p><?php endif; ?>

            <?php if ($showDemoLogins): ?>
                <div class="demo-credentials">
                    <div class="demo-title"><i class="fas fa-key" aria-hidden="true"></i> Demo accounts (this computer only, never on a live server)</div>
                    <?php foreach ($demo as [$rk, $label, $de, $dp]): ?>
                        <button type="button" class="demo-row r-<?= $rk ?>" data-demo-role="<?= $rk ?>" data-demo-email="<?= h($de) ?>" data-demo-pass="<?= h($dp) ?>">
                            <span class="who"><?= h($label) ?></span><code><?= h($de) ?> / <?= h($dp) ?></code>
                        </button>
                    <?php endforeach; ?>
                    <div class="demo-keys">Keys 1 to 5 pick a role when you are not typing in a field.</div>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/packages/frontend/partials/auth-end.php';
