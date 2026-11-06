<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
$active = 'stage-performance';
$cityId = $_SESSION['city_id'];

$stages = fetchAll("SELECT s.*, v.rider_count, v.compliance_pct,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.rider_id IN (SELECT id FROM riders WHERE stage_id=s.id) AND p.status='Confirmed') AS revenue
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id WHERE s.city_id = ?", [$cityId]);

$maxRevenue = max(array_column($stages, 'revenue')) ?: 1;
foreach ($stages as &$s) {
    $complianceScore = (float) ($s['compliance_pct'] ?? 0);
    $revenueScore = round(100 * $s['revenue'] / $maxRevenue);
    $s['performance'] = round(($complianceScore * 0.6) + ($revenueScore * 0.4));
}
unset($s);
usort($stages, fn($a, $b) => $b['performance'] <=> $a['performance']);

$best = $stages ? $stages[0]['performance'] : 0;
$avg = $stages ? round(array_sum(array_column($stages, 'performance')) / count($stages)) : 0;
$worst = $stages ? end($stages)['performance'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Stage performance | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'Stage performance';
    $pageSubtitle = '';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= $best ?>%</div><div class="stat-hero-label">Best performing</div></div></div></div>
            <div class="col-md-4"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= $avg ?>%</div><div class="stat-hero-label">Average score</div></div></div></div>
            <div class="col-md-4"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= $worst ?>%</div><div class="stat-hero-label">Needs attention</div></div></div></div>
        </div>

        <div class="card mb-4"><div class="card-body">
            <h6 class="fw-bold mb-3">Performance score by stage <small class="text-muted">(60% compliance + 40% revenue share)</small></h6>
            <div id="performanceChart"></div>
        </div></div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='performanceTable'; $exportFilename='stage_performance'; $exportTitle='Stage Performance Ranking'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="performanceTable">
                        <thead><tr><th>Rank</th><th>Stage</th><th>Chairperson</th><th>Riders</th><th>Revenue</th><th>Compliance</th><th>Performance</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($stages as $i => $s):
                            $p = $s['performance'];
                            [$badge, $label] = $p >= 80 ? ['bg-success','Excellent'] : ($p >= 60 ? ['bg-primary','Good'] : ($p >= 40 ? ['bg-warning text-dark','Fair'] : ['bg-danger','Poor']));
                        ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= $i+1 ?></span></td>
                                <td><strong><?= h($s['name']) ?></strong></td>
                                <td><?= h($s['chairperson_name'] ?: '–') ?></td>
                                <td><?= (int) $s['rider_count'] ?></td>
                                <td><?= h(formatCurrency($s['revenue'])) ?></td>
                                <td><?= (float)($s['compliance_pct'] ?? 0) ?>%</td>
                                <td><span class="fw-bold"><?= $p ?>%</span></td>
                                <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
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
<script nonce="<?= csp_nonce() ?>">
    new ApexCharts(document.querySelector('#performanceChart'), {
        series: [{ name: 'Performance %', data: <?= js_json(array_column($stages, 'performance')) ?> }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', distributed: true } },
        colors: ['#056CFE','#198754','#f59e0b','#dc3545','#0dcaf0','#001740','#fd7e14','#20c997','#e83e8c','#6c757d'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: v => v + '%' },
        xaxis: { categories: <?= js_json(array_column($stages, 'name')) ?> },
        yaxis: { max: 100 },
    }).render();

</script>
</body>
</html>
