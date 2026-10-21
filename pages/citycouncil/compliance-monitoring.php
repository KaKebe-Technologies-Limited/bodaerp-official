<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'compliance-monitoring';
$cityId = $_SESSION['city_id'];

$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);
$stages = fetchAll("SELECT s.*, v.rider_count, v.active_count, v.expired_count, v.pending_count, v.compliance_pct
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id WHERE s.city_id = ? ORDER BY v.compliance_pct DESC", [$cityId]);

$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL", [$cityId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='active' AND deleted_at IS NULL", [$cityId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='expired' AND deleted_at IS NULL", [$cityId]);
$overallRate = $totalRiders ? round(100 * $activeRiders / $totalRiders) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Compliance Monitoring - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Compliance Monitoring</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= $overallRate ?>%</div><div class="stat-hero-label">Compliance Rate</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= number_format($activeRiders) ?></div><div class="stat-hero-label">Compliant Riders</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-hero-value"><?= number_format($defaulters) ?></div><div class="stat-hero-label">Non-Compliant</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="stat-hero-value"><?= count($stages) ?></div><div class="stat-hero-label">Stages Monitored</div></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Compliance by Stage <small class="text-muted">(target: <?= (int)$city['compliance_target'] ?>%)</small></h6>
                <div id="complianceChart"></div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='complianceTable'; $exportFilename='compliance_by_stage'; $exportTitle='Compliance Monitoring'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="complianceTable">
                        <thead><tr><th>Stage</th><th>Chairperson</th><th>Total Riders</th><th>Compliant</th><th>Non-Compliant</th><th>Compliance Rate</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($stages as $s):
                            $c = (float) ($s['compliance_pct'] ?? 0);
                            [$badge, $label] = $c >= 80 ? ['bg-success','Excellent'] : ($c >= 70 ? ['bg-primary','Good'] : ($c >= 50 ? ['bg-warning text-dark','Fair'] : ['bg-danger','Poor']));
                        ?>
                            <tr>
                                <td><strong><?= h($s['name']) ?></strong></td>
                                <td><?= h($s['chairperson_name'] ?: '—') ?></td>
                                <td><?= (int) $s['rider_count'] ?></td>
                                <td class="text-success"><?= (int) $s['active_count'] ?></td>
                                <td class="text-danger"><?= (int) ($s['expired_count'] + $s['pending_count']) ?></td>
                                <td><span class="fw-bold"><?= $c ?>%</span></td>
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

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    new ApexCharts(document.querySelector('#complianceChart'), {
        series: [{ name: 'Compliance %', data: <?= json_encode(array_map(fn($s) => (float)($s['compliance_pct'] ?? 0), $stages)) ?> }],
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%', distributed: true } },
        colors: ['#5b4bd5','#198754','#f59e0b','#dc3545','#0dcaf0','#6f42c1','#fd7e14','#20c997','#e83e8c','#6c757d'],
        legend: { show: false },
        dataLabels: { enabled: true, formatter: v => v + '%' },
        xaxis: { categories: <?= json_encode(array_column($stages, 'name')) ?>, labels: { style: { colors: '#6c757d', fontSize: '11px' } } },
        yaxis: { max: 100, labels: { formatter: v => v + '%' } },
        annotations: { yaxis: [{ y: <?= (int)$city['compliance_target'] ?>, borderColor: '#dc3545', label: { text: 'Target: <?= (int)$city['compliance_target'] ?>%', style: { color: '#fff', background: '#dc3545' } } }] },
        grid: { borderColor: '#f1f1f1' },
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
