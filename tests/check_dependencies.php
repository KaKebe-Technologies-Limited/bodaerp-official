<?php
// Checks every library version the pages load against the public OSV vulnerability database.
//   php tests/check_dependencies.php
// CDN links are invisible to package scanners such as Dependabot, so this reads them from the source.
// Exits 1 when a pinned version has a known advisory, 2 when the database could not be reached.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
$root = realpath(__DIR__ . '/..');

$patterns = [
    '#cdn\.jsdelivr\.net/npm/((?:@[a-z0-9-]+/)?[a-z0-9.-]+)@(\d[\w.-]*)/#i',
    '#cdnjs\.cloudflare\.com/ajax/libs/([a-z0-9.-]+)/(\d[\w.-]*)/#i',
];
$npmNames = ['font-awesome' => '@fortawesome/fontawesome-free'];

$found = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/tests/')) continue;
    $source = (string) file_get_contents($file->getPathname());
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) continue;
        foreach ($matches as [, $name, $version]) $found[($npmNames[$name] ?? $name) . '@' . $version] = [$npmNames[$name] ?? $name, $version];
    }
}
$bundle = @file_get_contents("$root/packages/frontend/assets/vendor/ckeditor5/ckeditor5.js", false, null, 0, 400);
if ($bundle && preg_match('/v(\d+\.\d+\.\d+)/', $bundle, $hit)) $found['ckeditor5@' . $hit[1]] = ['ckeditor5', $hit[1]];
ksort($found);

$withAdvisories = 0;
foreach ($found as $label => [$name, $version]) {
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'timeout' => 15, 'ignore_errors' => true,
        'content' => json_encode(['package' => ['name' => $name, 'ecosystem' => 'npm'], 'version' => $version])]]);
    $reply = @file_get_contents('https://api.osv.dev/v1/query', false, $context);
    if ($reply === false) { fwrite(STDERR, "Could not reach api.osv.dev\n"); exit(2); }
    $advisories = json_decode($reply, true)['vulns'] ?? [];
    if ($advisories === []) { echo "  ok    $label\n"; continue; }
    $withAdvisories++;
    echo "  FAIL  $label: " . count($advisories) . " known advisories\n";
    foreach (array_slice($advisories, 0, 4) as $advisory) echo '          ' . $advisory['id'] . ' ' . ($advisory['summary'] ?? '') . "\n";
}
echo count($found) . " libraries checked, $withAdvisories with known advisories\n";
exit($withAdvisories ? 1 : 0);
