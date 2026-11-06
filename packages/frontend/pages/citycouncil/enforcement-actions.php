<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_once __DIR__ . '/../../../backend/enforcement.php';
require_role(['city_admin']);
$active = 'enforcement-actions';
$cityId = $_SESSION['city_id'];
const ENFORCEMENT_TYPES = ['warning', 'fine', 'suspension', 'impound'];
const ENFORCEMENT_STATUSES = ['pending', 'resolved', 'closed'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'appeal_answer') {
        $ok = enforcement_answer_appeal((int) ($_POST['id'] ?? 0), (string) $cityId, (string) ($_POST['decision'] ?? ''), (string) ($_POST['response'] ?? ''), (int) $_SESSION['user_id']);
        flash($ok ? 'success' : 'error', $ok ? 'The appeal is answered and the rider was told.' : 'Choose accept or reject and write the answer for the rider.');
        redirect('/pages/citycouncil/enforcement-actions.php');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        runQuery("DELETE FROM enforcement_actions WHERE id = ? AND city_id = ?", [$id, $cityId]);
        audit_log('DELETE', 'enforcement_action', (string) $id, 'Enforcement action deleted');
        redirect('/pages/citycouncil/enforcement-actions.php');
    } else {
        $editId = (int) ($_POST['edit_id'] ?? 0);
        $riderId = (int) ($_POST['rider_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'warning');
        $amountRaw = trim((string) ($_POST['amount'] ?? ''));
        $amount = $amountRaw !== '' ? (float) $amountRaw : null;
        $desc = trim($_POST['description'] ?? '');
        $status = (string) ($_POST['status'] ?? 'pending');

        if (!$riderId || $desc === '') $errors[] = 'Rider and description are required.';
        if (!in_array($type, ENFORCEMENT_TYPES, true)) $errors[] = 'Choose an action type from the list.';
        if (!in_array($status, ENFORCEMENT_STATUSES, true)) $errors[] = 'Choose a status from the list.';
        if ($amountRaw !== '' && (!is_numeric($amountRaw) || $amount < 0)) $errors[] = 'The amount must be a number of zero or more.';

        if (!$errors) {
            $rider = fetchOne("SELECT stage_id, bike_plate FROM riders WHERE id = ? AND city_id = ?", [$riderId, $cityId]);
            if (!$rider) { $errors[] = 'Rider not found.'; }
            else {
                if ($editId) {
                    runQuery("UPDATE enforcement_actions SET rider_id=?,stage_id=?,plate=?,type=?,amount=?,description=?,status=? WHERE id=? AND city_id=?",
                        [$riderId,$rider['stage_id'],$rider['bike_plate'],$type,$amount,$desc,$status,$editId,$cityId]);
                    audit_log('UPDATE', 'enforcement_action', (string) $editId, 'Enforcement action updated');
                } else {
                    // The code comes from the new row's id: a count would repeat a code after a deletion or when two admins save at once.
                    runQuery("INSERT INTO enforcement_actions (action_code,rider_id,city_id,stage_id,plate,type,amount,description,action_date,status,officer_user_id)
                        VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?)",
                        ['TMP-' . bin2hex(random_bytes(6)),$riderId,$cityId,$rider['stage_id'],$rider['bike_plate'],$type,$amount,$desc,$status,$_SESSION['user_id']]);
                    $actionId = (int) db()->insert_id;
                    $code = nextActionCode($actionId);
                    runQuery("UPDATE enforcement_actions SET action_code = ? WHERE id = ?", [$code, $actionId]);
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
$appeals = fetchAll("SELECT e.*, r.full_name FROM enforcement_actions e LEFT JOIN riders r ON r.id = e.rider_id
    WHERE e.city_id = ? AND e.appeal_status = 'open' ORDER BY e.appeal_at", [$cityId]);
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
    <?php $pageTitle = 'Enforcement actions | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php ob_start(); ?>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#actionModal" data-call="openAdd"><i class="fas fa-plus me-1" aria-hidden="true"></i>Record an action</button>
    <?php $headerActions = ob_get_clean();
    $pageTitle = 'Enforcement actions';
    $pageSubtitle = '';
    require __DIR__ . '/../../partials/topheader.php'; ?>
    <?php if ($appeals): ?>
    <div class="content-area pb-0">
        <div class="card border-warning"><div class="card-body">
            <h2 class="section-title"><i class="fas fa-scale-balanced me-2 text-warning" aria-hidden="true"></i><?= count($appeals) ?> appeal<?= count($appeals) === 1 ? '' : 's' ?> waiting for an answer</h2>
            <?php foreach ($appeals as $ap): ?>
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= h($ap['full_name'] ?? 'Unknown rider') ?> · <code><?= h($ap['action_code']) ?></code> · <?= h(ENFORCEMENT_LABELS[$ap['type']] ?? $ap['type']) ?></strong><small class="text-muted">Appealed <?= formatDate($ap['appeal_at']) ?></small></div>
                <p class="small text-muted mb-1">Action: <?= h($ap['description']) ?></p>
                <p class="small mb-2"><strong>Rider's appeal:</strong> <?= nl2br(h($ap['appeal_text'])) ?></p>
                <form method="post" class="row g-2 align-items-end">
                    <?= csrf_field() ?><input type="hidden" name="action" value="appeal_answer"><input type="hidden" name="id" value="<?= (int) $ap['id'] ?>">
                    <div class="col-md-3"><label class="form-label small" for="dec<?= (int) $ap['id'] ?>">Decision</label>
                        <select class="form-select form-select-sm" id="dec<?= (int) $ap['id'] ?>" name="decision" required><option value="">Choose</option><option value="upheld">Accept: close the action</option><option value="rejected">Reject: the action stands</option></select></div>
                    <div class="col-md-7"><label class="form-label small" for="resp<?= (int) $ap['id'] ?>">Answer for the rider</label>
                        <input type="text" class="form-control form-control-sm" id="resp<?= (int) $ap['id'] ?>" name="response" maxlength="1000" required></div>
                    <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Send answer</button></div>
                </form>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <?php endif; ?>

    <div class="content-area">
        <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-gavel"></i></div><div><div class="stat-hero-value"><?= $total ?></div><div class="stat-hero-label">Total actions</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-clock"></i></div><div><div class="stat-hero-value"><?= $pending ?></div><div class="stat-hero-label">Pending</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= $resolved ?></div><div class="stat-hero-label">Resolved</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-archive"></i></div><div><div class="stat-hero-value"><?= $closed ?></div><div class="stat-hero-label">Closed</div></div></div></div>
        </div>

        <?php if ($actions): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6"><div class="card"><div class="card-body"><h6 class="fw-bold mb-3">By action type</h6><div id="actionTypeChart"></div></div></div></div>
            <div class="col-md-6"><div class="card"><div class="card-body"><h6 class="fw-bold mb-3">By Stage</h6><div id="actionStageChart"></div></div></div></div>
        </div>
        <?php endif; ?>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <select aria-label="Filter by type" class="form-select" name="type" data-autosubmit>
                            <option value="">All Types</option>
                            <?php foreach (ENFORCEMENT_TYPES as $t): ?><option value="<?= $t ?>" <?= $typeFilter===$t?'selected':'' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select aria-label="Filter by status" class="form-select" name="status" data-autosubmit>
                            <option value="">All Status</option>
                            <?php foreach (ENFORCEMENT_STATUSES as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select aria-label="Filter by stage" class="form-select" name="stage" data-autosubmit>
                            <option value="">All Stages</option>
                            <?php foreach ($stagesList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <?php $exportTableId='actionsTable'; $exportFilename='enforcement_actions'; $exportTitle='Enforcement Actions'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
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
                        <?php if (!$actions): ?><tr><td colspan="9"><div class="empty-state" style="padding:36px 20px;"><i class="fas fa-inbox"></i><h5>No enforcement actions recorded</h5></div></td></tr><?php endif; ?>
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
                                <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($a['status']) ?></span><?php if ($a['appeal_status']): ?><br><small class="text-<?= $a['appeal_status'] === 'open' ? 'warning-emphasis' : 'muted' ?>">Appeal <?= $a['appeal_status'] === 'open' ? 'waiting' : ($a['appeal_status'] === 'upheld' ? 'accepted' : 'rejected') ?></small><?php endif; ?></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-warning" data-call="openEdit" data-call-args="<?= h(js_json([(int) $a['id']])) ?>" aria-label="Edit action <?= h($a['action_code']) ?>" title="Edit"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                    <form method="post" class="d-inline" data-confirm="Delete enforcement action <?= h($a['action_code']) ?> for <?= h($a['full_name'] ?? 'this rider') ?>? It disappears from the city's records and reports." data-confirm-ok="Delete action">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete action <?= h($a['action_code']) ?>" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
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
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="actionModalTitle">New enforcement action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label for="riderSelect" class="form-label fw-semibold">Rider <span class="text-danger">*</span></label>
                        <select class="form-select" name="rider_id" id="riderSelect" required>
                            <option value="">Select rider</option>
                            <?php foreach ($riders as $r): ?><option value="<?= (int)$r['id'] ?>"><?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label for="typeSelect" class="form-label fw-semibold">Action type <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" id="typeSelect"><option value="warning">Warning</option><option value="fine">Fine</option><option value="suspension">Suspension</option><option value="impound">Impound</option></select>
                    </div>
                    <div class="mb-3"><label for="amountInput" class="form-label fw-semibold">Amount (UGX)</label><input type="number" class="form-control" name="amount" id="amountInput" min="0" step="any"></div>
                    <div class="mb-3"><label for="descInput" class="form-label fw-semibold">Description <span class="text-danger">*</span></label><textarea class="form-control" name="description" id="descInput" rows="2" required></textarea></div>
                    <div class="mb-3"><label for="statusSelect" class="form-label fw-semibold">Status</label><select class="form-select" name="status" id="statusSelect"><option value="pending">Pending</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save me-1"></i>Save action</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    const actionsData = <?= js_json($actionsById) ?>;

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
        series: <?= js_json(array_values($byType)) ?>,
        chart: { type: 'donut', height: 220 },
        labels: <?= js_json(array_map('ucfirst', array_keys($byType))) ?>,
        colors: ['#f59e0b','#dc3545','#212529','#6c757d'],
    }).render();
    new ApexCharts(document.querySelector('#actionStageChart'), {
        series: [{ name: 'Actions', data: <?= js_json(array_values($byStage)) ?> }],
        chart: { type: 'bar', height: 220, toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
        colors: ['#dc3545'],
        xaxis: { categories: <?= js_json(array_keys($byStage)) ?> },
    }).render();
    <?php endif; ?>

</script>
</body>
</html>
