<?php
require_once "/var/www/html/packages/backend/auth.php";
require_role(['city_admin']);
$active = 'city-defaulters';
$cityId = $_SESSION['city_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['rider_id'] ?? 0);
    $rider = fetchOne("SELECT id, user_id, full_name FROM riders WHERE id = ? AND city_id = ?", [$riderId, $cityId]);
    if ($rider) {
        if ($rider['user_id']) {
            runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, 'Renewal Reminder', ?)",
                [$rider['id'], 'Your annual tax is overdue. Please renew to remain compliant.']);
        }
        audit_log('CREATE', 'reminder', (string) $riderId, 'Reminder sent to ' . $rider['full_name']);
    }
    redirect('/pages/citycouncil/city-defaulters.php');
}

$stageFilter = (int) ($_GET['stage'] ?? 0);
$where = "WHERE r.city_id = :city AND r.status = 'expired' AND r.deleted_at IS NULL"; $params = ['city' => $cityId];
if ($stageFilter) { $where .= " AND r.stage_id = :stage"; $params['stage'] = $stageFilter; }

$defaulters = fetchAll(
    "SELECT r.*, s.name AS stage_name,
        (SELECT MAX(paid_at) FROM payments p WHERE p.rider_id = r.id AND p.status='Confirmed') AS last_payment,
        DATEDIFF(CURDATE(), r.expiry_date) AS overdue_days
     FROM riders r LEFT JOIN stages s ON s.id = r.stage_id
     $where ORDER BY overdue_days DESC", $params
);
$stages = fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]);

$totalDefaulters = count($defaulters);
$critical = count(array_filter($defaulters, fn($d) => $d['overdue_days'] > 90));
$warning = $totalDefaulters - $critical;
$amountOwed = array_sum(array_map(fn($d) => (float) $d['annual_tax'], $defaulters));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'City Defaulters - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
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
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">City Defaulters</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-hero-value"><?= number_format($totalDefaulters) ?></div><div class="stat-hero-label">Total Defaulters</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-fire"></i></div><div><div class="stat-hero-value"><?= number_format($critical) ?></div><div class="stat-hero-label">Critical (&gt;90 days)</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-clock"></i></div><div><div class="stat-hero-value"><?= number_format($warning) ?></div><div class="stat-hero-label">Warning</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-hero-value"><?= h(formatCurrency($amountOwed)) ?></div><div class="stat-hero-label">Amount Owed</div></div></div></div>
        </div>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <select class="form-select" name="stage" onchange="this.form.submit()">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"></div>
                    <div class="col-md-4 text-md-end">
                        <?php $exportTableId='defaultersTable'; $exportFilename='city_defaulters'; $exportTitle='City Defaulters'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="defaultersTable">
                        <thead><tr><th>#</th><th>Rider</th><th>Plate</th><th>Stage</th><th>Phone</th><th>Amount</th><th>Last Payment</th><th>Overdue</th><th>Status</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$defaulters): ?>
                            <tr><td colspan="10"><div class="empty-state" style="padding:36px 20px;"><i class="fas fa-inbox"></i><h5>No defaulters — great compliance!</h5></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($defaulters as $i => $d):
                            $days = (int) $d['overdue_days'];
                            [$badge, $label] = $days > 90 ? ['bg-danger','Critical'] : ($days > 60 ? ['bg-warning text-dark','Warning'] : ['bg-secondary','Normal']);
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= h($d['full_name']) ?></strong></td>
                                <td><?= h($d['bike_plate']) ?></td>
                                <td><?= h($d['stage_name']) ?></td>
                                <td><?= h($d['phone']) ?></td>
                                <td><?= h(formatCurrency($d['annual_tax'])) ?></td>
                                <td><?= $d['last_payment'] ? formatDate($d['last_payment']) : 'Never' ?></td>
                                <td><?= $days ?> days</td>
                                <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                                <td class="no-export">
                                    <form method="post" class="d-inline" onsubmit="return confirm('Send a renewal reminder to <?= h(addslashes($d['full_name'])) ?>?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="rider_id" value="<?= (int) $d['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-bell"></i> Remind</button>
                                    </form>
                                </td>
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
