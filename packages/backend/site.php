<?php
// BodaERP - public website content (pages, menus, FAQ, branding, SEO) Every public page starts with:
// require_once __DIR__ . '/packages/backend/site.php'; All text comes from the database and is edited in
// Super Admin → Content.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';

/** Site-relative link from a menu/setting value: "/", "/p/about", "/#features", or a full https:// URL. */
function site_link(string $url): string {
    $url = trim($url);
    if ($url === '') return BASE_URL . '/';
    if (preg_match('#^(https?:|mailto:|tel:)#i', $url)) return $url;
    if ($url[0] === '#') return $url;
    return BASE_URL . '/' . ltrim($url, '/');
}

/** Full https://... URL for a stored path (/assets/..., /uploads/..., with or without BASE_URL). */
function absolute_url(string $stored): string {
    if (preg_match('#^https?://#i', $stored)) return $stored;
    $p = '/' . ltrim(str_replace('\\', '/', $stored), '/');
    if (BASE_URL !== '' && str_starts_with($p, BASE_URL . '/')) $p = substr($p, strlen(BASE_URL));
    return app_url(ltrim($p, '/'));
}

function menu_items(string $menu): array {
    static $cache = [];
    if (!isset($cache[$menu])) {
        try {
            $cache[$menu] = fetchAll("SELECT label, url, new_tab FROM menu_items WHERE menu = ? AND is_active = 1 ORDER BY sort_order, id", [$menu]);
        } catch (Throwable $e) { error_log('BodaERP menu: ' . $e->getMessage()); $cache[$menu] = []; }
    }
    return $cache[$menu];
}

function content_items(string $group): array {
    try { return fetchAll("SELECT id, icon, title, body FROM content_items WHERE group_code = ? AND is_active = 1 ORDER BY sort_order, id", [$group]); }
    catch (Throwable $e) { error_log('BodaERP content_items: ' . $e->getMessage()); return []; }
}

function page_by_slug(string $slug, bool $includeDrafts = false): ?array {
    if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) return null;
    return fetchOne("SELECT * FROM pages WHERE slug = ? AND deleted_at IS NULL" . ($includeDrafts ? '' : " AND status = 'published'"), [$slug]);
}

/** Page text with {year} and similar tokens replaced (used for footer copyright and the like). */
function site_text(string $key, string $default = ''): string {
    return strtr(get_setting($key, $default), ['{year}' => date('Y'), '{site_name}' => get_setting('site_name', 'BodaERP')]);
}

function brand_color(string $key, string $fallback): string {
    $v = get_setting($key, $fallback);
    return preg_match('/^#[0-9a-f]{3,8}$/i', $v) ? $v : $fallback;
}

const BRAND_DEFAULT_PRIMARY = '#056CFE';
const BRAND_DEFAULT_ACCENT = '#E2262F';
const BRAND_DEFAULT_DARK = '#001740';

/** Square logo icon (Super Admin → Site Text & Branding → Logo); the wordmark beside it is the site name as text. */
function brand_logo_url(): string {
    return media_url(get_setting('logo_path', '/assets/images/logo-mark.png'), '/assets/images/logo-mark.png');
}

/** <link> tags for the browser-tab and home-screen icons. An uploaded favicon replaces the bundled set. */
function brand_favicon_tags(): string {
    $custom = get_setting('favicon_path', '');
    $bundled = in_array($custom, ['', '/assets/images/favicon-32.png', '/assets/images/logo.png'], true);
    if (!$bundled) return '<link rel="icon" href="' . h(media_url($custom)) . '">';
    $img = BASE_URL . '/assets/images/';
    return '<link rel="icon" href="' . $img . 'favicon.ico" sizes="any">'
        . '<link rel="icon" type="image/png" sizes="32x32" href="' . $img . 'favicon-32.png">'
        . '<link rel="apple-touch-icon" href="' . $img . 'favicon-180.png">';
}

/** The brand colours as CSS custom properties, for public pages and portals alike. */
function brand_css_vars(): string {
    $p = brand_color('brand_color_primary', BRAND_DEFAULT_PRIMARY);
    $a = brand_color('brand_color_accent', BRAND_DEFAULT_ACCENT);
    $d = brand_color('brand_color_dark', BRAND_DEFAULT_DARK);
    return ':root{--brand:' . h($p) . ';--accent:' . h($a) . ';--brand-dark:' . h($d)
        . ';--brand-ink:color-mix(in srgb,' . h($p) . ' 78%,' . h($d) . ')}';
}

/**
 * Removes scripts, event handlers and javascript: links from admin-entered rich text (CKEditor output).
 * Admins are trusted, but a stolen admin session must not be able to plant script on public pages.
 */
function safe_html(string $html): string {
    if (trim($html) === '') return '';
    $allowed = ['p','br','strong','b','em','i','u','s','sub','sup','span','div','h2','h3','h4','h5','h6','ul','ol','li','a','blockquote',
        'table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col','img','figure','figcaption','hr','pre','code','mark','small',
        'iframe','oembed','label','input','section'];
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="safe-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('safe-root');
    if (!$root) return '';

    $walk = function (DOMNode $node) use (&$walk, $allowed) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) { $node->removeChild($child); continue; }
            if (!$child instanceof DOMElement) continue;
            $tag = strtolower($child->tagName);
            if (!in_array($tag, $allowed, true)) {
                if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'button', 'textarea', 'select', 'link', 'meta', 'base'], true)) {
                    $node->removeChild($child);
                } else {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                }
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = trim($attr->value);
                $keep = in_array($name, ['href','src','alt','title','class','style','colspan','rowspan','width','height','target','rel','url',
                    'type','checked','disabled','allowfullscreen','frameborder','allow','loading','data-oembed-url','start','reversed'], true);
                if (in_array($name, ['href', 'src', 'url', 'data-oembed-url'], true) && preg_match('#^\s*(javascript|vbscript|data):#i', $value)
                    && !($name === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp);#i', $value))) $keep = false;
                if ($name === 'style' && preg_match('/expression|javascript|url\s*\(/i', $value)) $keep = false;
                if ($tag === 'input' && $name === 'type' && $value !== 'checkbox') $keep = false;
                if (!$keep) $child->removeAttribute($attr->name);
            }
            if ($tag === 'iframe') {
                $src = $child->getAttribute('src');
                if (!preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|google\.com/maps)/#i', $src)) { $node->removeChild($child); continue; }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
            $walk($child);
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

/** Turns CKEditor's <oembed url="..."> for YouTube/Vimeo into a responsive player. */
function render_rich_text(string $html): string {
    $html = safe_html($html);
    return preg_replace_callback('#<figure class="media"><oembed url="([^"]+)"></oembed></figure>#i', function ($m) {
        $url = html_entity_decode($m[1]);
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $y)) {
            return '<div class="ratio ratio-16x9 my-3"><iframe src="https://www.youtube-nocookie.com/embed/' . $y[1] . '" title="Video" allowfullscreen loading="lazy"></iframe></div>';
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $v)) {
            return '<div class="ratio ratio-16x9 my-3"><iframe src="https://player.vimeo.com/video/' . $v[1] . '" title="Video" allowfullscreen loading="lazy"></iframe></div>';
        }
        return '<p><a href="' . h($url) . '" rel="noopener noreferrer" target="_blank">' . h($url) . '</a></p>';
    }, $html);
}
