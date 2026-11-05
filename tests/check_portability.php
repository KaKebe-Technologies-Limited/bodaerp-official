<?php
// BodaERP - portability check (command line): catches what works on Windows/XAMPP but breaks on Linux, or
// the other way round, without needing both machines. php tests/check_portability.php Checks:
// include/require targets exist with the exact same letter case; no hard-coded C:\xampp or /var/www paths
// or localhost URLs in application code; no CRLF line endings or UTF-8 BOM in PHP files; asset links in
// pages go through BASE_URL.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
$root = realpath(__DIR__ . '/..');
$skipDirs = ['/.git/', '/.claude/', '/vendor/', '/uploads/', '/storage/', '/docker/', '/assets/vendor/'];
$problems = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

/** Exact-case existence check (Windows ignores case, Linux does not). */
function exists_exact_case(string $path): bool {
    $path = str_replace('\\', '/', $path);
    $parts = explode('/', $path);
    $cur = $parts[0] === '' ? '/' : $parts[0];
    for ($i = 1; $i < count($parts); $i++) {
        $p = $parts[$i];
        if ($p === '' || $p === '.') continue;
        if ($p === '..') { $cur = dirname($cur); continue; }
        if (!is_dir($cur)) return false;
        if (!in_array($p, scandir($cur) ?: [], true)) return false;
        $cur = rtrim($cur, '/') . '/' . $p;
    }
    return file_exists($cur);
}

$count = 0;
$definedFunctions = [];
$markupActions = [];
$markupAttrs = [];
$behaviourSource = '';
foreach ($files as $f) {
    $path = str_replace('\\', '/', $f->getPathname());
    $rel = substr($path, strlen($root) + 1);
    foreach ($skipDirs as $d) if (str_contains('/' . $rel, $d)) continue 2;
    $ext = strtolower($f->getExtension());
    if (!in_array($ext, ['php', 'js', 'css', 'htaccess'], true) && $f->getFilename() !== '.htaccess') continue;
    $src = (string) file_get_contents($path);
    $count++;

    if (str_starts_with($src, "\xEF\xBB\xBF")) $problems[] = "$rel: starts with a UTF-8 BOM (breaks headers/sessions)";
    if (str_contains($src, "\r\n")) $problems[] = "$rel: has Windows (CRLF) line endings; .gitattributes should normalise them";
    if (preg_match_all('/\bfunction\s+([A-Za-z_]\w*)\s*\(/', $src, $fm)) $definedFunctions += array_flip($fm[1]);
    if (!str_starts_with($rel, 'tests/')) $behaviourSource .= $src . "\n";
    if ($ext !== 'php') continue;

    if (!str_starts_with($rel, 'tests/') && !str_starts_with($rel, 'database/')) {
        if (preg_match_all('/\sdata-call(?:-input|-submit)?="([A-Za-z_]\w*)"/', $src, $am)) {
            foreach ($am[1] as $action) $markupActions[$action] = $rel;
        }
        if (preg_match_all('/\s(data-[a-z][a-z0-9-]*)(?=[\s=>])/', $src, $dm)) {
            foreach ($dm[1] as $attr) $markupAttrs[$attr] = $rel;
        }
        $code = implode('', array_map(fn($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (is_array($t) ? $t[1] : $t), token_get_all($src)));
        if (preg_match('#<script(?![^>]*\s(?:src|nonce)=)(?![^>]*\stype=["\']application/(?:ld\+)?json)[^>]*>#i', $code, $hit)) {
            $problems[] = "$rel: inline <script> without nonce=\"<?= csp_nonce() ?>\" (the CSP blocks it): " . substr($hit[0], 0, 80);
        }
        if (preg_match('#<(?:script[^>]*\ssrc|link[^>]*\shref)=["\']https?://#i', $src, $hit)) {
            $problems[] = "$rel: loads a script or stylesheet from another site (self-host it in assets/vendor/, see VERSIONS.json): " . substr($hit[0], 0, 80);
        }
        if (preg_match('/<[a-z][^>]*\son(?:click|change|input|submit|load|error|keyup|keydown|blur|focus)=["\']/i', $src, $hit)) {
            $problems[] = "$rel: inline event handler (use a data attribute, see main.js): " . substr($hit[0], 0, 80);
        }
    }

    $code = preg_replace('#^\s*(//|\*|/\*).*$#m', '', $src);   // ignore examples in comments
    if (preg_match_all('/(?:require|include)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*([\'"])([^\'"]+)\1(?!\s*\.)/', $code, $m)) {
        foreach ($m[2] as $target) {
            $abs = dirname($path) . '/' . ltrim($target, '/');
            if (!exists_exact_case($abs)) $problems[] = "$rel: include '$target' does not exist with that exact letter case";
        }
    }
    if (str_starts_with($rel, 'tests/')) continue;
    if (preg_match('#[A-Za-z]:\\\\\\\\?xampp|/var/www/|/home/[a-z]+/|/opt/lampp#i', $src, $hit)) $problems[] = "$rel: hard-coded machine path '{$hit[0]}'";
    if (preg_match('#https?://(localhost|127\.0\.0\.1)[:/]#', $src, $hit)) $problems[] = "$rel: hard-coded local URL '{$hit[0]}'";
    if (preg_match_all('#(?:href|src|action)="/(?!/)(?:assets|pages|uploads|login|index)[^"]*"#', $src, $hm)) {
        foreach ($hm[0] as $h) $problems[] = "$rel: root-relative link $h ignores BASE_URL (breaks in an XAMPP subfolder)";
    }
}

// These suites do not run JavaScript, so markup wired to nothing would otherwise pass unnoticed.
$mainJs = (string) file_get_contents("$root/packages/frontend/assets/js/main.js");
preg_match('/PAGE_ACTIONS = new Set\(\[(.*?)\]\)/s', $mainJs, $actionList);
preg_match_all("/'([A-Za-z_]\w*)'/", $actionList[1] ?? '', $listedActions);
foreach ($markupActions as $action => $rel) {
    if (!in_array($action, $listedActions[1], true)) $problems[] = "$rel: data-call names '$action', which is not in PAGE_ACTIONS in main.js";
    if (!isset($definedFunctions[$action])) $problems[] = "$rel: data-call names '$action', but no such function exists";
}
foreach ($listedActions[1] as $action) {
    if (!isset($markupActions[$action])) $problems[] = "assets/js/main.js: PAGE_ACTIONS lists '$action', which no page uses";
}

$proseMentions = ['data-integrity'];
foreach ($markupAttrs as $attr => $rel) {
    if (str_starts_with($attr, 'data-bs-') || in_array($attr, $proseMentions, true)) continue;
    $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', substr($attr, 5)))));
    $read = preg_match('/(?:[\'"\[]\s*|\[)' . preg_quote($attr, '/') . '\b(?!=")|dataset\.' . preg_quote($camel, '/') . '\b/', $behaviourSource);
    if (!$read) $problems[] = "$rel: $attr is in the markup, but no script or stylesheet reads it";
}

echo "Checked $count files\n";
foreach ($problems as $p) echo "  FAIL  $p\n";
echo count($problems) ? count($problems) . " problem(s)\n" : "No portability problems found\n";
exit($problems ? 1 : 0);
