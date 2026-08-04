<?php
require_once __DIR__ . '/config/config.php';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - BodaERP</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #1a1a2e, #2d2d3f); color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; text-align: center; }
        .brand-blue { color: #0d6efd; }
        .brand-red { color: #dc3545; }
        .error-code { font-size: 7rem; font-weight: 900; line-height: 1; background: linear-gradient(135deg, #0d6efd, #dc3545); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        h1 { font-weight: 800; margin: 16px 0 8px; font-size: 1.5rem; }
        p { color: rgba(255,255,255,0.6); margin-bottom: 28px; }
        .btn-home { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #0d6efd, #0a58ca); color: #fff; text-decoration: none; font-weight: 600; padding: 12px 28px; border-radius: 50px; box-shadow: 0 6px 24px rgba(13,110,253,0.35); transition: transform 0.2s ease; }
        .btn-home:hover { transform: translateY(-2px); color: #fff; }
        .brand-mark { margin-top: 40px; font-weight: 800; letter-spacing: 1px; opacity: 0.5; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div>
        <div class="error-code">404</div>
        <h1>Page Not Found</h1>
        <p>The page you're looking for doesn't exist or may have been moved.</p>
        <a href="<?= BASE_URL ?>/index.php" class="btn-home"><i class="fas fa-home"></i> Back to Home</a>
        <div class="brand-mark"><span class="brand-blue">Boda</span><span class="brand-red">ERP</span></div>
    </div>
</body>
</html>
