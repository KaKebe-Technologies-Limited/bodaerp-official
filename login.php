<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url_for($_SESSION['role']));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Enter email and password.';
    } else {
        $result = attempt_login($email, $password);
        if (is_array($result)) {
            redirect(dashboard_url_for($result['role']));
        }
        $error = $result;
    }
}
$timeout = isset($_GET['timeout']);
$resetDone = isset($_GET['reset']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BodaERP</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <style>
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; position: relative; overflow: hidden; }
        .login-page::before { content: ''; position: absolute; top: -50%; right: -30%; width: 60%; height: 100%; background: linear-gradient(135deg, rgba(13, 110, 253, 0.05), rgba(220, 53, 69, 0.05)); border-radius: 0 0 0 100%; animation: floatBg 8s ease-in-out infinite; }
        .login-page::after { content: ''; position: absolute; bottom: -30%; left: -20%; width: 50%; height: 80%; background: radial-gradient(circle, rgba(13, 110, 253, 0.03), transparent); border-radius: 50%; animation: floatBg 10s ease-in-out infinite reverse; }
        @keyframes floatBg { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(-20px, 20px); } }
        .login-container { width: 100%; max-width: 480px; position: relative; z-index: 1; animation: fadeInUp 0.8s ease forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .login-card { background: white; border-radius: 24px; padding: 40px 36px; box-shadow: 0 20px 60px rgba(0,0,0,0.08); position: relative; overflow: hidden; }
        .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #0d6efd, #dc3545, #0d6efd); background-size: 200% 100%; animation: shimmer 3s ease-in-out infinite; }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .login-card .logo-section { text-align: center; margin-bottom: 28px; }
        .login-card .logo-section .logo-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #0d6efd, #1a3a5c); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 28px; margin-bottom: 12px; box-shadow: 0 8px 25px rgba(13, 110, 253, 0.2); }
        .login-card .logo-section h2 { font-weight: 900; font-size: 1.8rem; margin: 0; letter-spacing: -0.5px; }
        .login-card .logo-section .brand-blue { color: #0d6efd; }
        .login-card .logo-section .brand-red { color: #dc3545; }
        .login-card .logo-section p { color: #6c757d; font-size: 0.85rem; margin: 4px 0 0; }
        .role-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 24px; }
        .role-btn { padding: 12px 6px; border: 2px solid #e9ecef; border-radius: 14px; background: white; text-align: center; cursor: pointer; transition: all 0.3s ease; font-size: 0.6rem; font-weight: 600; color: #6c757d; position: relative; }
        .role-btn i { font-size: 1.4rem; display: block; margin-bottom: 4px; }
        .role-btn .role-label { display: block; font-size: 0.65rem; font-weight: 700; }
        .role-btn .role-badge { font-size: 0.45rem; padding: 1px 6px; border-radius: 10px; display: inline-block; margin-top: 2px; }
        .role-btn:hover { border-color: #0d6efd; background: #f8f9ff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .role-btn.active { border-color: #0d6efd; background: #e6f0ff; color: #0d6efd; box-shadow: 0 4px 16px rgba(13, 110, 253, 0.12); }
        .role-btn.active .role-badge { background: #0d6efd; color: white; }
        .role-btn .check-mark { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; background: #0d6efd; border-radius: 50%; color: white; font-size: 10px; display: none; align-items: center; justify-content: center; }
        .role-btn.active .check-mark { display: flex; }
        .role-btn.role-super i { color: #0d6efd; }
        .role-btn.role-super.active { border-color: #0d6efd; background: #e6f0ff; color: #0d6efd; }
        .role-btn.role-super .role-badge { background: #0d6efd; color: white; }
        .role-btn.role-stage i { color: #f59e0b; }
        .role-btn.role-stage.active { border-color: #f59e0b; background: #fffbeb; color: #f59e0b; }
        .role-btn.role-stage.active .role-badge { background: #f59e0b; color: white; }
        .role-btn.role-city i { color: #198754; }
        .role-btn.role-city.active { border-color: #198754; background: #ebf9f1; color: #198754; }
        .role-btn.role-city.active .role-badge { background: #198754; color: white; }
        .role-btn.role-rider i { color: #0dcaf0; }
        .role-btn.role-rider.active { border-color: #0dcaf0; background: #e6f9fc; color: #0dcaf0; }
        .role-btn.role-rider.active .role-badge { background: #0dcaf0; color: white; }
        .form-group { margin-bottom: 16px; }
        .form-group .input-group { border-radius: 12px; overflow: hidden; border: 2px solid #e9ecef; transition: all 0.3s ease; }
        .form-group .input-group:focus-within { border-color: #0d6efd; box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.08); }
        .form-group .input-group-text { background: transparent; border: none; color: #6c757d; padding: 0 0 0 16px; }
        .form-group .form-control { border: none; padding: 12px 16px; font-size: 0.95rem; background: transparent; }
        .form-group .form-control:focus { box-shadow: none; }
        .form-options { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .form-options .form-check { margin: 0; }
        .form-options a { color: #0d6efd; text-decoration: none; font-weight: 500; font-size: 0.85rem; }
        .btn-login { width: 100%; padding: 14px; font-weight: 700; font-size: 1rem; border-radius: 12px; background: linear-gradient(135deg, #0d6efd, #0a58ca); border: none; color: white; transition: all 0.3s ease; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3); color: white; }
        .demo-credentials { margin-top: 16px; padding: 12px 16px; background: #f8f9fa; border-radius: 12px; font-size: 0.7rem; max-height: 180px; overflow-y: auto; }
        .demo-credentials .demo-title { font-weight: 600; color: #6c757d; margin-bottom: 4px; }
        .demo-credentials .demo-row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 0.7rem; }
        .demo-credentials .demo-row .role-name { font-weight: 600; }
        .demo-credentials .demo-row .role-name.super { color: #0d6efd; }
        .demo-credentials .demo-row .role-name.stage { color: #f59e0b; }
        .demo-credentials .demo-row .role-name.city { color: #198754; }
        .demo-credentials .demo-row .role-name.rider { color: #0dcaf0; }
        .demo-credentials code { background: white; padding: 1px 8px; border-radius: 4px; font-weight: 600; font-size: 0.65rem; }
        .login-footer { text-align: center; margin-top: 16px; color: #6c757d; font-size: 0.7rem; }
        .login-footer a { color: #0d6efd; text-decoration: none; }
        @media (max-width: 576px) { .login-card { padding: 28px 20px; } .role-selector { grid-template-columns: repeat(2, 1fr); gap: 6px; } }
    </style>
</head>
<body>

    <div class="login-page">
        <div class="login-container">
            <div class="login-card">

                <a href="<?= BASE_URL ?>/index.php" class="logo-section" style="display:block; text-decoration:none; color:inherit; cursor:pointer;">
                    <div class="logo-icon"><i class="fas fa-motorcycle"></i></div>
                    <h2><span class="brand-blue">Boda</span><span class="brand-red">ERP</span></h2>
                    <p>Enterprise Boda Management System</p>
                </a>

                <?php if ($timeout): ?>
                    <div class="alert alert-warning py-2 small">Your session timed out. Please sign in again.</div>
                <?php endif; ?>
                <?php if ($resetDone): ?>
                    <div class="alert alert-success py-2 small">Your password has been reset. Sign in with your new password.</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
                <?php endif; ?>

                <label class="form-label fw-semibold mb-2 small text-muted">Select Your Role</label>
                <div class="role-selector" id="roleSelector">
                    <div class="role-btn role-super active" data-role="super">
                        <i class="fas fa-user-shield"></i><span class="role-label">Super Admin</span><span class="role-badge">Full Access</span><span class="check-mark"><i class="fas fa-check"></i></span>
                    </div>
                    <div class="role-btn role-stage" data-role="stage">
                        <i class="fas fa-users-cog"></i><span class="role-label">Stage</span><span class="role-badge">Chairperson</span><span class="check-mark"><i class="fas fa-check"></i></span>
                    </div>
                    <div class="role-btn role-city" data-role="city">
                        <i class="fas fa-building"></i><span class="role-label">City</span><span class="role-badge">Council</span><span class="check-mark"><i class="fas fa-check"></i></span>
                    </div>
                    <div class="role-btn role-rider" data-role="rider">
                        <i class="fas fa-motorcycle"></i><span class="role-label">Rider</span><span class="role-badge">Operator</span><span class="check-mark"><i class="fas fa-check"></i></span>
                    </div>
                </div>

                <form id="loginForm" method="post" action="<?= BASE_URL ?>/login.php">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" value="admin@bodaerp.com" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter Password" value="admin123" required>
                            <button type="button" class="btn btn-outline-secondary border-0" onclick="togglePassword()">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-options">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe">
                            <label class="form-check-label small" for="rememberMe">Remember me</label>
                        </div>
                        <a href="<?= BASE_URL ?>/forgot-password.php">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>

                <div class="demo-credentials">
                    <div class="demo-title">🔑 Demo Credentials — every city can log in</div>
                    <div class="demo-row"><span><span class="role-name super">● Super Admin</span></span><code>admin@bodaerp.com / admin123</code></div>
                    <div class="demo-row"><span><span class="role-name city">● City (Lira)</span></span><code>council@liracityuganda.go.ug / lira2025</code></div>
                    <div class="demo-row"><span><span class="role-name city">● City (Gulu)</span></span><code>council@gulucity.go.ug / gulu2025</code></div>
                    <div class="demo-row"><span><span class="role-name city">● City (Kampala)</span></span><code>council@kcca.go.ug / kampala2025</code></div>
                    <div class="demo-row"><span><span class="role-name city">● City (Mbarara)</span></span><code>council@mbararacity.go.ug / mbarara2025</code></div>
                    <div class="demo-row"><span><span class="role-name stage">● Chairperson (Lira)</span></span><code>ocen@railway.lira.ug / chair123</code></div>
                    <div class="demo-row"><span><span class="role-name stage">● Chairperson (Gulu)</span></span><code>komakech@gulumain.gulu.ug / chair123</code></div>
                    <div class="demo-row"><span><span class="role-name stage">● Chairperson (Kampala)</span></span><code>nakato@nakasero.kla.ug / chair123</code></div>
                    <div class="demo-row"><span><span class="role-name stage">● Chairperson (Mbarara)</span></span><code>tumwine@mbararamain.mba.ug / chair123</code></div>
                    <div class="demo-row"><span><span class="role-name rider">● Rider (Lira)</span></span><code>akello@bodaerp.com / rider123</code></div>
                    <div class="demo-row"><span><span class="role-name rider">● Rider (Gulu)</span></span><code>aciro@bodaerp.com / rider123</code></div>
                    <div class="demo-row"><span><span class="role-name rider">● Rider (Kampala)</span></span><code>mukasa@bodaerp.com / rider123</code></div>
                    <div class="demo-row"><span><span class="role-name rider">● Rider (Mbarara)</span></span><code>kyomuhendo@bodaerp.com / rider123</code></div>
                </div>

                <div class="login-footer">&copy; 2026 <a href="<?= BASE_URL ?>/index.php">Kakebe Technologies Limited</a>. All rights reserved.</div>

            </div>
        </div>
    </div>

    <script>
        const roleBtns = document.querySelectorAll('.role-btn');
        roleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                roleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const hints = {
                    'super': { email: 'admin@bodaerp.com', pass: 'admin123' },
                    'city':  { email: 'council@liracityuganda.go.ug', pass: 'lira2025' },
                    'stage': { email: 'ocen@railway.lira.ug', pass: 'chair123' },
                    'rider': { email: 'akello@bodaerp.com', pass: 'rider123' },
                };
                const hint = hints[this.dataset.role];
                if (hint) {
                    document.getElementById('email').value = hint.email;
                    document.getElementById('password').value = hint.pass;
                }
                const colors = {
                    'super': 'linear-gradient(135deg, #0d6efd, #0a58ca)',
                    'stage': 'linear-gradient(135deg, #f59e0b, #d97706)',
                    'city':  'linear-gradient(135deg, #198754, #0d6e3e)',
                    'rider': 'linear-gradient(135deg, #0dcaf0, #0a9bb8)'
                };
                document.getElementById('loginBtn').style.background = colors[this.dataset.role] || colors['super'];
            });
        });

        function togglePassword() {
            const inp = document.getElementById('password');
            const ico = document.getElementById('togglePasswordIcon');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            ico.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        }

        document.addEventListener('keydown', e => {
            const map = {'1':'super','2':'stage','3':'city','4':'rider'};
            if (map[e.key]) document.querySelector(`.role-btn[data-role="${map[e.key]}"]`)?.click();
        });
    </script>
</body>
</html>
