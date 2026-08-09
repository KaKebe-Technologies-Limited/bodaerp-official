<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url_for($_SESSION['role']));
}

$error = null;
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$user = $token !== ''
    ? fetchOne("SELECT id, name FROM users WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active' AND deleted_at IS NULL", [$token])
    : null;

if ($token !== '' && !$user) {
    $error = 'This reset link is invalid or has expired.';
}

$success = false;
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        runQuery("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        audit_log('UPDATE', 'user', (string) $user['id'], 'Password reset completed');
        redirect('/login.php?reset=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BodaERP</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <style>
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; position: relative; overflow: hidden; }
        .login-page::before { content: ''; position: absolute; top: -50%; right: -30%; width: 60%; height: 100%; background: linear-gradient(135deg, rgba(13, 110, 253, 0.05), rgba(220, 53, 69, 0.05)); border-radius: 0 0 0 100%; }
        .login-container { width: 100%; max-width: 480px; position: relative; z-index: 1; }
        .login-card { background: white; border-radius: 24px; padding: 40px 36px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
        .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0d6efd, #dc3545, #0d6efd); }
        .login-card .logo-section { text-align: center; margin-bottom: 28px; }
        .login-card .logo-section .logo-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #0d6efd, #1a3a5c); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 28px; margin-bottom: 12px; box-shadow: 0 8px 25px rgba(13, 110, 253, 0.2); }
        .login-card .logo-section h2 { font-weight: 900; font-size: 1.8rem; margin: 0; letter-spacing: -0.5px; }
        .login-card .logo-section .brand-blue { color: #0d6efd; }
        .login-card .logo-section .brand-red { color: #dc3545; }
        .login-card .logo-section p { color: #6c757d; font-size: 0.85rem; margin: 4px 0 0; }
        .form-group { margin-bottom: 16px; }
        .form-group .input-group { border-radius: 12px; overflow: hidden; border: 2px solid #e9ecef; }
        .form-group .input-group:focus-within { border-color: #0d6efd; box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.08); }
        .form-group .input-group-text { background: transparent; border: none; color: #6c757d; padding: 0 0 0 16px; }
        .form-group .form-control { border: none; padding: 12px 16px; font-size: 0.95rem; background: transparent; }
        .form-group .form-control:focus { box-shadow: none; }
        .btn-login { width: 100%; padding: 14px; font-weight: 700; font-size: 1rem; border-radius: 12px; background: linear-gradient(135deg, #0d6efd, #0a58ca); border: none; color: white; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3); color: white; }
        .login-footer { text-align: center; margin-top: 16px; color: #6c757d; font-size: 0.7rem; }
        .login-footer a { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>

    <div class="login-page">
        <div class="login-container">
            <div class="login-card">

                <a href="<?= BASE_URL ?>/index.php" class="logo-section" style="display:block; text-decoration:none; color:inherit;">
                    <div class="logo-icon"><i class="fas fa-lock"></i></div>
                    <h2><span class="brand-blue">Boda</span><span class="brand-red">ERP</span></h2>
                    <p>Set a new password</p>
                </a>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
                <?php endif; ?>

                <?php if ($user): ?>
                    <p class="text-muted small mb-3">Hi <?= h($user['name']) ?>, choose a new password (at least 8 characters).</p>
                    <form method="post" action="<?= BASE_URL ?>/reset-password.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= h($token) ?>">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="New password" minlength="8" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password_confirm" placeholder="Confirm new password" minlength="8" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-login"><i class="fas fa-check me-2"></i>Set New Password</button>
                    </form>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-login"><i class="fas fa-redo me-2"></i>Request a New Link</a>
                <?php endif; ?>

                <div class="login-footer">
                    <a href="<?= BASE_URL ?>/login.php">Back to Sign In</a> &middot; &copy; 2026 <a href="<?= BASE_URL ?>/index.php">Kakebe Technologies Limited</a>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
