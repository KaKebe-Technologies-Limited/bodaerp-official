<?php
// Languages for the rider portal and rider SMS. English text lives in the code as the default; other
// languages come from the translations table, edited in Super Admin → Languages. A language is offered
// to riders only once it is listed in the languages_enabled setting, and only reviewed text is shown.
require_once __DIR__ . '/db_helpers.php';

const LANGUAGES = ['en' => 'English', 'lg' => 'Luganda', 'laj' => 'Lango', 'ach' => 'Acholi', 'nyn' => 'Runyankole'];

/** Languages riders may choose: English plus the ones switched on in settings. */
function languages_enabled(): array {
    $codes = array_filter(array_map('trim', explode(',', strtolower(get_setting('languages_enabled', 'en')))));
    $enabled = ['en' => LANGUAGES['en']];
    foreach ($codes as $code) if (isset(LANGUAGES[$code])) $enabled[$code] = LANGUAGES[$code];
    return $enabled;
}

function language_or_default(?string $code): string {
    $code = strtolower(trim((string) $code));
    return isset(languages_enabled()[$code]) ? $code : 'en';
}

/** The signed-in person's language (riders choose it in My Account); English for everyone else. */
function current_language(): string {
    static $lang = null;
    if ($lang !== null) return $lang;
    if (empty($_SESSION['user_id'])) return $lang = 'en';
    return $lang = language_or_default((string) fetchValue("SELECT language FROM users WHERE id = ?", [(int) $_SESSION['user_id']]));
}

/** Reviewed translations for one language, keyed by message key. */
function translations_for(string $language): array {
    static $cache = [];
    if ($language === 'en') return [];
    if (!isset($cache[$language])) {
        try {
            $rows = fetchAll("SELECT msg_key, text FROM translations WHERE language = ? AND needs_review = 0", [$language]);
            $cache[$language] = array_column($rows, 'text', 'msg_key');
        } catch (Throwable $e) {
            db_probe_failed('translations', $e);
            $cache[$language] = [];
        }
    }
    return $cache[$language];
}

/**
 * Text in the reader's language: t('rider.renew.title', 'Renew your permit'). {name} style placeholders are
 * filled from $vars. Falls back to the English default whenever no reviewed translation exists.
 */
function t(string $key, string $english, array $vars = [], ?string $language = null): string {
    $text = translations_for($language ?? current_language())[$key] ?? $english;
    return $vars ? strtr($text, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', $vars))) : $text;
}

/** Every t() key used in the code, with its English default, for the Languages editor. */
function translation_catalogue(): array {
    $root = dirname(__DIR__, 2);
    $found = [];
    $files = array_merge(glob($root . '/packages/frontend/pages/rider/*.php') ?: [], glob($root . '/packages/frontend/partials/*.php') ?: [],
        glob($root . '/packages/frontend/pages/account/*.php') ?: [], [$root . '/packages/backend/portal_nav.php']);
    foreach ($files as $file) {
        $src = (string) file_get_contents($file);
        if (!preg_match_all("/\\bt\\(\\s*'([a-z0-9_.]+)'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'/", $src, $m, PREG_SET_ORDER)) continue;
        foreach ($m as [, $key, $english]) $found[$key] ??= ['english' => stripslashes($english), 'file' => basename($file)];
    }
    ksort($found);
    return $found;
}
