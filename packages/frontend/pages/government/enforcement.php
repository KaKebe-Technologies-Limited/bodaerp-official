<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['government']);
$active = 'enforcement';

$filterCity = $_GET['city'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT e.*, c.name AS city_name, r.full_name AS rider_name FROM enforcement_actions e
        LEFT JOIN cities c ON c.id = e.city_id
        LEFT JOIN riders r ON r.id = e.rider_id
        WHERE 1=1";
$params = [];
if ($filterCity !== '')   { $sql .= " AND e.city_id = ?"; $params[] = $filterCity; }
if ($filterType !== '')   { $sql .= " AND e.type = ?"; $params[] = $filterType; }
if ($filterStatus !== '') { $sql .= " AND e.status = ?"; $params[] = $filterStatus; }
$sql .= " ORDER BY e.action_date DESC, e.id DESC LIMIT 300";
$actions = fetchAll($sql, $params);

$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");

$totalActions = (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions");
$totalPending = (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions WHERE status = 'pending'");
$totalImpounded = (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions WHERE type = 'impound'");
$totalFinesValue = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM enforcement_actions WHERE type = 'fine'");

$byType = fetchAll("SELECT type, COUNT(*) AS c FROM enforcement_actions GROUP BY type");
$typeMap = array_column($byType, 'c', 'type');

$byCity = fetchAll("SELECT c.name, COUNT(e.id) AS c FROM cities c LEFT JOIN enforcement_actions e ON e.city_id = c.id GROUP BY c.id ORDER BY c ASC");

$repeatOffenders = fetchAll("SELECT r.id, r.full_name, r.bike_plate, c.name AS city_name, COUNT(e.id) AS action_count
    FROM enforcement_actions e
    JOIN riders r ON r.id = e.rider_id
    LEFT JOIN cities c ON c.id = r.city_id
    GROUP BY r.id HAVING action_count > 1 ORDER BY action_count DESC LIMIT 10");

$agencyLabel = $_SESSION['agency_name'] ?? 'Government Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'National Enforcement | BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'National enforcement';
    $pageSubtitle = 'Actions, violations and impoundments across all cities';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-gavel"></i></div><div><div class="stat-hero-value"><?= number_format($totalActions) ?></div><div class="stat-hero-label">Total Actions</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-hero-value"><?= number_format($totalPending) ?></div><div class="stat-hero-label">Pending</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-motorcycle"></i></div><div><div class="stat-hero-value"><?= number_format($totalImpounded) ?></div><div class="stat-hero-label">Bikes Impounded</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value">UGX <?= number_format($totalFinesValue) ?></div><div class="stat-hero-label">Fines Issued</div></div></div></div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="section-heading mb-3">Violations by Type</h6>
                        <div id="typeChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="section-heading mb-3">Enforcement Actions by City</h6>
                        <div id="cityChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Repeat Offenders</h6>
                        <?php if (!$repeatOffenders): ?>
                            <p class="text-muted small mb-0">No repeat offenders recorded.</p>
                        <?php else: ?>
                        <div>
                        <?php foreach ($repeatOffenders as $o): ?>
                            <div class="activity-row">
                                <div class="icon-badge red" style="width:34px;height:34px;font-size:0.85rem;"><i class="fas fa-motorcycle"></i></div>
                                <div class="flex-1" style="min-width:0;">
                                    <div class="activity-title"><?= h($o['full_name']) ?> · <?= h($o['bike_plate']) ?></div>
                                    <div class="activity-sub"><?= h($o['city_name'] ?? '–') ?></div>
                                </div>
                                <span class="badge bg-danger" style="flex-shrink:0;"><?= (int) $o['action_count'] ?>x</span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Violation Type Breakdown</h6>
                        <?php $typeIcons = ['warning'=>['fa-exclamation-circle','warning'],'fine'=>['fa-money-bill-wave','danger'],'suspension'=>['fa-ban','dark'],'impound'=>['fa-motorcycle','danger']]; ?>
                        <?php foreach (['warning','fine','suspension','impound'] as $t): [$icon, $color] = $typeIcons[$t]; ?>
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span><i class="fas <?= $icon ?> text-<?= $color ?> me-2"></i><?= ucfirst($t) ?></span>
                                <strong><?= number_format($typeMap[$t] ?? 0) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0">All Enforcement Actions</h6>
                    <form class="d-flex gap-2 flex-wrap" method="get">
                        <select class="form-select form-select-sm" style="width:auto;" name="city" data-autosubmit>
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?><option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm" style="width:auto;" name="type" data-autosubmit>
                            <option value="">All Types</option>
                            <?php foreach (['warning','fine','suspension','impound'] as $t): ?><option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm" style="width:auto;" name="status" data-autosubmit>
                            <option value="">All Status</option>
                            <?php foreach (['pending','resolved','closed'] as $s): ?><option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                        <?php $exportTableId='enforcementTable'; $exportFilename='national_enforcement'; $exportTitle='National Enforcement Actions'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" id="enforcementTable">
                        <thead><tr><th>Code</th><th>Rider / Plate</th><th>City</th><th>Type</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php $typeColors = ['warning'=>'warning','fine'=>'danger','suspension'=>'dark','impound'=>'danger']; $statusColors = ['pending'=>'warning','resolved'=>'success','closed'=>'secondary']; ?>
                        <?php foreach ($actions as $a): ?>
                            <tr>
                                <td><code><?= h($a['action_code']) ?></code></td>
                                <td style="font-size:0.82rem;"><?= h($a['rider_name'] ?? '–') ?><br><small class="text-muted"><?= h($a['plate'] ?: '–') ?></small></td>
                                <td style="font-size:0.82rem;"><?= h($a['city_name'] ?? '–') ?></td>
                                <td><span class="badge bg-<?= $typeColors[$a['type']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= strtoupper($a['type']) ?></span></td>
                                <td style="font-size:0.82rem;"><?= $a['amount'] ? 'UGX ' . number_format($a['amount']) : '–' ?></td>
                                <td style="font-size:0.8rem;"><?= formatDate($a['action_date']) ?></td>
                                <td><span class="badge bg-<?= $statusColors[$a['status']] ?? 'secondary' ?>"><?= strtoupper($a['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$actions): ?>
                    <div class="empty-state">
                        <i class="fas fa-gavel"></i>
                        <h5>No enforcement actions found</h5>
                        <p>Try adjusting your filters.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#typeChart'), {
        series: [<?= (int)($typeMap['warning'] ?? 0) ?>, <?= (int)($typeMap['fine'] ?? 0) ?>, <?= (int)($typeMap['suspension'] ?? 0) ?>, <?= (int)($typeMap['impound'] ?? 0) ?>],
        chart: { type: 'donut', height: 240, animations: { enabled: true } },
        labels: ['Warning', 'Fine', 'Suspension', 'Impound'],
        colors: ['#f59e0b', '#dc3545', '#1a1a2e', '#a71d2a'],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => '<?= number_format($totalActions) ?>' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (v) => Number(v).toLocaleString() } }
    }).render();

    new ApexCharts(document.querySelector('#cityChart'), {
        series: [{ name: 'Actions', data: <?= js_json(array_map(fn($c) => (int) $c['c'], $byCity)) ?> }],
        chart: { type: 'bar', height: 240, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        colors: ['#056CFE', '#198754', '#f59e0b', '#0dcaf0', '#dc3545', '#001740'],
        xaxis: { categories: <?= js_json(array_map(fn($c) => $c['name'], $byCity)) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        tooltip: { theme: 'dark' }
    }).render();

</script>
</body>
</html>
