<?php
// Results of the top-bar search for staff roles. ?format=json answers the suggestion list under the search box.
// A search with exactly one match opens that record straight away.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/global_search.php';
$me = require_role(SEARCH_ROLES);
$q = trim((string) ($_GET['q'] ?? ''));

if (!rate_limit('search:' . (int) $me['user_id'], 240, 300)) {
    if (($_GET['format'] ?? '') === 'json') { http_response_code(429); header('Content-Type: application/json'); echo '{"groups":[]}'; exit; }
    flash('error', 'Too many searches in a short time. Wait a few minutes and try again.');
    $q = '';
}

if (($_GET['format'] ?? '') === 'json') {
    $groups = global_search($me['role'], $q, 4);
    $out = [];
    foreach ($groups as $label => $rows) {
        $out[] = ['label' => $label, 'results' => array_map(fn($r) => $r + ['href' => BASE_URL . $r['url']], $rows)];
    }
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['q' => $q, 'groups' => $out, 'all' => BASE_URL . '/pages/account/search.php?q=' . rawurlencode($q)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$groups = global_search($me['role'], $q, 25);
$count = array_sum(array_map('count', $groups));
if ($count === 1 && empty($_GET['list'])) redirect(reset($groups)[0]['url']);

$sidebar = ['super_admin' => 'superadmin', 'city_admin' => 'citycouncil', 'chairperson' => 'chairperson', 'government' => 'government'][$me['role']];
portal_start($sidebar, 'search', 'Search', [
    'subtitle' => $q === '' ? 'Type a name, permit number, plate, receipt, phone number, stage, city or role in the search box at the top.'
        : ($count ? number_format($count) . ' ' . ($count === 1 ? 'match' : 'matches') . ' for "' . $q . '"' : 'Nothing matches "' . $q . '"'),
]);
?>
<form class="filter-bar mb-3" method="get" role="search" aria-label="Search everything">
    <input class="form-control" type="search" name="q" value="<?= h($q) ?>" minlength="2" maxlength="80" aria-label="Search" placeholder="Name, permit number, plate, receipt, phone, stage, city or role">
    <button class="btn btn-primary">Search</button>
</form>
<?php if ($q !== '' && mb_strlen($q) < 2): ?>
    <div class="alert alert-info">Type at least two letters or numbers.</div>
<?php elseif ($q !== '' && !$groups): ?>
    <section class="card"><div class="card-body"><?= empty_state('fa-magnifying-glass', 'No matches', 'Check the spelling, or search with part of the number only, for example the last digits of a phone number or plate.') ?></div></section>
<?php endif; ?>
<?php foreach ($groups as $label => $rows): ?>
<section class="card mb-3">
    <div class="card-body">
        <h2 class="section-title h6 mb-3"><?= h($label) ?> <span class="text-muted fw-normal">(<?= count($rows) ?><?= count($rows) >= 25 ? '+' : '' ?>)</span></h2>
        <ul class="list-unstyled mb-0 search-results">
        <?php foreach ($rows as $r): ?>
            <li><a href="<?= h(BASE_URL . $r['url']) ?>"><strong><?= h($r['title']) ?></strong><span><?= h($r['detail']) ?></span></a></li>
        <?php endforeach; ?>
        </ul>
        <?php if (count($rows) >= 25): ?><p class="small text-muted mt-2 mb-0">Showing the first 25. Add more of the name or number to narrow it down.</p><?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<?php portal_end(); ?>
