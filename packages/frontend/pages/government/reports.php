<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['government']);
$active = 'reports';

$rows = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = c.id AND p.status = 'Confirmed') AS revenue_collected
    FROM cities c ORDER BY c.name");

$totalRiders = 0; $totalRevenue = 0; $totalGovShare = 0;
foreach ($rows as &$r) {
    $r['government_share'] = $r['revenue_collected'] * ($r['revenue_split_government'] / 100);
    $totalRiders += $r['rider_count'];
    $totalRevenue += $r['revenue_collected'];
    $totalGovShare += $r['government_share'];
}
unset($r);

$genderRows = fetchAll("SELECT gender, COUNT(*) AS c FROM riders WHERE deleted_at IS NULL AND gender IS NOT NULL GROUP BY gender");
$genderMap = array_column($genderRows, 'c', 'gender');

$statusRows = fetchAll("SELECT status, COUNT(*) AS c FROM riders WHERE deleted_at IS NULL GROUP BY status");
$statusMap = array_column($statusRows, 'c', 'status');

$agencyLabel = $_SESSION['agency_name'] ?? 'Government Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'National Reports - BodaERP Government Portal'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">National Reports</span></h5>
                    <small class="text-muted d-none d-sm-block">Revenue, demographics, and performance — <?= h($agencyLabel) ?></small>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-city"></i></div><div><div class="stat-hero-value"><?= count($rows) ?></div><div class="stat-hero-label">Total Cities</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value">UGX <?= $totalRevenue >= 1000000 ? number_format($totalRevenue/1000000,1).'M' : number_format($totalRevenue) ?></div><div class="stat-hero-label">National Revenue</div></div></div></div>
            <div class="col"><div class="stat-hero-card" style="background:linear-gradient(135deg,#e0529c,#a02a6b);"><div class="stat-hero-icon"><i class="fas fa-university"></i></div><div><div class="stat-hero-value">UGX <?= $totalGovShare >= 1000000 ? number_format($totalGovShare/1000000,1).'M' : number_format(round($totalGovShare)) ?></div><div class="stat-hero-label">Government Share</div></div></div></div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-venus-mars text-primary me-2"></i>Rider Demographics (Gender)</h6>
                        <div id="genderChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-check-circle text-primary me-2"></i>Registration Status</h6>
                        <div id="statusChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar text-primary me-2"></i>National Revenue Report by City</h6>
                    <?php $exportTableId='natReportsTable'; $exportFilename='national_revenue_report'; $exportTitle='National Revenue Report'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="natReportsTable">
                        <thead><tr><th>City</th><th>Riders</th><th>Stages</th><th>Annual Fee</th><th>Revenue Collected</th><th>Government Share</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $t): $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><img src="<?= h(BASE_URL . $t['logo_path']) ?>" width="28" height="28" class="rounded border" style="object-fit:contain;background:white;border-radius:8px!important;" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'"><strong><?= h($t['name']) ?></strong></div></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></td>
                                <td><?= h($t['currency']) ?> <?= number_format(round($t['government_share'])) ?></td>
                                <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= strtoupper($t['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#genderChart'), {
        series: [<?= (int)($genderMap['Male'] ?? 0) ?>, <?= (int)($genderMap['Female'] ?? 0) ?>, <?= (int)($genderMap['Other'] ?? 0) ?>],
        chart: { type: 'donut', height: 220 },
        labels: ['Male', 'Female', 'Other'],
        colors: ['#5b4bd5', '#e5484d', '#e0529c'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
        stroke: { width: 0 },
    }).render();

    new ApexCharts(document.querySelector('#statusChart'), {
        series: [<?= (int)($statusMap['active'] ?? 0) ?>, <?= (int)($statusMap['expired'] ?? 0) ?>, <?= (int)($statusMap['pending'] ?? 0) ?>],
        chart: { type: 'donut', height: 220 },
        labels: ['Active', 'Expired', 'Pending'],
        colors: ['#198754', '#dc3545', '#f59e0b'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
        stroke: { width: 0 },
    }).render();

    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
