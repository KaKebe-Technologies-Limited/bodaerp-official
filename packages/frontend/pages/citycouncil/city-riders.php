<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['city_admin']);
$active = 'city-riders';
$cityId = $_SESSION['city_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $riderId = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'portal_link') {
        require_once __DIR__ . '/../../../backend/accounts.php';
        if (fetchValue("SELECT COUNT(*) FROM riders WHERE id = ? AND city_id = ? AND deleted_at IS NULL", [$riderId, $cityId])) flash('info', rider_portal_invite($riderId));
        redirect('/pages/citycouncil/city-riders.php' . (isset($_GET['page']) ? '?page=' . (int) $_GET['page'] : ''));
    }
    if (($_POST['action'] ?? '') === 'delete') {
        runQuery("UPDATE riders SET deleted_at = NOW() WHERE id = ? AND city_id = ?", [$riderId, $cityId]);
        audit_log('DELETE', 'rider', (string) $riderId, 'Rider soft-deleted');
    } else {
        $status = in_array($_POST['status'] ?? '', ['active', 'pending', 'expired'], true) ? $_POST['status'] : 'pending';
        $stageId = (int) ($_POST['stage_id'] ?? 0);
        $expiry = ($_POST['expiry_date'] ?? '') !== '' && strtotime($_POST['expiry_date']) ? date('Y-m-d', strtotime($_POST['expiry_date'])) : null;
        if (!fetchValue("SELECT COUNT(*) FROM stages WHERE id = ? AND city_id = ?", [$stageId, $cityId]) || trim($_POST['full_name'] ?? '') === '') {
            flash('error', 'Choose a stage in your city and enter the rider\'s name.');
            redirect('/pages/citycouncil/city-riders.php');
        }
        runQuery("UPDATE riders SET full_name=?,phone=?,stage_id=?,status=?,expiry_date=? WHERE id=? AND city_id=?", [
            trim($_POST['full_name'] ?? ''), trim($_POST['phone'] ?? ''), $stageId, $status,
            $expiry, $riderId, $cityId,
        ]);
        audit_log('UPDATE', 'rider', (string) $riderId, 'Rider updated');
        flash('success', 'Rider details saved.');
    }
    redirect('/pages/citycouncil/city-riders.php' . (isset($_GET['page']) ? '?page=' . (int)$_GET['page'] : ''));
}

$search = trim($_GET['q'] ?? '');
$stageFilter = (int) ($_GET['stage'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$where = "WHERE r.city_id = :city AND r.deleted_at IS NULL"; $params = ['city' => $cityId];
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
    csv_put($out, ['ID', 'Rider', 'Plate', 'Stage', 'Phone', 'Status', 'Expiry']);
    foreach ($exportRows as $r) {
        csv_put($out, [$r['id_number'], $r['full_name'], $r['bike_plate'], $r['stage_name'], $r['phone'], ucfirst($r['status']), formatDate($r['expiry_date'])]);
    }
    fclose($out);
    exit;
}

$total = (int) fetchValue("SELECT COUNT(*) FROM riders r $where", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$riders = fetchAll("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id = r.stage_id $where ORDER BY $orderBy LIMIT " . (int) $perPage . " OFFSET " . (int) $offset, $params);
$stages = fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]);

$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL", [$cityId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='active' AND deleted_at IS NULL", [$cityId]);
$expiredRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND status='expired' AND deleted_at IS NULL", [$cityId]);
$stageCount = count($stages);
// Only what the edit form needs goes into the page's JavaScript.
$ridersById = [];
foreach ($riders as $r) {
    $ridersById[$r['id']] = ['id' => (int) $r['id'], 'full_name' => $r['full_name'], 'phone' => $r['phone'],
        'stage_id' => (int) $r['stage_id'], 'status' => $r['status'], 'expiry_date' => $r['expiry_date']];
}

function qsMerge(array $overrides): string {
    return '?' . http_build_query(array_merge($_GET, $overrides));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'City riders | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
        .status-dot.active { background: #198754; } .status-dot.expired { background: #dc3545; } .status-dot.pending { background: #ffc107; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'Riders';
    $pageSubtitle = '';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRiders) ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= number_format($activeRiders) ?></div><div class="stat-hero-label">Active (paid)</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-hero-value"><?= number_format($expiredRiders) ?></div><div class="stat-hero-label">Expired</div></div></div></div>
            <div class="col-md-3 col-6"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="stat-hero-value"><?= $stageCount ?></div><div class="stat-hero-label">Stages</div></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-center" method="get">
                    <div class="col-md-3"><input aria-label="Search" type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by name, plate, phone, or ID number..."></div>
                    <div class="col-md-2">
                        <select aria-label="Filter by stage" class="form-select" name="stage" data-autosubmit>
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select aria-label="Filter by status" class="form-select" name="status" data-autosubmit>
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="expired" <?= $statusFilter==='expired'?'selected':'' ?>>Expired</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select aria-label="Sort by" class="form-select" name="sort" data-autosubmit>
                            <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A-Z</option>
                            <option value="name-desc" <?= $sort==='name-desc'?'selected':'' ?>>Name Z-A</option>
                            <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest first</option>
                            <option value="oldest" <?= $sort==='oldest'?'selected':'' ?>>Oldest first</option>
                        </select>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Apply</button></div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-2 no-print">
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-file-export me-1"></i>Export all matching (<?= number_format($total) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= qsMerge(['export'=>'csv']) ?>"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="<?= qsMerge(['export'=>'excel']) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
                </ul>
            </div>
            <?php $exportTableId='ridersTable'; $exportFilename='city_riders_page'.$page; $exportTitle='City Riders (this page)'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="ridersTable">
                        <thead><tr><th>Rider</th><th>Permit number</th><th>Plate</th><th>Stage</th><th>Phone</th><th>Status</th><th>Expiry</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$riders): ?>
                            <tr><td colspan="8"><div class="empty-state" style="padding:36px 20px;"><i class="fas fa-inbox"></i><h5>No riders found</h5></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($riders as $r):
                            $statusBadge = ['active'=>'bg-success','expired'=>'bg-danger','pending'=>'bg-warning text-dark'][$r['status']];
                        ?>
                            <tr>
                                <td><?= person_cell($r['photo_path'], $r['full_name']) ?></td>
                                <td><code><?= h($r['id_number']) ?></code></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td><?= h($r['stage_name']) ?></td>
                                <td><?= h($r['phone']) ?></td>
                                <td><span class="badge <?= $statusBadge ?>"><span class="status-dot <?= h($r['status']) ?>"></span><?= ucfirst($r['status']) ?></span></td>
                                <td><?= formatDate($r['expiry_date']) ?></td>
                                <td class="no-export">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/pages/citycouncil/id-card.php?id=<?= (int) $r['id'] ?>" aria-label="ID card of <?= h($r['full_name']) ?>" title="ID card"><i class="fas fa-id-card" aria-hidden="true"></i></a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/pages/citycouncil/edit-rider.php?id=<?= (int) $r['id'] ?>" aria-label="Correct every detail of <?= h($r['full_name']) ?>" title="Full record"><i class="fas fa-user-pen" aria-hidden="true"></i></a>
                                    <form method="post" class="d-inline" data-confirm="Send <?= h($r['full_name']) ?> a link to set or reset their portal password, by email or SMS? You never see the password." data-confirm-ok="Send the link"><?= csrf_field() ?><input type="hidden" name="action" value="portal_link"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-outline-success" aria-label="Send <?= h($r['full_name']) ?> a portal sign-in link" title="Portal access link"><i class="fas fa-key" aria-hidden="true"></i></button></form>
                                    <button class="btn btn-sm btn-outline-warning" data-call="openEdit" data-call-args="<?= h(js_json([(int) $r['id']])) ?>" aria-label="Edit <?= h($r['full_name']) ?>" title="Edit"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                    <form method="post" class="d-inline" data-confirm="Delete <?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)? Their permit, payments and portal account are removed. This cannot be undone." data-confirm-ok="Delete rider">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= h($r['full_name']) ?>" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
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
                    <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$page-1]) ?>" aria-label="Previous page"><i class="fas fa-chevron-left" aria-hidden="true"></i></a></li>
                    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
                        <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$p]) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge(['page'=>$page+1]) ?>" aria-label="Next page"><i class="fas fa-chevron-right" aria-hidden="true"></i></a></li>
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
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit rider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label for="editName" class="form-label fw-semibold">Full name <span class="text-danger">*</span></label><input type="text" class="form-control" name="full_name" id="editName" required></div>
                    <div class="mb-3"><label for="editPhone" class="form-label fw-semibold">Phone number</label><input type="tel" class="form-control" name="phone" id="editPhone"></div>
                    <div class="mb-3"><label for="editStage" class="form-label fw-semibold">Stage</label>
                        <select class="form-select" name="stage_id" id="editStage">
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label for="editStatus" class="form-label fw-semibold">Status</label>
                        <select class="form-select" name="status" id="editStatus"><option value="active">Active</option><option value="expired">Expired</option><option value="pending">Pending</option></select>
                    </div>
                    <div class="mb-3"><label for="editExpiry" class="form-label fw-semibold">Expiry date</label><input type="date" class="form-control" name="expiry_date" id="editExpiry"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Update rider</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    const ridersData = <?= js_json($ridersById) ?>;
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
</script>
</body>
</html>
