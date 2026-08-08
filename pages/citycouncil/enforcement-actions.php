<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'enforcement-actions';
$cityId = $_SESSION['city_id'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        runQuery("DELETE FROM enforcement_actions WHERE id = ? AND city_id = ?", [$id, $cityId]);
        audit_log('DELETE', 'enforcement_action', (string) $id, 'Enforcement action deleted');
        redirect('/pages/citycouncil/enforcement-actions.php');
    } else {
        $editId = (int) ($_POST['edit_id'] ?? 0);
        $riderId = (int) ($_POST['rider_id'] ?? 0);
        $type = $_POST['type'] ?? 'warning';
        $amount = $_POST['amount'] !== '' ? (float) $_POST['amount'] : null;
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        if (!$riderId || $desc === '') $errors[] = 'Rider and description are required.';

        if (!$errors) {
            $rider = fetchOne("SELECT stage_id, bike_plate FROM riders WHERE id = ? AND city_id = ?", [$riderId, $cityId]);
            if (!$rider) { $errors[] = 'Rider not found.'; }
            else {
                if ($editId) {
                    runQuery("UPDATE enforcement_actions SET rider_id=?,stage_id=?,plate=?,type=?,amount=?,description=?,status=? WHERE id=? AND city_id=?",
                        [$riderId,$rider['stage_id'],$rider['bike_plate'],$type,$amount,$desc,$status,$editId,$cityId]);
                    audit_log('UPDATE', 'enforcement_action', (string) $editId, 'Enforcement action updated');
                } else {
                    $count = (int) fetchValue("SELECT COUNT(*) FROM enforcement_actions") + 1;
                    $code = 'ENF-' . date('Y') . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
                    runQuery("INSERT INTO enforcement_actions (action_code,rider_id,city_id,stage_id,plate,type,amount,description,action_date,status,officer_user_id)
                        VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?)",
                        [$code,$riderId,$cityId,$rider['stage_id'],$rider['bike_plate'],$type,$amount,$desc,$status,$_SESSION['user_id']]);
                    audit_log('CREATE', 'enforcement_action', $code, 'Enforcement action created');
                }
                redirect('/pages/citycouncil/enforcement-actions.php');
            }
        }
    }
}

$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$stageFilter = (int) ($_GET['stage'] ?? 0);
$where = "WHERE e.city_id = :city"; $params = ['city' => $cityId];
if ($typeFilter !== '') { $where .= " AND e.type = :type"; $params['type'] = $typeFilter; }
if ($statusFilter !== '') { $where .= " AND e.status = :status"; $params['status'] = $statusFilter; }
if ($stageFilter) { $where .= " AND e.stage_id = :stage"; $params['stage'] = $stageFilter; }

$actions = fetchAll("SELECT e.*, r.full_name, s.name AS stage_name FROM enforcement_actions e
    LEFT JOIN riders r ON r.id = e.rider_id LEFT JOIN stages s ON s.id = e.stage_id
    $where ORDER BY e.action_date DESC, e.id DESC", $params);
$riders = fetchAll("SELECT id, full_name, bike_plate FROM riders WHERE city_id = ? AND deleted_at IS NULL ORDER BY full_name LIMIT 500", [$cityId]);
$stagesList = fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]);

$total = count($actions);
$pending = count(array_filter($actions, fn($a) => $a['status'] === 'pending'));
$resolved = count(array_filter($actions, fn($a) => $a['status'] === 'resolved'));
$closed = count(array_filter($actions, fn($a) => $a['status'] === 'closed'));

$byType = ['warning'=>0,'fine'=>0,'suspension'=>0,'impound'=>0];
foreach ($actions as $a) { $byType[$a['type']]++; }
$byStage = [];
foreach ($actions as $a) { $byStage[$a['stage_name'] ?? 'Unknown'] = ($byStage[$a['stage_name'] ?? 'Unknown'] ?? 0) + 1; }

$actionsById = array_column($actions, null, 'id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Enforcement Actions - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .council-stat-card { background: white; border-radius: 14px; padding: 16px 20px; border-left: 4px solid #dc3545; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        .council-stat-card .stat-number { font-size: 1.6rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
        .council-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .council-stat-card .stat-icon { font-size: 1.6rem; opacity: 0.15; }
        .council-stat-card.gold { border-left-color: #f59e0b; }
        .council-stat-card.green { border-left-color: #198754; }
        .council-stat-card.blue { border-left-color: #0d6efd; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Enforcement Actions</span></h5>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#actionModal" onclick="openAdd()"><i class="fas fa-plus me-1"></i>New Action</button>
        </div>
    </header>

    <div class="content-area">
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="council-stat-card"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $total ?></span><div class="stat-label">Total Actions</div></div><i class="fas fa-gavel stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $pending ?></span><div class="stat-label">Pending</div></div><i class="fas fa-clock stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card green"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $resolved ?></span><div class="stat-label">Resolved</div></div><i class="fas fa-check-circle stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card blue"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= $closed ?></span><div class="stat-label">Closed</div></div><i class="fas fa-archive stat-icon"></i></div></div></div>
        </div>

        <?php if ($actions): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6"><div class="card"><div class="card-body"><h6 class="fw-bold mb-3">By Action Type</h6><div id="actionTypeChart"></div></div></div></div>
            <div class="col-md-6"><div class="card"><div class="card-body"><h6 class="fw-bold mb-3">By Stage</h6><div id="actionStageChart"></div></div></div></div>
        </div>
        <?php endif; ?>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select class="form-select" name="type" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <?php foreach (['warning','fine','suspension','impound'] as $t): ?><option value="<?= $t ?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <?php foreach (['pending','resolved','closed'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="stage" onchange="this.form.submit()">
                            <option value="">All Stages</option>
                            <?php foreach ($stagesList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <?php $exportTableId='actionsTable'; $exportFilename='enforcement_actions'; $exportTitle='Enforcement Actions'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="actionsTable">
                        <thead><tr><th>#</th><th>Action ID</th><th>Rider</th><th>Plate</th><th>Stage</th><th>Type</th><th>Date</th><th>Status</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$actions): ?><tr><td colspan="9" class="text-center py-4 text-muted">No enforcement actions recorded</td></tr><?php endif; ?>
                        <?php foreach ($actions as $i => $a):
                            $typeBadge = ['warning'=>'bg-warning text-dark','fine'=>'bg-danger','suspension'=>'bg-dark','impound'=>'bg-secondary'][$a['type']];
                            $statusBadge = ['pending'=>'bg-warning text-dark','resolved'=>'bg-success','closed'=>'bg-secondary'][$a['status']];
                        ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><code><?= h($a['action_code']) ?></code></td>
                                <td><?= h($a['full_name'] ?? 'Unknown') ?></td>
                                <td><?= h($a['plate']) ?></td>
                                <td><?= h($a['stage_name']) ?></td>
                                <td><span class="badge <?= $typeBadge ?>"><?= ucfirst($a['type']) ?></span></td>
                                <td><?= formatDate($a['action_date']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($a['status']) ?></span></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-warning" onclick="openEdit(<?= (int) $a['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this action?');">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="actionForm">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_id" id="editId">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #a71d2a); color: white;">
                    <h5 class="modal-title fw-bold" id="actionModalTitle"><i class="fas fa-gavel me-2"></i>New Enforcement Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="form-label fw-semibold">Rider <span class="text-danger">*</span></label>
                        <select class="form-select" name="rider_id" id="riderSelect" required>
                            <option value="">Select rider</option>
                            <?php foreach ($riders as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Action Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" id="typeSelect"><option value="warning">Warning</option><option value="fine">Fine</option><option value="suspension">Suspension</option><option value="impound">Impound</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label fw-semibold">Amount (UGX)</label><input type="number" class="form-control" name="amount" id="amountInput"></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Description <span class="text-danger">*</span></label><textarea class="form-control" name="description" id="descInput" rows="2" required></textarea></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Status</label><select class="form-select" name="status" id="statusSelect"><option value="pending">Pending</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save me-1"></i>Save Action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    const actionsData = <?= json_encode($actionsById) ?>;

    function openAdd() {
        document.getElementById('actionModalTitle').innerHTML = '<i class="fas fa-gavel me-2"></i>New Enforcement Action';
        document.getElementById('actionForm').reset();
        document.getElementById('editId').value = '';
    }
    function openEdit(id) {
        const a = actionsData[id];
        if (!a) return;
        document.getElementById('actionModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Action';
        document.getElementById('editId').value = a.id;
        document.getElementById('riderSelect').value = a.rider_id;
        document.getElementById('typeSelect').value = a.type;
        document.getElementById('amountInput').value = a.amount || '';
        document.getElementById('descInput').value = a.description;
        document.getElementById('statusSelect').value = a.status;
        new bootstrap.Modal(document.getElementById('actionModal')).show();
    }
    <?php if ($errors): ?>new bootstrap.Modal(document.getElementById('actionModal')).show();<?php endif; ?>

    <?php if ($actions): ?>
    new ApexCharts(document.querySelector('#actionTypeChart'), {
        series: <?= json_encode(array_values($byType)) ?>,
        chart: { type: 'donut', height: 220 },
        labels: <?= json_encode(array_map('ucfirst', array_keys($byType))) ?>,
        colors: ['#f59e0b','#dc3545','#212529','#6c757d'],
    }).render();
    new ApexCharts(document.querySelector('#actionStageChart'), {
        series: [{ name: 'Actions', data: <?= json_encode(array_values($byStage)) ?> }],
        chart: { type: 'bar', height: 220, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        colors: ['#dc3545'],
        xaxis: { categories: <?= json_encode(array_keys($byStage)) ?> },
    }).render();
    <?php endif; ?>

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
