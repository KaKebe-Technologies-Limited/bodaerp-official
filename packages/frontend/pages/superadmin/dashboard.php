<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);
$active = 'dashboard';

$totalCities  = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'active'");
$totalRiders  = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
$totalStages  = (int) fetchValue("SELECT COUNT(*) FROM stages");
$totalUsers   = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
$totalRevenue = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Confirmed'");

$cities = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.status = 'active' AND r.deleted_at IS NULL) AS active_rider_count,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = c.id AND p.status = 'Confirmed') AS revenue_collected
    FROM cities c ORDER BY c.created_at");

$splitCityTotal = 0; $splitAssocTotal = 0; $splitGovTotal = 0; $splitPlatformTotal = 0;
foreach ($cities as &$c) {
    $c['compliance_pct'] = $c['rider_count'] ? round(100 * $c['active_rider_count'] / $c['rider_count']) : 0;
    $splitCityTotal     += $c['revenue_collected'] * $c['revenue_split_city'] / 100;
    $splitAssocTotal    += $c['revenue_collected'] * $c['revenue_split_association'] / 100;
    $splitGovTotal      += $c['revenue_collected'] * $c['revenue_split_government'] / 100;
    $splitPlatformTotal += $c['revenue_collected'] * $c['revenue_split_platform'] / 100;
}
unset($c);

$monthlyRows = fetchAll(
    "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total FROM payments
     WHERE status = 'Confirmed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY ym"
);
$monthlyByYm = array_column($monthlyRows, 'total', 'ym');
$chartLabels = []; $chartValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M', strtotime("-$i months"));
    $chartValues[] = round((float) ($monthlyByYm[$ym] ?? 0) / 1000000, 2);
}

$statusCounts = fetchAll("SELECT status, COUNT(*) AS c FROM riders WHERE deleted_at IS NULL GROUP BY status");
$statusMap = array_column($statusCounts, 'c', 'status');
$activeCount  = (int) ($statusMap['active'] ?? 0);
$expiredCount = (int) ($statusMap['expired'] ?? 0);
$pendingCount = (int) ($statusMap['pending'] ?? 0);

$recentUsers = fetchAll("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5");
$roleColors = ['super_admin' => 'primary', 'city_admin' => 'success', 'chairperson' => 'warning', 'rider' => 'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Platform overview | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .chart-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white; height: 100%; }
        .section-heading { font-weight: 700; font-size: 0.95rem; margin-bottom: 0; }
        .city-cell-logo { width: 30px; height: 30px; object-fit: contain; background: white; border-radius: 6px; }
        .compliance-bar { height: 5px; border-radius: 3px; background: #eef1f4; overflow: hidden; width: 70px; display: inline-block; vertical-align: middle; }
        .compliance-bar span { display: block; height: 100%; background: #198754; }
        .table-search-wrap { position: relative; width: 220px; max-width: 100%; }
        .table-search-wrap input { padding-left: 30px; }
        .table-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #adb5bd; }
        #citiesOverviewTable tbody tr.d-none-search { display: none; }
        @media (max-width: 576px) { .table-search-wrap { width: 100%; } }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php ob_start(); ?>
<a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-primary btn-sm">Add a city</a>
    <?php $headerActions = ob_get_clean();
    $pageTitle = 'Platform overview';
    $pageSubtitle = 'All cities on BodaERP';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">

        <div class="dash-banner theme-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>Platform overview</h4>
                    <p>Overseeing <?= $totalCities ?> cities, <?= number_format($totalRiders) ?> riders and <?= $totalStages ?> stages across BodaERP.</p>
                </div>
                <?php $attentionCount = count(automation_health_problems()); if ($attentionCount > 0): ?>
                    <a class="dash-meta" href="<?= BASE_URL ?>/pages/superadmin/system.php?tab=automation"><?= $attentionCount ?> <?= $attentionCount === 1 ? 'item needs' : 'items need' ?> your attention</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= $totalCities ?></div><div class="stat-hero-label">Active cities</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= $totalStages ?></div><div class="stat-hero-label">Total stages</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div><div class="stat-hero-value"><?= $totalUsers ?></div><div class="stat-hero-label">Platform users</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= $totalRevenue >= 1000000 ? number_format($totalRevenue / 1000000, 1) . 'M' : number_format($totalRevenue) ?></div><div class="stat-hero-label">Revenue (UGX)</div></div></div></div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-8">
                <div class="chart-card"><div class="card-body">
                    <h6 class="section-heading mb-3">Platform revenue overview (last 12 months)</h6>
                    <div id="revenueChart"></div>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="chart-card"><div class="card-body">
                    <h6 class="section-heading mb-3">Revenue split (all cities)</h6>
                    <div id="splitChart"></div>
                    <div class="mt-2">
                        <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-primary me-2"></span>City councils</span><span class="fw-bold small">UGX <?= number_format(round($splitCityTotal)) ?></span></div>
                        <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-success me-2"></span>Associations</span><span class="fw-bold small">UGX <?= number_format(round($splitAssocTotal)) ?></span></div>
                        <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot me-2" style="background:#b45309;"></span>Government</span><span class="fw-bold small">UGX <?= number_format(round($splitGovTotal)) ?></span></div>
                        <div class="d-flex justify-content-between align-items-center py-1"><span><span class="legend-dot bg-info me-2"></span>Platform</span><span class="fw-bold small">UGX <?= number_format(round($splitPlatformTotal)) ?></span></div>
                    </div>
                </div></div>
            </div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-7">
                <div class="chart-card"><div class="card-body">
                    <h6 class="section-heading mb-3">Riders by city</h6>
                    <div id="ridersByCityChart"></div>
                </div></div>
            </div>
            <div class="col-xl-5">
                <div class="chart-card"><div class="card-body">
                    <h6 class="section-heading mb-3">Compliance status</h6>
                    <div id="complianceChart"></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0">Registered cities</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="table-search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control form-control-sm" id="citySearchInput" placeholder="Search cities..." aria-label="Search cities">
                        </div>
                        <a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-outline-primary btn-sm">Manage All</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" id="citiesOverviewTable">
                        <thead><tr><th>City</th><th>Status</th><th>Stages</th><th>Riders</th><th>Compliance</th><th>Revenue collected</th><th>Split C/A/G/P</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($cities as $t): $statusColor = $t['status'] === 'active' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'secondary'); ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img alt="" src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" data-fallback-src="<?= BASE_URL ?>/assets/images/logo-mark.png">
                                        <div><strong style="font-size:0.85rem;"><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?></small></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?= $statusColor ?>" style="font-size:0.62rem;"><?= h(ucfirst(str_replace('_', ' ', $t['status']))) ?></span></td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td>
                                    <span class="compliance-bar"><span style="width:<?= $t['compliance_pct'] ?>%;"></span></span>
                                    <small class="text-muted ms-1"><?= $t['compliance_pct'] ?>%</small>
                                </td>
                                <td style="font-size:0.82rem;"><?= h($t['currency']) ?> <?= number_format($t['revenue_collected']) ?></td>
                                <td style="font-size:0.72rem;white-space:nowrap;">
                                    <span class="badge bg-primary"><?= (int) $t['revenue_split_city'] ?></span>
                                    <span class="badge bg-success"><?= (int) $t['revenue_split_association'] ?></span>
                                    <span class="badge" style="background:#b45309;"><?= (int) $t['revenue_split_government'] ?></span>
                                    <span class="badge bg-info text-dark"><?= (int) $t['revenue_split_platform'] ?></span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/pages/superadmin/cities.php#row-<?= h($t['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php#card-<?= h($t['id']) ?>" class="btn btn-sm btn-outline-warning" title="Revenue Splits"><i class="fas fa-percentage"></i></a>
                                    <a href="<?= BASE_URL ?>/pages/superadmin/users.php?city=<?= h($t['id']) ?>" class="btn btn-sm btn-outline-success" title="Users"><i class="fas fa-users"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="text-center py-4 text-muted d-none" id="citySearchEmpty">No cities match your search.</div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-7">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold mb-3">Recent users added</h6>
                    <div>
                    <?php foreach ($recentUsers as $u): ?>
                        <div class="activity-row">
                            <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" alt="<?= h($u['name']) ?>">
                            <div class="flex-1" style="min-width:0;">
                                <div class="activity-title"><?= h($u['name']) ?></div>
                                <div class="activity-sub"><?= h($u['email']) ?></div>
                            </div>
                            <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:0.6rem;flex-shrink:0;"><?= h(role_label($u['role'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/superadmin/users.php" class="btn btn-sm btn-outline-primary mt-2">All Users</a>
                </div></div>
            </div>
            <div class="col-md-5">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold mb-3">Quick actions</h6>
                    <div class="row g-2">
                        <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/platform-reports.php" class="text-decoration-none"><div class="quick-action-card"><h6>Reports</h6></div></a></div>
                        <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/audit-logs.php" class="text-decoration-none"><div class="quick-action-card"><h6>Audit logs</h6></div></a></div>
                        <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php" class="text-decoration-none"><div class="quick-action-card"><h6>Rev. splits</h6></div></a></div>
                        <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/platform-settings.php" class="text-decoration-none"><div class="quick-action-card"><h6>Settings</h6></div></a></div>
                    </div>
                </div></div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    new ApexCharts(document.querySelector('#revenueChart'), {
        series: [{ name: 'Revenue (UGX M)', data: <?= js_json($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#056CFE'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= js_json($chartLabels) ?>, labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        yaxis: { labels: { formatter: (val) => 'UGX ' + val + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        colors: ['#056CFE'],
        tooltip: { theme: 'dark', y: { formatter: (val) => 'UGX ' + val + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#splitChart'), {
        series: [<?= round($splitCityTotal) ?>, <?= round($splitAssocTotal) ?>, <?= round($splitGovTotal) ?>, <?= round($splitPlatformTotal) ?>],
        chart: { type: 'donut', height: 200 },
        labels: ['City Councils', 'Associations', 'Government', 'Platform'],
        colors: ['#056CFE', '#198754', '#d97706', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total Revenue', formatter: () => 'UGX ' + <?= round($totalRevenue / 1000000, 1) ?> + 'M' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val) => 'UGX ' + Number(val).toLocaleString() } }
    }).render();

    new ApexCharts(document.querySelector('#ridersByCityChart'), {
        series: [{ name: 'Riders', data: <?= js_json(array_map(fn($c) => (int) $c['rider_count'], $cities)) ?> }],
        chart: { type: 'bar', height: 260, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        colors: ['#056CFE', '#198754', '#f59e0b', '#0dcaf0', '#dc3545', '#001740'],
        xaxis: { categories: <?= js_json(array_map(fn($c) => $c['name'], $cities)) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        tooltip: { theme: 'dark' }
    }).render();

    new ApexCharts(document.querySelector('#complianceChart'), {
        series: [<?= $activeCount ?>, <?= $expiredCount ?>, <?= $pendingCount ?>],
        chart: { type: 'donut', height: 260 },
        labels: ['Active', 'Expired', 'Pending'],
        colors: ['#198754', '#dc3545', '#f59e0b'],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total Riders', formatter: () => '<?= number_format($totalRiders) ?>' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: true, formatter: (val) => Math.round(val) + '%' },
        tooltip: { y: { formatter: (val) => Number(val).toLocaleString() + ' riders' } }
    }).render();

    document.getElementById('citySearchInput')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('#citiesOverviewTable tbody tr');
        let visible = 0;
        rows.forEach(row => {
            const match = row.textContent.toLowerCase().includes(q);
            row.classList.toggle('d-none-search', !match);
            if (match) visible++;
        });
        document.getElementById('citySearchEmpty').classList.toggle('d-none', visible !== 0);
    });

</script>
</body>
</html>
