<?php
// ============================================================
// BodaERP – includes/env_loader.php
// Minimal, dependency-free .env loader (no composer package for this
// is available in this environment — no internet access to fetch
// one — so this hand-parses simple KEY=VALUE lines instead).
// Never overwrites a variable the environment already provides (e.g.
// one set by the hosting panel on the live server), so a committed
// .env.example with blanks can never shadow real production config.
// ============================================================

if (!function_exists('loadEnvFile')) {
    function loadEnvFile(string $path): void {
        if (!is_file($path) || !is_readable($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip one layer of matching surrounding quotes, if present.
            $len = strlen($value);
            if ($len >= 2 && (
                ($value[0] === '"' && $value[$len - 1] === '"') ||
                ($value[0] === "'" && $value[$len - 1] === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
