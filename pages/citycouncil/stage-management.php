<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'stage-management';
$cityId = $_SESSION['city_id'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            runQuery("DELETE FROM stages WHERE id = ? AND city_id = ?", [$id, $cityId]);
            audit_log('DELETE', 'stage', (string) $id, 'Stage deleted');
            redirect('/pages/citycouncil/stage-management.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Cannot delete this stage — it still has riders assigned to it.';
        }
    } else {
        $editId = (int) ($_POST['edit_stage_id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $chairName = trim($_POST['chairperson_name'] ?? '');
        $chairPhone = trim($_POST['chairperson_phone'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $chairName === '' || $chairPhone === '') $errors[] = 'Please fill in all required fields.';

        if (!$errors && $editId) {
            runQuery("UPDATE stages SET name=?,location=?,chairperson_name=?,chairperson_phone=?,status=? WHERE id=? AND city_id=?",
                [$name,$location,$chairName,'+256 '.$chairPhone,$status,$editId,$cityId]);
            audit_log('UPDATE', 'stage', (string) $editId, 'Stage updated');
            redirect('/pages/citycouncil/stage-management.php');
        } elseif (!$errors) {
            $count = (int) fetchValue("SELECT COUNT(*) FROM stages WHERE city_id = ?", [$cityId]);
            $code = $cityId . '-STG-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
            runQuery("INSERT INTO stages (city_id,code,name,location,chairperson_name,chairperson_phone,status) VALUES (?,?,?,?,?,?,?)",
                [$cityId,$code,$name,$location,$chairName,'+256 '.$chairPhone,$status]);
            $newId = db()->insert_id;
            audit_log('CREATE', 'stage', (string) $newId, 'Stage created');
            redirect('/pages/citycouncil/stage-management.php');
        }
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$stageWhere = "WHERE s.city_id = :city"; $stageParams = ['city' => $cityId];
if ($search !== '') { $stageWhere .= " AND (s.name LIKE :q1 OR s.chairperson_name LIKE :q2)"; $stageParams['q1'] = $stageParams['q2'] = "%$search%"; }
if ($statusFilter !== '') { $stageWhere .= " AND s.status = :status"; $stageParams['status'] = $statusFilter; }

$stages = fetchAll("SELECT s.*, v.rider_count, v.compliance_pct,
    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.city_id = s.city_id AND p.rider_id IN (SELECT id FROM riders WHERE stage_id = s.id) AND p.status='Confirmed') AS revenue
    FROM stages s LEFT JOIN v_stage_stats v ON v.stage_id = s.id
    $stageWhere ORDER BY s.name", $stageParams);

$totalStages = count($stages);
$activeStages = count(array_filter($stages, fn($s) => $s['status'] === 'active'));
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ?", [$cityId]);
$stagesById = array_column($stages, null, 'id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Stage Management - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
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
        .status-dot.active { background: #198754; }
        .status-dot.inactive { background: #dc3545; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Stage Management</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="council-stat-card blue"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $totalStages ?></span><div class="stat-label">Total Stages</div></div><i class="fas fa-map-marker-alt stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card green"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $activeStages ?></span><div class="stat-label">Active Stages</div></div><i class="fas fa-check-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card red"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $totalStages - $activeStages ?></span><div class="stat-label">Inactive Stages</div></div><i class="fas fa-exclamation-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div><i class="fas fa-users stat-icon"></i></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stageModal" onclick="openAddStage()"><i class="fas fa-plus me-2"></i>Add Stage</button>
                    <?php $exportTableId='stagesTable'; $exportFilename='stages'; $exportTitle='Stage Management'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                </div>
                <form class="row g-2 align-items-center no-print" method="get">
                    <div class="col-md-5"><input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by stage or chairperson..."></div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-outline-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="stagesTable">
                        <thead><tr><th>#</th><th>Stage Name</th><th>Chairperson</th><th>Phone</th><th>Riders</th><th>Revenue</th><th>Compliance</th><th>Status</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($stages as $i => $s):
                            $compliance = (float) ($s['compliance_pct'] ?? 0);
                            $cBadge = $compliance >= 80 ? 'bg-success' : ($compliance >= 70 ? 'bg-primary' : ($compliance >= 50 ? 'bg-warning text-dark' : 'bg-danger'));
                            $cLabel = $compliance >= 80 ? 'Excellent' : ($compliance >= 70 ? 'Good' : ($compliance >= 50 ? 'Fair' : 'Poor'));
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= h($s['name']) ?></strong><br><small class="text-muted"><?= h($s['location'] ?: '') ?></small></td>
                                <td><?= h($s['chairperson_name'] ?: '—') ?></td>
                                <td><?= h($s['chairperson_phone'] ?: '—') ?></td>
                                <td><span class="badge bg-primary"><?= (int) $s['rider_count'] ?></span></td>
                                <td><?= h(formatCurrency($s['revenue'])) ?></td>
                                <td><span class="badge <?= $cBadge ?>"><?= $cLabel ?></span> <span class="fw-bold"><?= $compliance ?>%</span></td>
                                <td><span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><span class="status-dot <?= h($s['status']) ?>"></span><?= ucfirst($s['status']) ?></span></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-warning" onclick="openEditStage(<?= (int) $s['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this stage? All riders must be reassigned first.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
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
    </div>
</div>

<div class="modal fade" id="stageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="stageForm">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_stage_id" id="editStageId">
                <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd, #0a58ca); color: white;">
                    <h5 class="modal-title fw-bold" id="stageModalTitle"><i class="fas fa-plus-circle me-2"></i>Add New Stage</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="form-label fw-semibold">Stage Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" id="stageName" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Location</label><input type="text" class="form-control" name="location" id="stageLocation"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Chairperson Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="chairperson_name" id="chairName" required></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Chairperson Phone <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">+256</span><input type="tel" class="form-control" name="chairperson_phone" id="chairPhone" placeholder="772123456" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Status</label><select class="form-select" name="status" id="stageStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    const stagesData = <?= json_encode($stagesById) ?>;

    function openAddStage() {
        document.getElementById('stageModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Stage';
        document.getElementById('editStageId').value = '';
        document.getElementById('stageForm').reset();
    }

    function openEditStage(id) {
        const s = stagesData[id];
        if (!s) return;
        document.getElementById('stageModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Stage';
        document.getElementById('editStageId').value = s.id;
        document.getElementById('stageName').value = s.name;
        document.getElementById('stageLocation').value = s.location || '';
        document.getElementById('chairName').value = s.chairperson_name || '';
        document.getElementById('chairPhone').value = (s.chairperson_phone || '').replace('+256 ', '');
        document.getElementById('stageStatus').value = s.status;
        new bootstrap.Modal(document.getElementById('stageModal')).show();
    }

    <?php if ($errors): ?>new bootstrap.Modal(document.getElementById('stageModal')).show();<?php endif; ?>

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
