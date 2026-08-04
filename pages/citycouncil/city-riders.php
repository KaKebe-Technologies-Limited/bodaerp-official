<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'city-riders';
$cityId = $_SESSION['city_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        runQuery("DELETE FROM riders WHERE id = ? AND city_id = ?", [$riderId, $cityId]);
        audit_log('DELETE', 'rider', (string) $riderId, 'Rider deleted');
    } else {
        $status = $_POST['status'] ?? 'pending';
        $stageId = (int) ($_POST['stage_id'] ?? 0);
        runQuery("UPDATE riders SET full_name=?,phone=?,stage_id=?,status=?,expiry_date=? WHERE id=? AND city_id=?", [
            trim($_POST['full_name'] ?? ''), trim($_POST['phone'] ?? ''), $stageId, $status,
            $_POST['expiry_date'] ?: null, $riderId, $cityId,
        ]);
        audit_log('UPDATE', 'rider', (string) $riderId, 'Rider updated');
    }
    redirect('/pages/citycouncil/city-riders.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
}

$search = trim($_GET['q'] ?? '');
$stageFilter = (int) ($_GET['stage'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = "WHERE r.city_id = :city"; $params = ['city' => $cityId];
if ($search !== '') { $where .= " AND (r.full_name LIKE :q1 OR r.bike_plate LIKE :q2 OR r.phone LIKE :q3 OR r.id_number LIKE :q4)"; $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = "%$search%"; }
if ($stageFilter) { $where .= " AND r.stage_id = :stage"; $params['stage'] = $stageFilter; }
if ($statusFilter) { $where .= " AND r.status = :status"; $params['status'] = $statusFilter; }
$orderBy = match ($sort) {
    'name-desc' => 'r.full_name DESC', 'latest' => 'r.id DESC', 'oldest' => 'r.id ASC', default => 'r.full_name ASC',
};

if (isset($_GET['export'])) {
    $exportRows = fetchAll("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id = r.stage_id $where ORDER BY $orderBy", $params);
    $filename = 'city_riders_' . date('Ymd_His');
    $format = $_GET['export'];
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.xls\"");
        echo "<table border='1'><tr><th>ID</th><th>Rider</th><th>Plate</th><th>Stage</th><th>Phone</th><th>Status</th><th>Expiry</th></tr>";
        foreach ($exportRows as $r) {
            echo '<tr><td>' . h($r['id_number']) . '</td><td>' . h($r['full_name']) . '</td><td>' . h($r['bike_plate']) . '</td><td>' . h($r['stage_name']) . '</td><td>' . h($r['phone']) . '</td><td>' . ucfirst($r['status']) . '</td><td>' . formatDate($r['expiry_date']) . "</td></tr>\n";
        }
        echo '</table>';
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Rider', 'Plate', 'Stage', 'Phone', 'Status', 'Expiry']);
    foreach ($exportRows as $r) {
        fputcsv($out, [$r['id_number'], $r['full_name'], $r['bike_plate'], $r['stage_name'], $r['phone'], ucfirst($r['status']), formatDate($r['expiry_date'])]);
    }
    fclose($out);
    exit;
}

$total = (int) fetchValue("SELECT COUNT(*) FROM riders r $where", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$riders = fetchAll("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id = r.stage_id $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);
$stages = fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]);

$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ?", [$cityId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='active'", [$cityId]);
$expiredRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='expired'", [$cityId]);
$stageCount = count($stages);
$ridersById = array_column($riders, null, 'id');

function qsMerge(array $overrides): string {
    return '?' . http_build_query(array_merge($_GET, $overrides));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'City Riders - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .council-stat-card { background: white; border-radius: 14px; padding: 16px 20px; border-left: 4px solid #0d6efd; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        .council-stat-card .stat-number { font-size: 1.6rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
        .council-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .council-stat-card .stat-icon { font-size: 1.6rem; opacity: 0.15; }
        .council-stat-card.blue { border-left-color: #0d6efd; }
        .council-stat-card.green { border-left-color: #198754; }
        .council-stat-card.red { border-left-color: #dc3545; }
        .council-stat-card.gold { border-left-color: #f59e0b; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .status-dot.active { background: #198754; } .status-dot.expired { background: #dc3545; } .status-dot.pending { background: #ffc107; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">All Riders</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="council-stat-card blue"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div><i class="fas fa-users stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card green"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($activeRiders) ?></span><div class="stat-label">Active (Paid)</div></div><i class="fas fa-check-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card red"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($expiredRiders) ?></span><div class="stat-label">Expired</div></div><i class="fas fa-exclamation-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $stageCount ?></span><div class="stat-label">Stages</div></div><i class="fas fa-map-marker-alt stat-icon"></i></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-center" method="get">
                    <div class="col-md-3"><input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by name, plate, phone, or ID number..."></div>
                    <div class="col-md-2">
                        <select class="form-select" name="stage" onchange="this.form.submit()">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="expired" <?= $statusFilter==='expired'?'selected':'' ?>>Expired</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="sort" onchange="this.form.submit()">
                            <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A-Z</option>
                            <option value="name-desc" <?= $sort==='name-desc'?'selected':'' ?>>Name Z-A</option>
                            <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest First</option>
                            <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Apply</button></div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-2 no-print">
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-file-export me-1"></i>Export All Matching (<?= number_format($total) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= qsMerge(['export'=>'csv']) ?>"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="<?= qsMerge(['export'=>'excel']) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
                </ul>
            </div>
            <?php $exportTableId='ridersTable'; $exportFilename='city_riders_page'.$page; $exportTitle='City Riders (this page)'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="ridersTable">
                        <thead><tr><th>ID</th><th>Rider</th><th>Plate</th><th>Stage</th><th>Phone</th><th>Status</th><th>Expiry</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$riders): ?>
                            <tr><td colspan="8" class="text-center py-4"><i class="fas fa-inbox fa-2x text-muted mb-2 d-block"></i><span class="text-muted">No riders found</span></td></tr>
                        <?php endif; ?>
                        <?php foreach ($riders as $r):
                            $statusBadge = ['active'=>'bg-success','expired'=>'bg-danger','pending'=>'bg-warning text-dark'][$r['status']];
                        ?>
                            <tr>
                                <td><code><?= h($r['id_number']) ?></code></td>
                                <td><strong><?= h($r['full_name']) ?></strong></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td><?= h($r['stage_name']) ?></td>
                                <td><?= h($r['phone']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><span class="status-dot <?= h($r['status']) ?>"></span><?= ucfirst($r['status']) ?></span></td>
                                <td><?= formatDate($r['expiry_date']) ?></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-warning" onclick="openEdit(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this rider? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Showing <?= $total ? $offset+1 : 0 ?>-<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?> riders</small>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$page-1]) ?>"><i class="fas fa-chevron-left"></i></a></li>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$p]) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$page+1]) ?>"><i class="fas fa-chevron-right"></i></a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="editRiderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editRiderForm">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="editRiderId">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white;">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>Edit Rider</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="full_name" id="editName" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Phone Number</label><input type="tel" class="form-control" name="phone" id="editPhone"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Stage</label>
                        <select class="form-select" name="stage_id" id="editStage">
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Status</label>
                        <select class="form-select" name="status" id="editStatus"><option value="active">Active</option><option value="expired">Expired</option><option value="pending">Pending</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Expiry Date</label><input type="date" class="form-control" name="expiry_date" id="editExpiry"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Update Rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    const ridersData = <?= json_encode($ridersById) ?>;
    function openEdit(id) {
        const r = ridersData[id];
        if (!r) return;
        document.getElementById('editRiderId').value = r.id;
        document.getElementById('editName').value = r.full_name;
        document.getElementById('editPhone').value = r.phone || '';
        document.getElementById('editStage').value = r.stage_id;
        document.getElementById('editStatus').value = r.status;
        document.getElementById('editExpiry').value = r.expiry_date || '';
        new bootstrap.Modal(document.getElementById('editRiderModal')).show();
    }
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
