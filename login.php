<?php
require_once '/var/www/html/packages/backend/auth.php';

// Already logged in — go to dashboard
if (is_logged_in()) {
    redirect(dashboard_url_for($_SESSION['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $result = attempt_login($email, $password);
        if (is_array($result)) {
            redirect(dashboard_url_for($result['role']));
        } else {
            $error = $result;
        }
    }
}

$timeout = !empty($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — BodaERP</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
</head>
<body class="login-body">

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP" class="login-logo">
            <h2 class="login-title">Boda<span class="text-gradient-red">ERP</span></h2>
            <p class="login-subtitle">Boda Boda Management Platform</p>
        </div>

        <?php if ($timeout): ?>
            <div class="alert alert-warning"><i class="fas fa-clock me-2"></i>Your session expired. Please sign in again.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="login-form">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" name="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="you@example.com" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Your password" required>
                    <button type="button" class="btn btn-outline-secondary toggle-password"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <div class="login-footer">
            <small class="text-muted">© <?= date('Y') ?> Kakebe Technologies Limited</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = this.closest('.input-group').querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
</script>
</body>
</html>
