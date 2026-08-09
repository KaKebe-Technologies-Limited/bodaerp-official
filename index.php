<?php
require_once __DIR__ . '/includes/functions.php';

$totalRiders    = (int) fetchValue("SELECT COUNT(*) FROM riders");
$activeRiders   = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE status = 'active'");
$totalRevenue   = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Confirmed'");
$complianceRate = $totalRiders ? round(100 * $activeRiders / $totalRiders) : 0;
$revenueDisplay = $totalRevenue >= 1000000 ? 'UGX ' . round($totalRevenue / 1000000, 1) . 'M' : formatCurrency($totalRevenue);
?>
<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BodaERP - Enterprise Boda Management System</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <style>
        :root { --primary-blue: #0d6efd; --primary-blue-dark: #0a58ca; --secondary-red: #dc3545; --dark: #1a1a2e; --dark-light: #2d2d3f; --white: #ffffff; --off-white: #f8f9fa; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; overflow-x: hidden; background: var(--white); }
        .navbar-custom { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); box-shadow: 0 2px 30px rgba(0, 0, 0, 0.06); padding: 16px 0; transition: all 0.3s ease; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .navbar-custom.scrolled { background: rgba(255, 255, 255, 0.98); box-shadow: 0 4px 40px rgba(0, 0, 0, 0.08); }
        .navbar-custom .brand { font-size: 1.5rem; font-weight: 900; text-decoration: none; letter-spacing: -0.5px; }
        .navbar-custom .brand .blue { color: var(--primary-blue); }
        .navbar-custom .brand .red { color: var(--secondary-red); }
        .navbar-custom .brand small { font-size: 0.6rem; font-weight: 400; color: #6c757d; letter-spacing: 2px; display: block; margin-top: -4px; }
        .btn-login-nav { background: var(--primary-blue); color: white; padding: 10px 28px; border-radius: 50px; font-weight: 600; font-size: 0.9rem; border: none; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-login-nav:hover { background: var(--primary-blue-dark); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(13, 110, 253, 0.3); color: white; }
        .hero-section { min-height: 100vh; display: flex; align-items: center; padding: 120px 0 80px; position: relative; overflow: hidden; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
        .hero-section .bg-pattern { position: absolute; top: -50%; right: -20%; width: 60%; height: 120%; background: linear-gradient(135deg, rgba(13, 110, 253, 0.04), rgba(220, 53, 69, 0.03)); border-radius: 50%; pointer-events: none; }
        .hero-section .bg-pattern-2 { position: absolute; bottom: -30%; left: -10%; width: 40%; height: 80%; background: radial-gradient(circle, rgba(13, 110, 253, 0.03), transparent); border-radius: 50%; pointer-events: none; }
        .hero-content { position: relative; z-index: 1; }
        .hero-content .badge-hero { background: rgba(13, 110, 253, 0.1); color: var(--primary-blue); padding: 6px 18px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: inline-block; margin-bottom: 20px; border: 1px solid rgba(13, 110, 253, 0.15); animation: pulse 2s ease-in-out infinite; }
        .hero-content h1 { font-size: 3.5rem; font-weight: 900; line-height: 1.1; margin-bottom: 20px; color: var(--dark); }
        .hero-content h1 .highlight-blue { color: var(--primary-blue); position: relative; }
        .hero-content h1 .highlight-red { color: var(--secondary-red); }
        .hero-content p { font-size: 1.15rem; color: #6c757d; max-width: 520px; line-height: 1.8; margin-bottom: 30px; }
        .hero-content .btn-hero { padding: 14px 40px; font-size: 1.05rem; font-weight: 700; border-radius: 50px; background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark)); border: none; color: white; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .hero-content .btn-hero:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(13, 110, 253, 0.35); color: white; }
        .hero-content .btn-hero-outline { padding: 14px 36px; font-size: 1.05rem; font-weight: 600; border-radius: 50px; border: 2px solid var(--dark); background: transparent; color: var(--dark); transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .hero-content .btn-hero-outline:hover { background: var(--dark); color: white; transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); }
        .hero-illustration { position: relative; z-index: 1; }
        .hero-illustration .illustration-box { background: white; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(13, 110, 253, 0.08); border: 1px solid rgba(13, 110, 253, 0.06); animation: float 6s ease-in-out infinite; }
        .hero-illustration .illustration-box .icon-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .hero-illustration .illustration-box .icon-grid .icon-item { background: var(--off-white); padding: 20px; border-radius: 16px; text-align: center; transition: all 0.3s ease; }
        .hero-illustration .illustration-box .icon-grid .icon-item:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06); }
        .hero-illustration .illustration-box .icon-grid .icon-item i { font-size: 2rem; margin-bottom: 8px; }
        .hero-illustration .illustration-box .icon-grid .icon-item .icon-blue { color: var(--primary-blue); }
        .hero-illustration .illustration-box .icon-grid .icon-item .icon-red { color: var(--secondary-red); }
        .hero-illustration .illustration-box .icon-grid .icon-item .icon-dark { color: var(--dark); }
        .hero-illustration .illustration-box .icon-grid .icon-item span { display: block; font-size: 0.7rem; font-weight: 600; color: #6c757d; }
        .hero-illustration .stats-badge { background: white; padding: 16px 24px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06); display: inline-flex; align-items: center; gap: 12px; margin-top: 20px; border: 1px solid rgba(13, 110, 253, 0.06); }
        .hero-illustration .stats-badge .stat-number { font-size: 1.5rem; font-weight: 900; color: var(--primary-blue); }
        .hero-illustration .stats-badge .stat-label { font-size: 0.8rem; color: #6c757d; font-weight: 500; }
        .features-section { padding: 80px 0; background: var(--white); }
        .features-section .section-badge { background: rgba(220, 53, 69, 0.08); color: var(--secondary-red); padding: 4px 16px; border-radius: 50px; font-size: 0.7rem; font-weight: 600; display: inline-block; margin-bottom: 12px; letter-spacing: 1px; }
        .features-section h2 { font-size: 2.5rem; font-weight: 900; color: var(--dark); }
        .features-section h2 span { color: var(--primary-blue); }
        .features-section .feature-card { background: white; padding: 32px 28px; border-radius: 20px; border: 1px solid rgba(0, 0, 0, 0.04); transition: all 0.4s ease; height: 100%; position: relative; overflow: hidden; }
        .features-section .feature-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--primary-blue), var(--secondary-red)); opacity: 0; transition: all 0.4s ease; }
        .features-section .feature-card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06); border-color: rgba(13, 110, 253, 0.1); }
        .features-section .feature-card:hover::before { opacity: 1; }
        .features-section .feature-card .feature-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 16px; }
        .features-section .feature-card .feature-icon.blue { background: rgba(13, 110, 253, 0.1); color: var(--primary-blue); }
        .features-section .feature-card .feature-icon.red { background: rgba(220, 53, 69, 0.1); color: var(--secondary-red); }
        .features-section .feature-card .feature-icon.dark { background: rgba(26, 26, 46, 0.06); color: var(--dark); }
        .features-section .feature-card h5 { font-weight: 700; color: var(--dark); font-size: 1.1rem; }
        .features-section .feature-card p { font-size: 0.9rem; color: #6c757d; line-height: 1.7; margin-bottom: 0; }
        .stats-section { padding: 60px 0; background: linear-gradient(135deg, var(--dark), var(--dark-light)); color: white; }
        .stats-section .stat-item { text-align: center; }
        .stats-section .stat-item .stat-number { font-size: 3rem; font-weight: 900; background: linear-gradient(135deg, var(--primary-blue), #0dcaf0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stats-section .stat-item .stat-label { font-size: 0.9rem; opacity: 0.7; font-weight: 500; }
        .cta-section { padding: 80px 0; background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark)); color: white; position: relative; overflow: hidden; }
        .cta-section::before { content: ''; position: absolute; top: -50%; right: -20%; width: 60%; height: 200%; background: rgba(255, 255, 255, 0.03); border-radius: 50%; }
        .cta-section h2 { font-size: 2.5rem; font-weight: 900; }
        .cta-section p { opacity: 0.8; font-size: 1.1rem; }
        .cta-section .btn-cta { padding: 14px 40px; font-size: 1.05rem; font-weight: 700; border-radius: 50px; background: white; color: var(--primary-blue); border: none; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .cta-section .btn-cta:hover { transform: translateY(-3px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2); color: var(--primary-blue-dark); }
        .cta-section .btn-cta-outline { padding: 14px 36px; font-size: 1.05rem; font-weight: 600; border-radius: 50px; border: 2px solid rgba(255, 255, 255, 0.4); background: transparent; color: white; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; }
        .cta-section .btn-cta-outline:hover { background: rgba(255, 255, 255, 0.1); border-color: white; color: white; }
        .footer { background: var(--dark); color: rgba(255, 255, 255, 0.6); padding: 40px 0 20px; }
        .footer .brand { font-size: 1.3rem; font-weight: 900; text-decoration: none; }
        .footer .brand .blue { color: var(--primary-blue); }
        .footer .brand .red { color: var(--secondary-red); }
        .footer p { font-size: 0.85rem; }
        .footer .footer-links a { color: rgba(255, 255, 255, 0.5); text-decoration: none; transition: all 0.3s ease; font-size: 0.85rem; }
        .footer .footer-links a:hover { color: white; }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-12px); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fadeUp { animation: fadeInUp 0.8s ease forwards; }
        .animate-fadeUp-delay { animation: fadeInUp 0.8s ease 0.2s forwards; opacity: 0; }
        .animate-fadeUp-delay-2 { animation: fadeInUp 0.8s ease 0.4s forwards; opacity: 0; }
        @media (max-width: 992px) { .hero-content h1 { font-size: 2.5rem; } .hero-illustration .illustration-box { margin-top: 40px; } }
        @media (max-width: 576px) { .hero-content h1 { font-size: 2rem; } .hero-content p { font-size: 1rem; } .hero-content .btn-hero, .hero-content .btn-hero-outline { width: 100%; justify-content: center; } .features-section h2 { font-size: 1.8rem; } .stats-section .stat-item .stat-number { font-size: 2rem; } .cta-section h2 { font-size: 1.8rem; } .hero-illustration .illustration-box .icon-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

    <nav class="navbar-custom" id="navbar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= BASE_URL ?>/index.php" class="brand">
                    <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP" width="32" height="32" class="me-2 rounded">
                    <span class="blue">Boda</span><span class="red">ERP</span>
                    <small>Enterprise Boda Management</small>
                </a>
                <div class="d-flex align-items-center gap-3">
                    <a href="<?= BASE_URL ?>/login.php" class="btn-login-nav"><i class="fas fa-sign-in-alt"></i> Login</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="bg-pattern"></div>
        <div class="bg-pattern-2"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="badge-hero animate-fadeUp"><i class="fas fa-rocket me-1"></i> Revolutionizing Boda Management</div>
                    <h1 class="animate-fadeUp"><span class="highlight-blue">Digital</span> Tax &amp; <br><span class="highlight-red">Boda</span> Management<br>For <span class="highlight-blue">Every City</span></h1>
                    <p class="animate-fadeUp-delay">BodaERP is an enterprise-grade SaaS platform that streamlines rider registration, tax collection, and compliance tracking for city councils across Uganda.</p>
                    <div class="d-flex flex-wrap gap-3 animate-fadeUp-delay-2">
                        <a href="<?= BASE_URL ?>/login.php" class="btn-hero"><i class="fas fa-arrow-right"></i> Get Started</a>
                        <a href="#features" class="btn-hero-outline"><i class="fas fa-play-circle"></i> Learn More</a>
                    </div>
                    <div class="mt-4 d-flex gap-4 animate-fadeUp-delay-2">
                        <div><span class="fw-bold fs-5 text-dark"><?= number_format($totalRiders) ?>+</span><small class="text-muted d-block">Riders Registered</small></div>
                        <div><span class="fw-bold fs-5 text-dark"><?= h($revenueDisplay) ?></span><small class="text-muted d-block">Revenue Collected</small></div>
                        <div><span class="fw-bold fs-5 text-dark"><?= $complianceRate ?>%</span><small class="text-muted d-block">Compliance Rate</small></div>
                    </div>
                </div>
                <div class="col-lg-6 hero-illustration animate-fadeUp-delay">
                    <div class="illustration-box">
                        <div class="icon-grid">
                            <div class="icon-item"><i class="fas fa-motorcycle icon-blue"></i><span>Riders</span></div>
                            <div class="icon-item"><i class="fas fa-id-card icon-red"></i><span>ID Cards</span></div>
                            <div class="icon-item"><i class="fas fa-qrcode icon-dark"></i><span>QR Codes</span></div>
                            <div class="icon-item"><i class="fas fa-money-bill-wave icon-blue"></i><span>Payments</span></div>
                            <div class="icon-item"><i class="fas fa-chart-line icon-red"></i><span>Reports</span></div>
                            <div class="icon-item"><i class="fas fa-shield-alt icon-dark"></i><span>Compliance</span></div>
                        </div>
                        <div class="stats-badge">
                            <div><span class="stat-number">4</span><span class="stat-label">User Levels</span></div>
                            <div style="width: 1px; height: 30px; background: #e9ecef;"></div>
                            <div><span class="stat-number">3</span><span class="stat-label">System Modules</span></div>
                            <div style="width: 1px; height: 30px; background: #e9ecef;"></div>
                            <div><span class="stat-number">24/7</span><span class="stat-label">Availability</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">Features</span>
                <h2>Everything You Need To <span>Manage</span> Boda Operations</h2>
                <p class="text-muted" style="max-width: 600px; margin: 0 auto;">A complete solution designed for city councils to streamline boda boda management.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon blue"><i class="fas fa-user-plus"></i></div><h5>Rider Registration</h5><p>Register riders with full personal details, NIN verification, and stage assignment in seconds.</p></div></div>
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon red"><i class="fas fa-id-card"></i></div><h5>Digital ID Cards</h5><p>Generate beautiful, print-ready ID cards with QR codes for instant verification.</p></div></div>
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon dark"><i class="fas fa-money-bill-wave"></i></div><h5>Payment Management</h5><p>Collect annual taxes with automatic revenue splitting between City Council, Association, and Tech.</p></div></div>
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon blue"><i class="fas fa-qrcode"></i></div><h5>QR Verification</h5><p>Enforcement officers scan QR codes to instantly verify rider compliance on the spot.</p></div></div>
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon red"><i class="fas fa-bell"></i></div><h5>Automated Reminders</h5><p>SMS and email reminders for renewals, expirations, and payment due dates.</p></div></div>
                <div class="col-md-4"><div class="feature-card"><div class="feature-icon dark"><i class="fas fa-chart-pie"></i></div><h5>Real-time Reports</h5><p>Live dashboards with revenue analytics, compliance stats, and detailed reports.</p></div></div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-6 stat-item"><div class="stat-number"><?= number_format($totalRiders) ?></div><div class="stat-label">Total Riders</div></div>
                <div class="col-md-3 col-6 stat-item"><div class="stat-number"><?= number_format($activeRiders) ?></div><div class="stat-label">Active (Paid)</div></div>
                <div class="col-md-3 col-6 stat-item"><div class="stat-number"><?= h($revenueDisplay) ?></div><div class="stat-label">Revenue Collected</div></div>
                <div class="col-md-3 col-6 stat-item"><div class="stat-number"><?= $complianceRate ?>%</div><div class="stat-label">Compliance Rate</div></div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container text-center position-relative">
            <h2>Ready to Transform Boda Management?</h2>
            <p class="mb-4" style="max-width: 500px; margin: 0 auto 30px;">Join the city councils already digitizing boda operations for better revenue collection and compliance.</p>
            <div class="d-flex flex-wrap gap-3 justify-content-center">
                <a href="<?= BASE_URL ?>/login.php" class="btn-cta"><i class="fas fa-rocket"></i> Get Started Now</a>
                <a href="#contact" class="btn-cta-outline"><i class="fas fa-phone"></i> Contact Us</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="<?= BASE_URL ?>/index.php" class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BodaERP" width="24" height="24" class="me-2"><span class="blue">Boda</span><span class="red">ERP</span></a>
                    <p class="mt-2" style="max-width: 300px;">Enterprise Boda Boda Management System for city councils across Uganda. Digitizing operations for better revenue collection and compliance.</p>
                </div>
                <div class="col-md-2"><h6 class="text-white fw-bold">Product</h6><div class="footer-links d-flex flex-column gap-2"><a href="#features">Features</a><a href="#contact">Support</a></div></div>
                <div class="col-md-2"><h6 class="text-white fw-bold">Company</h6><div class="footer-links d-flex flex-column gap-2"><a href="#contact">Contact</a></div></div>
                <div class="col-md-4" id="contact">
                    <h6 class="text-white fw-bold">Contact Us</h6>
                    <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i> Kampala, Uganda</p>
                    <p class="mb-1"><i class="fas fa-phone me-2"></i> +256 700 123 456</p>
                    <p><i class="fas fa-envelope me-2"></i> info@bodaerp.com</p>
                </div>
            </div>
            <hr class="mt-4" style="border-color: rgba(255,255,255,0.06);">
            <div class="text-center"><small>&copy; 2026 <span class="text-white">Kakebe Technologies Limited</span>. All rights reserved.</small></div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) navbar.classList.add('scrolled'); else navbar.classList.remove('scrolled');
        });
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
            });
        });
    </script>
</body>
</html>
