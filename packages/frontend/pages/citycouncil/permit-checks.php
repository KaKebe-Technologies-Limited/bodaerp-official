<?php
// Roadside permit checks recorded on the verify page by chairpersons and city staff in this city: who was
// checked, what the card showed, what was done, and where (when the officer shared the phone's location).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_role(['city_admin']);
$cityId = (string) $_SESSION['city_id'];

$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
$outcome = in_array($_GET['outcome'] ?? '', ['ok', 'warned', 'impounded', 'other'], true) ? $_GET['outcome'] : '';
$where = "pc.city_id = ? AND pc.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)" . ($outcome ? " AND pc.outcome = ?" : '');
$params = $outcome ? [$cityId, $outcome] : [$cityId];

$summary = fetchOne("SELECT COUNT(*) AS total, SUM(pc.status_seen <> 'active') AS not_valid, SUM(pc.outcome = 'impounded') AS impounded,
    COUNT(DISTINCT pc.checked_by) AS officers FROM permit_checks pc WHERE $where", $params);
$page = paginate((int) $summary['total'], 50);
$rows = fetchAll("SELECT pc.*, r.id_number, r.full_name, s.name AS stage_name, u.name AS officer FROM permit_checks pc
    JOIN riders r ON r.id = pc.rider_id LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN users u ON u.id = pc.checked_by
    WHERE $where ORDER BY pc.created_at DESC LIMIT " . (int) $page['per_page'] . " OFFSET " . (int) $page['offset'], $params);
$outcomes = ['ok' => ['All in order', 'success'], 'warned' => ['Warned', 'warning text-dark'], 'impounded' => ['Impounded', 'danger'], 'other' => ['Other', 'secondary']];

portal_start('citycouncil', 'permit-checks', 'Roadside checks', [
    'subtitle' => 'Permit checks recorded by scanning ID cards in ' . ($_SESSION['city_name'] ?? 'your city'),
]);
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-qrcode" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= (int) $summary['total'] ?></div><div class="stat-hero-label">Checks</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-circle-xmark" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= (int) $summary['not_valid'] ?></div><div class="stat-hero-label">Permit not valid</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-motorcycle" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= (int) $summary['impounded'] ?></div><div class="stat-hero-label">Impounded</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= (int) $summary['officers'] ?></div><div class="stat-hero-label">People checking</div></div></div></div>
</div>

<form class="d-flex flex-wrap gap-2 mb-3" method="get">
    <select class="form-select w-auto" name="days" aria-label="Period" data-autosubmit>
        <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 3 months', 365 => 'Last 12 months'] as $d => $label): ?><option value="<?= $d ?>"<?= $days === $d ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select w-auto" name="outcome" aria-label="Outcome" data-autosubmit>
        <option value="">Every outcome</option>
        <?php foreach ($outcomes as $key => [$label]): ?><option value="<?= $key ?>"<?= $outcome === $key ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
</form>

<section class="card">
    <div class="card-body">
    <?php if (!$rows): ?>
        <?= empty_state('fa-qrcode', 'No checks recorded in this period', 'Signed-in chairpersons and city staff can record a check after scanning a rider\'s ID card with their phone.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">When</th><th scope="col">Rider</th><th scope="col">Card showed</th><th scope="col">Outcome</th><th scope="col">Checked by</th><th scope="col">Location</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $c): [$label, $tone] = $outcomes[$c['outcome']]; ?>
                    <tr>
                        <td><?= h(formatDate($c['created_at'], 'j M Y, H:i')) ?></td>
                        <td><?= h($c['full_name']) ?><div class="small text-muted"><?= h($c['id_number']) ?> · <?= h($c['stage_name'] ?? '') ?></div></td>
                        <td><?= $c['status_seen'] === 'active' ? 'Valid' : h(ucfirst($c['status_seen'])) ?></td>
                        <td><span class="badge bg-<?= $tone ?>"><?= h($label) ?></span><?php if ($c['note']): ?><div class="small text-muted"><?= h($c['note']) ?></div><?php endif; ?></td>
                        <td><?= h($c['officer'] ?? '') ?></td>
                        <td><?php if ($c['latitude'] !== null): ?><a href="https://www.openstreetmap.org/?mlat=<?= h($c['latitude']) ?>&amp;mlon=<?= h($c['longitude']) ?>#map=17/<?= h($c['latitude']) ?>/<?= h($c['longitude']) ?>" target="_blank" rel="noopener">Map</a><?= $c['accuracy_m'] ? '<div class="small text-muted">within ' . (int) $c['accuracy_m'] . ' m</div>' : '' ?><?php else: ?><span class="text-muted">Not shared</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($page) ?>
    <?php endif; ?>
    </div>
</section>
<?php portal_end(); ?>
