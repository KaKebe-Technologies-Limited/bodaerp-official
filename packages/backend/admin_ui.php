<?php
// BodaERP - shared building blocks for portal pages: page shell, pagination, sortable column headers and
// query-string helpers. Keeps every admin page on the same layout.
require_once __DIR__ . '/auth.php';

/**
 * Opens a portal page: <head>, the role's sidebar, the top header and the content area.
 * $opts: subtitle, breadcrumbs (label => url|null), actions (raw HTML for the header's right side), editor (bool), head (raw HTML)
 */
function portal_start(string $sidebar, string $active, string $title, array $opts = []): void {
    $opts += ['subtitle' => '', 'breadcrumbs' => [], 'actions' => '', 'head' => ''];
    $pageTitle = $title . ' | ' . get_setting('site_name', 'BodaERP');
    echo "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
    require __DIR__ . '/../frontend/partials/head-assets.php';
    echo $opts['head'] . "\n</head>\n<body>\n";
    require __DIR__ . '/../frontend/partials/sidebar-' . preg_replace('/[^a-z]/', '', $sidebar) . '.php';
    echo '<div class="main-content" id="mainContent">';
    $pageTitle = $title;
    $pageSubtitle = $opts['subtitle'];
    require __DIR__ . '/../frontend/partials/topheader.php';
    echo '<div class="content-area">';
    if ($opts['breadcrumbs'] || $opts['actions']) {
        echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">';
        echo '<nav aria-label="Breadcrumb"><ol class="breadcrumb mb-0 small">';
        $i = 0; $n = count($opts['breadcrumbs']);
        foreach ($opts['breadcrumbs'] as $label => $url) {
            $i++;
            echo $url && $i < $n ? '<li class="breadcrumb-item"><a href="' . h(BASE_URL . $url) . '">' . h($label) . '</a></li>'
                : '<li class="breadcrumb-item active" aria-current="page">' . h($label) . '</li>';
        }
        echo '</ol></nav><div class="d-flex flex-wrap gap-2">' . $opts['actions'] . '</div></div>';
    }
}

function portal_end(bool $editor = false): void {
    echo '</div></div>';
    require __DIR__ . '/../frontend/partials/scripts-footer.php';
    if ($editor) require __DIR__ . '/../frontend/partials/editor-assets.php';
    echo "\n</body>\n</html>\n";
}

/** Current query string with some values replaced (null removes a key). */
function query_with(array $changes): string {
    $q = array_merge($_GET, $changes);
    foreach ($q as $k => $v) if ($v === null || $v === '') unset($q[$k]);
    return $q ? '?' . http_build_query($q) : '';
}

/** @return array{page:int,per_page:int,offset:int,pages:int,total:int} */
function paginate(int $total, int $perPage = 25): array {
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'pages' => $pages, 'total' => $total];
}

function render_pagination(array $p): string {
    if ($p['pages'] <= 1) return '<p class="small text-muted mb-0">' . number_format($p['total']) . ' ' . ($p['total'] === 1 ? 'record' : 'records') . '</p>';
    $from = $p['offset'] + 1; $to = min($p['total'], $p['offset'] + $p['per_page']);
    $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><p class="small text-muted mb-0">Showing '
        . number_format($from) . '–' . number_format($to) . ' of ' . number_format($p['total']) . '</p><nav aria-label="Pages"><ul class="pagination pagination-sm mb-0">';
    $link = fn(int $n, string $label, bool $disabled = false, bool $current = false) => '<li class="page-item' . ($disabled ? ' disabled' : '') . ($current ? ' active' : '') . '">'
        . ($disabled || $current ? '<span class="page-link"' . ($current ? ' aria-current="page"' : '') . '>' . $label . '</span>' : '<a class="page-link" href="' . h(query_with(['page' => $n])) . '">' . $label . '</a>') . '</li>';
    $html .= $link($p['page'] - 1, '&lsaquo;<span class="visually-hidden"> Previous</span>', $p['page'] <= 1);
    $start = max(1, $p['page'] - 2); $end = min($p['pages'], $p['page'] + 2);
    if ($start > 1) $html .= $link(1, '1') . ($start > 2 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '');
    for ($i = $start; $i <= $end; $i++) $html .= $link($i, (string) $i, false, $i === $p['page']);
    if ($end < $p['pages']) $html .= ($end < $p['pages'] - 1 ? '<li class="page-item disabled"><span class="page-link">…</span></li>' : '') . $link($p['pages'], (string) $p['pages']);
    $html .= $link($p['page'] + 1, '&rsaquo;<span class="visually-hidden"> Next</span>', $p['page'] >= $p['pages']);
    return $html . '</ul></nav></div>';
}

/** Validated ORDER BY from ?sort=key&dir=asc|desc against an allow-list (key => SQL expression). */
function sort_sql(array $allowed, string $default, string $defaultDir = 'desc'): string {
    $key = (string) ($_GET['sort'] ?? $default);
    if (!isset($allowed[$key])) $key = $default;
    $dir = strtolower((string) ($_GET['dir'] ?? $defaultDir)) === 'asc' ? 'ASC' : 'DESC';
    return $allowed[$key] . ' ' . $dir;
}

function sort_link(string $key, string $label, string $default, string $defaultDir = 'desc'): string {
    $current = (string) ($_GET['sort'] ?? $default);
    $dir = strtolower((string) ($_GET['dir'] ?? $defaultDir));
    $next = $current === $key && $dir === 'asc' ? 'desc' : 'asc';
    $icon = $current === $key ? ($dir === 'asc' ? ' <i class="fas fa-sort-up" aria-hidden="true"></i>' : ' <i class="fas fa-sort-down" aria-hidden="true"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . h(query_with(['sort' => $key, 'dir' => $next, 'page' => null])) . '">' . h($label) . $icon . '</a>';
}

/** Selected ids from bulk-action checkboxes (name="ids[]"). @return int[] */
function posted_ids(): array {
    return array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($i) => $i > 0)));
}

/**
 * Small action menu down the side of a document page (ID card, receipt, A4 card sheet), in place of a row of buttons
 * on top (see .doc-layout in portal-shell.css). $groups: heading => items. Item keys: label, icon (Font Awesome name
 * without "fa-"), then href (a link; external => true opens a new tab), or form (id of the form the button submits),
 * or attrs (data-* attributes for a button the page script handles). Optional tone: 'primary' or 'danger'.
 * A null item is skipped, so callers can write `$canEdit ? [...] : null`.
 */
function action_rail(array $groups, string $label = 'Actions'): string {
    $html = '<nav class="action-rail no-print" aria-label="' . h($label) . '">';
    foreach ($groups as $title => $items) {
        $items = array_filter($items);
        if (!$items) continue;
        $html .= '<div class="action-rail-group">' . ($title !== '' ? '<div class="action-rail-title">' . h((string) $title) . '</div>' : '');
        foreach ($items as $item) {
            $cls = 'action-rail-item' . (!empty($item['tone']) ? ' is-' . $item['tone'] : '');
            $inner = '<span>' . h($item['label']) . '</span>';
            $attrs = '';
            foreach ($item['attrs'] ?? [] as $name => $value) $attrs .= ' ' . h($name) . ($value === true ? '' : '="' . h((string) $value) . '"');
            if (isset($item['href'])) {
                $html .= '<a class="' . $cls . '" href="' . h($item['href']) . '"' . (!empty($item['external']) ? ' target="_blank" rel="noopener"' : '') . $attrs . '>' . $inner . '</a>';
            } elseif (isset($item['form'])) {
                $html .= '<button type="submit" form="' . h($item['form']) . '" class="' . $cls . '"' . $attrs . '>' . $inner . '</button>';
            } else {
                $html .= '<button type="button" class="' . $cls . '"' . $attrs . '>' . $inner . '</button>';
            }
        }
        $html .= '</div>';
    }
    return $html . '</nav>';
}

function empty_state(string $icon, string $title, string $text = '', string $action = ''): string {
    return '<div class="text-center py-5 text-muted"><i class="fas ' . h($icon) . ' fa-2x mb-3 opacity-50" aria-hidden="true"></i>'
        . '<p class="fw-semibold text-dark mb-1">' . h($title) . '</p>' . ($text ? '<p class="small mb-3">' . h($text) . '</p>' : '') . $action . '</div>';
}
