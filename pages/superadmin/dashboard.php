<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

$pageTitle = 'Platform Overview';
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

$splitCityTotal = 0; $splitAssocTotal = 0; $splitPlatformTotal = 0;
foreach ($cities as &$c) {
    $c['compliance_pct'] = $c['rider_count'] ? round(100 * $c['active_rider_count'] / $c['rider_count']) : 0;
    $splitCityTotal     += $c['revenue_collected'] * $c['revenue_split_city'] / 100;
    $splitAssocTotal    += $c['revenue_collected'] * $c['revenue_split_association'] / 100;
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
    <?php $pageTitle = 'Super Admin - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .role-badge-sa { background: linear-gradient(135deg,#0d6efd,#1a3a5c); color: white; font-size: 0.65rem; padding: 3px 12px; border-radius: 20px; font-weight: 700; }
        .chart-card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); background: white; height: 100%; }
        .section-heading { font-weight: 700; font-size: 0.95rem; margin-bottom: 0; }
        .city-cell-logo { width: 30px; height: 30px; object-fit: contain; background: white; border-radius: 6px; }
        .compliance-bar { height: 5px; border-radius: 3px; background: #eef1f4; overflow: hidden; width: 70px; display: inline-block; vertical-align: middle; }
        .compliance-bar span { display: block; height: 100%; background: #198754; }
        .table-search-wrap { position: relative; width: 220px; max-width: 100%; }
        .table-search-wrap input { padding-left: 30px; }
        .table-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #adb5bd; }
        #citiesOverviewTable tbody tr.d-none-search { display: none; }
        @media (max-width: 576px) {
            .table-search-wrap { width: 100%; }
        }
    </style>
</head>
<body>

<?php $active = 'dashboard'; require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Platform Overview</span></h5>
                    <small class="text-muted d-none d-sm-block">Kakebe Technologies — Multi-City Dashboard</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-wrap">
                <a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">Add </span>City</a>
                <div class="user-info">
                    <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" class="rounded-circle" width="40" height="40">
                    <div>
                        <span class="fw-bold d-block" style="font-size:0.85rem;"><?= h($_SESSION['name']) ?></span>
                        <span class="role-badge-sa">Platform Admin</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="dash-banner theme-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>⚙️ Platform Command Center</h4>
                    <p>Overseeing <?= $totalCities ?> cities, <?= number_format($totalRiders) ?> riders and <?= $totalStages ?> stages across BodaERP.</p>
                </div>
                <span class="dash-badge"><i class="fas fa-signal me-1"></i>All systems operational</span>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-md-3 row-cols-xl-5 mb-4">
            <div class="col"><div class="stat-card-modern"><div class="icon-badge blue"><i class="fas fa-city"></i></div><div><span class="stat-number"><?= $totalCities ?></span><div class="stat-label">Active Cities</div></div></div></div>
            <div class="col"><div class="stat-card-modern"><div class="icon-badge green"><i class="fas fa-users"></i></div><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-card-modern"><div class="icon-badge gold"><i class="fas fa-map-marker-alt"></i></div><div><span class="stat-number"><?= $totalStages ?></span><div class="stat-label">Total Stages</div></div></div></div>
            <div class="col"><div class="stat-card-modern"><div class="icon-badge dark"><i class="fas fa-user-shield"></i></div><div><span class="stat-number"><?= $totalUsers ?></span><div class="stat-label">Platform Users</div></div></div></div>
            <div class="col"><div class="stat-card-modern"><div class="icon-badge red"><i class="fas fa-money-bill-wave"></i></div><div><span class="stat-number" style="font-size:1.1rem;"><?= $totalRevenue >= 1000000 ? number_format($totalRevenue / 1000000, 1) . 'M' : number_format($totalRevenue) ?></span><div class="stat-label">Revenue (UGX)</div></div></div></div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-8">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-chart-area text-primary me-2"></i>Platform Revenue Overview (Last 12 Months)</h6>
                        <div id="revenueChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-chart-pie text-primary me-2"></i>Revenue Split (All Cities)</h6>
                        <div id="splitChart"></div>
                        <div class="mt-2">
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-primary me-2">●</span>City Councils</span><span class="fw-bold small">UGX <?= number_format(round($splitCityTotal)) ?></span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-success me-2">●</span>Associations</span><span class="fw-bold small">UGX <?= number_format(round($splitAssocTotal)) ?></span></div>
                            <div class="d-flex justify-content-between align-items-center py-1"><span><span class="badge bg-info text-dark me-2">●</span>Kakebe Tech</span><span class="fw-bold small">UGX <?= number_format(round($splitPlatformTotal)) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-md-4 mb-4">
            <div class="col-xl-7">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Riders by City</h6>
                        <div id="ridersByCityChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="chart-card">
                    <div class="card-body">
                        <h6 class="section-heading mb-3"><i class="fas fa-check-circle text-primary me-2"></i>Compliance Status</h6>
                        <div id="complianceChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-city text-primary me-2"></i>Registered Cities</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="table-search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control form-control-sm" id="citySearchInput" placeholder="Search cities...">
                        </div>
                        <a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-cog me-1"></i>Manage All</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle" id="citiesOverviewTable">
                        <thead><tr><th>City</th><th>Status</th><th>Stages</th><th>Riders</th><th>Compliance</th><th>Revenue Collected</th><th>Split C/A/P</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($cities as $t): $statusColor = $t['status'] === 'active' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'secondary'); ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= h(BASE_URL . $t['logo_path']) ?>" class="city-cell-logo border" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                                        <div><strong style="font-size:0.85rem;"><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?></small></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?= $statusColor ?>" style="font-size:0.62rem;"><?= strtoupper($t['status']) ?></span></td>
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
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-users text-primary me-2"></i>Recent Users Added</h6>
                        <div>
                        <?php foreach ($recentUsers as $u): ?>
                            <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" width="32" height="32" class="rounded-circle">
                                <div class="flex-1">
                                    <div class="fw-bold" style="font-size:0.82rem;"><?= h($u['name']) ?></div>
                                    <small class="text-muted"><?= h($u['email']) ?></small>
                                </div>
                                <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:0.6rem;"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <a href="<?= BASE_URL ?>/pages/superadmin/users.php" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-list me-1"></i>All Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-bolt text-primary me-2"></i>Quick Actions</h6>
                        <div class="row g-2">
                            <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/platform-reports.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge blue"><i class="fas fa-chart-line"></i></div><h6>Reports</h6></div></a></div>
                            <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/audit-logs.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge dark"><i class="fas fa-history"></i></div><h6>Audit Logs</h6></div></a></div>
                            <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge gold"><i class="fas fa-percentage"></i></div><h6>Rev. Splits</h6></div></a></div>
                            <div class="col-6"><a href="<?= BASE_URL ?>/pages/superadmin/platform-settings.php" class="text-decoration-none"><div class="quick-action-card"><div class="icon-badge green"><i class="fas fa-cog"></i></div><h6>Settings</h6></div></a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#revenueChart'), {
        series: [{ name: 'Revenue (UGX M)', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false }, animations: { enabled: true } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#0d6efd'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= json_encode($chartLabels) ?>, labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        yaxis: { labels: { formatter: (val) => 'UGX ' + val + 'M', style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        colors: ['#0d6efd'],
        tooltip: { theme: 'dark', y: { formatter: (val) => 'UGX ' + val + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#splitChart'), {
        series: [<?= round($splitCityTotal) ?>, <?= round($splitAssocTotal) ?>, <?= round($splitPlatformTotal) ?>],
        chart: { type: 'donut', height: 200, animations: { enabled: true } },
        labels: ['City Councils', 'Associations', 'Kakebe Tech'],
        colors: ['#0d6efd', '#198754', '#0dcaf0'],
        legend: { show: false },
        plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total Revenue', formatter: () => 'UGX ' + <?= round($totalRevenue / 1000000, 1) ?> + 'M' } } } } },
        stroke: { width: 0 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val) => 'UGX ' + Number(val).toLocaleString() } }
    }).render();

    new ApexCharts(document.querySelector('#ridersByCityChart'), {
        series: [{ name: 'Riders', data: <?= json_encode(array_map(fn($c) => (int) $c['rider_count'], $cities)) ?> }],
        chart: { type: 'bar', height: 260, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: false },
        legend: { show: false },
        colors: ['#0d6efd', '#198754', '#f59e0b', '#0dcaf0', '#dc3545', '#6f42c1'],
        xaxis: { categories: <?= json_encode(array_map(fn($c) => $c['name'], $cities)) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6c757d', fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
        tooltip: { theme: 'dark' }
    }).render();

    new ApexCharts(document.querySelector('#complianceChart'), {
        series: [<?= $activeCount ?>, <?= $expiredCount ?>, <?= $pendingCount ?>],
        chart: { type: 'donut', height: 260, animations: { enabled: true } },
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
