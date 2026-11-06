<?php
// /sitemap.xml (rewritten here by .htaccess): every public page, including published content pages.
require_once __DIR__ . '/packages/backend/site.php';

header('Content-Type: application/xml; charset=UTF-8');
// Static pages carry the date their file last changed; content pages the date they were last edited.
$fileDate = fn(string $file) => date('Y-m-d H:i:s', (int) @filemtime(__DIR__ . '/' . $file) ?: time());
$urls = [['', $fileDate('index.php')], ['faq.php', $fileDate('faq.php')], ['contact.php', $fileDate('contact.php')],
    ['verify.php', $fileDate('verify.php')], ['login.php', $fileDate('login.php')]];
foreach (fetchAll("SELECT slug, updated_at FROM pages WHERE status = 'published' AND deleted_at IS NULL ORDER BY slug") as $p) {
    $urls[] = ['p/' . $p['slug'], $p['updated_at']];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $updated]) {
    echo '  <url><loc>' . htmlspecialchars(app_url($path), ENT_XML1) . '</loc>'
        . ($updated ? '<lastmod>' . date('Y-m-d', strtotime($updated)) . '</lastmod>' : '') . "</url>\n";
}
echo "</urlset>\n";
