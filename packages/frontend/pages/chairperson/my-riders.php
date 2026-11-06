<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['chairperson']);
$active = 'my-riders';
$stageId = $_SESSION['stage_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    runQuery("UPDATE riders SET deleted_at = NOW() WHERE id = ? AND stage_id = ?", [$id, $stageId]);
    audit_log('DELETE', 'rider', (string) $id, 'Rider soft-deleted');
    redirect('/pages/chairperson/my-riders.php');
}

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'name';

$where = "WHERE stage_id = :stage AND deleted_at IS NULL"; $params = ['stage' => $stageId];
if ($search !== '') { $where .= " AND (full_name LIKE :q1 OR bike_plate LIKE :q2 OR nin LIKE :q3 OR id_number LIKE :q4)"; $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = "%$search%"; }
if ($statusFilter) { $where .= " AND status = :status"; $params['status'] = $statusFilter; }
$orderBy = match ($sort) { 'name-desc' => 'full_name DESC', 'latest' => 'id DESC', 'oldest' => 'id ASC', default => 'full_name ASC' };

$riders = fetchAll("SELECT * FROM riders $where ORDER BY $orderBy", $params);

$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND deleted_at IS NULL", [$stageId]);
$activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND deleted_at IS NULL", [$stageId]);
$defaulters = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='expired' AND deleted_at IS NULL", [$stageId]);
$expiringSoon = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND status='active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND deleted_at IS NULL", [$stageId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'My Riders | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'My Riders'; $pageSubtitle = 'All riders registered under your stage'; require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= $totalRiders ?></div><div class="stat-hero-label">Total riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= $activeRiders ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= $defaulters ?></div><div class="stat-hero-label">Defaulters</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= $expiringSoon ?></div><div class="stat-hero-label">Expiring soon</div></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-center" method="get">
                    <div class="col-md-4"><label for="f-q" class="form-label fw-semibold">Search</label><input id="f-q" type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by name, plate, NIN, or ID number..."></div>
                    <div class="col-md-3"><label for="f-status" class="form-label fw-semibold">Status</label>
                        <select id="f-status" class="form-select" name="status">
                            <option value="">All</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="expired" <?= $statusFilter==='expired'?'selected':'' ?>>Expired</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3"><label for="f-sort" class="form-label fw-semibold">Sort By</label>
                        <select id="f-sort" class="form-select" name="sort">
                            <option value="name" <?= $sort==='name'?'selected':'' ?>>Name A-Z</option>
                            <option value="name-desc" <?= $sort==='name-desc'?'selected':'' ?>>Name Z-A</option>
                            <option value="latest" <?= $sort==='latest'?'selected':'' ?>>Latest first</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100 mt-4">Apply</button></div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='myRidersTable'; $exportFilename='my_riders'; $exportTitle='My Riders'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="myRidersTable">
                        <thead><tr><th>Rider</th><th>Permit number</th><th>Plate</th><th>Phone</th><th>Status</th><th>Expiry</th><th class="no-export">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($riders as $i => $r): $sc = ['active'=>'bg-success','expired'=>'bg-danger','pending'=>'bg-warning text-dark']; ?>
                            <tr>
                                <td><?= person_cell($r['photo_path'], $r['full_name']) ?></td>
                                <td><code><?= h($r['id_number']) ?></code></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td><?= h($r['phone']) ?></td>
                                <td><span class="badge <?= $sc[$r['status']] ?>"><span class="status-dot <?= h($r['status']) ?>"></span><?= ucfirst($r['status']) ?></span></td>
                                <td><?= formatDate($r['expiry_date']) ?></td>
                                <td class="no-export">
                                    <a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="View <?= h($r['full_name']) ?>" title="View"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                    <a href="<?= BASE_URL ?>/pages/chairperson/edit-rider.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-warning" aria-label="Edit <?= h($r['full_name']) ?>" title="Edit"><i class="fas fa-edit" aria-hidden="true"></i></a>
                                    <form method="post" class="d-inline" data-confirm="Delete <?= h($r['full_name']) ?> (<?= h($r['bike_plate']) ?>)? Their permit, payments and portal account are removed. This cannot be undone." data-confirm-ok="Delete rider">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= h($r['full_name']) ?>" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$riders): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h5>No riders found</h5>
                        <p>No rider matches. Clear the search, or register a new rider.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
