<?php
// /robots.txt (rewritten here by .htaccess) so the sitemap address is right in any folder or domain.
require_once __DIR__ . '/packages/backend/config/config.php';
header('Content-Type: text/plain; charset=UTF-8');
$base = BASE_URL;
echo "User-agent: *\n";
echo "Disallow: $base/pages/\n";
echo "Disallow: $base/api/\n";
echo "Disallow: $base/uploads/\n";
echo "Disallow: $base/verify.php?\n";
echo "Disallow: $base/reset-password.php\n";
echo "Allow: $base/\n\n";
echo 'Sitemap: ' . app_url('sitemap.xml') . "\n";
