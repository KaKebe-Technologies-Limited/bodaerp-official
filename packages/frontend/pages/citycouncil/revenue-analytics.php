<?php
require_once "/var/www/html/packages/backend/auth.php";
require_role(['city_admin']);
$active = 'revenue-analytics';
$cityId = $_SESSION['city_id'];
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$totalRevenue = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id=? AND status='Confirmed'", [$cityId]);
$thisMonth = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id=? AND status='Confirmed' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE())", [$cityId]);
$txnCount = (int) fetchValue("SELECT COUNT(*) FROM payments WHERE city_id=? AND status='Confirmed'", [$cityId]);
$avgPayment = $txnCount ? $totalRevenue / $txnCount : 0;

$monthlyRows = fetchAll("SELECT DATE_FORMAT(paid_at,'%Y-%m') ym, SUM(amount) total FROM payments WHERE city_id=? AND status='Confirmed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym", [$cityId]);
$monthlyByYm = array_column($monthlyRows, 'total', 'ym');
$chartLabels = []; $chartValues = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M', strtotime("-$i months"));
    $chartValues[] = round((float) ($monthlyByYm[$ym] ?? 0) / 1000000, 2);
}

$stageRevenue = fetchAll("SELECT s.name, COALESCE(SUM(p.amount),0) AS total FROM stages s
    LEFT JOIN riders r ON r.stage_id = s.id LEFT JOIN payments p ON p.rider_id = r.id AND p.status='Confirmed'
    WHERE s.city_id = ? GROUP BY s.id, s.name ORDER BY total DESC", [$cityId]);

$transactions = fetchAll("SELECT p.*, r.full_name, s.name AS stage_name FROM payments p
    JOIN riders r ON r.id = p.rider_id LEFT JOIN stages s ON s.id = r.stage_id
    WHERE p.city_id = ? ORDER BY p.paid_at DESC LIMIT 50", [$cityId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Revenue Analytics - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Revenue Analytics</span></h5>
            </div>
            <a href="<?= BASE_URL ?>/pages/citycouncil/revenue-report.php" class="btn btn-outline-primary btn-sm no-print"><i class="fas fa-file-alt me-1"></i>Annual Report</a>
        </div>
    </header>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($totalRevenue)) ?></div><div class="stat-hero-label">Total Revenue</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-calendar"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($thisMonth)) ?></div><div class="stat-hero-label">This Month</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-receipt"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency(round($avgPayment))) ?></div><div class="stat-hero-label">Avg. Payment</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exchange-alt"></i></div><div><div class="stat-hero-value"><?= number_format($txnCount) ?></div><div class="stat-hero-label">Transactions</div></div></div></div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card"><div class="card-body">
                    <h5 class="fw-bold mb-3"><i class="fas fa-chart-line text-primary me-2"></i>Revenue Trend (last 12 months)</h5>
                    <div id="revenueTrendChart"></div>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card"><div class="card-body">
                    <h5 class="fw-bold mb-3">Revenue Split</h5>
                    <div id="pieChart"></div>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="fas fa-map-marker-alt text-primary me-2"></i>Revenue by Stage</h5>
                <div id="stageRevenueChart"></div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 no-print">
            <h6 class="fw-bold mb-0">Recent Transactions</h6>
            <?php $exportTableId='analyticsTransTable'; $exportFilename='revenue_transactions'; $exportTitle='Revenue Transactions'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="analyticsTransTable">
                        <thead><tr><th>Receipt #</th><th>Rider</th><th>Stage</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($transactions as $t):
                            $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger'];
                        ?>
                            <tr>
                                <td><code><?= h($t['receipt_number']) ?></code></td>
                                <td><?= h($t['full_name']) ?></td>
                                <td><?= h($t['stage_name']) ?></td>
                                <td><?= h(formatCurrency($t['amount'])) ?></td>
                                <td><?= formatDate($t['paid_at']) ?></td>
                                <td><?= h($t['payment_method']) ?></td>
                                <td><span class="badge <?= $sc[$t['status']] ?? 'bg-secondary' ?>"><?= h($t['status']) ?></span></td>
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
    new ApexCharts(document.querySelector('#revenueTrendChart'), {
        series: [{ name: 'Revenue', data: <?= json_encode($chartValues) ?> }],
        chart: { type: 'area', height: 280, toolbar: { show: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3, colors: ['#5b4bd5'] },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis: { categories: <?= json_encode($chartLabels) ?> },
        yaxis: { labels: { formatter: v => 'UGX ' + v + 'M' } },
        colors: ['#5b4bd5'],
        tooltip: { y: { formatter: v => 'UGX ' + v + ' Million' } }
    }).render();

    new ApexCharts(document.querySelector('#pieChart'), {
        series: [<?= (int)$city['revenue_split_city'] ?>, <?= (int)$city['revenue_split_association'] ?>, <?= (int)$city['revenue_split_government'] ?>, <?= (int)$city['revenue_split_platform'] ?>],
        chart: { type: 'donut', height: 220 },
        labels: ['City Council', 'Boda Association', 'Government', 'Kakebe Tech'],
        colors: ['#5b4bd5', '#198754', '#e0529c', '#0dcaf0'],
        dataLabels: { enabled: true, formatter: v => Math.round(v) + '%' },
    }).render();

    new ApexCharts(document.querySelector('#stageRevenueChart'), {
        series: [{ name: 'Revenue (UGX)', data: <?= json_encode(array_map(fn($s) => (float)$s['total'], $stageRevenue)) ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
        colors: ['#198754'],
        dataLabels: { enabled: false },
        xaxis: { categories: <?= json_encode(array_column($stageRevenue, 'name')) ?> },
        yaxis: { labels: { formatter: v => 'UGX ' + Math.round(v/1000) + 'K' } },
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
