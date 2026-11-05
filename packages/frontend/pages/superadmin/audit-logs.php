<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);
require_once __DIR__ . '/../../../backend/admin_ui.php';
$active = 'audit-logs';

$search       = trim($_GET['q'] ?? '');
$filterAction = $_GET['action'] ?? '';
$filterCity   = $_GET['city'] ?? '';

$sql = " FROM audit_logs a LEFT JOIN cities c ON c.id = a.city_id WHERE 1=1";
$params = [];
if ($search !== '')      { $sql .= " AND (a.user_name_snapshot LIKE ? OR a.details LIKE ? OR a.entity_type LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterAction !== '') { $sql .= " AND a.action = ?"; $params[] = $filterAction; }
if ($filterCity !== '')   { $sql .= " AND a.city_id = ?"; $params[] = $filterCity; }
$pager = paginate((int) fetchValue("SELECT COUNT(*)" . $sql, $params), 50);
$logs = fetchAll("SELECT a.*, c.name AS city_name" . $sql . " ORDER BY a.created_at DESC, a.id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}", $params);

$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");
$actionColors = ['LOGIN'=>'success','LOGOUT'=>'secondary','LOGIN_FAILED'=>'danger','ACCESS_DENIED'=>'danger','CREATE'=>'primary','UPDATE'=>'warning','DELETE'=>'danger','PAYMENT_CONFIRMED'=>'success','PASSWORD_CHANGED'=>'info','PASSWORD_RESET'=>'info','EXPORT'=>'dark'];
$roleColors   = ['super_admin'=>'primary','city_admin'=>'success','chairperson'=>'warning','rider'=>'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Audit logs | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'Audit log';
    $pageSubtitle = 'Every sign-in and change across the platform';
    require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0">System audit trail</h6>
                    <form class="d-flex gap-2 flex-wrap" method="get">
                        <input aria-label="Search" type="text" class="form-control form-control-sm" name="q" value="<?= h($search) ?>" placeholder="Search user, details..." style="width:200px;">
                        <select aria-label="Filter by action" class="form-select form-select-sm" style="width:auto;" name="action" data-autosubmit>
                            <option value="">All Actions</option>
                            <?php foreach (array_column(fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action"), 'action') as $a): ?>
                                <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select aria-label="Filter by city" class="form-select form-select-sm" style="width:auto;" name="city" data-autosubmit>
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filter</button>
                        <?php $exportTableId='auditTable'; $exportFilename='audit_logs'; $exportTitle='System Audit Trail'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="auditTable">
                        <thead><tr><th>Time</th><th>User</th><th>Role</th><th>City</th><th>Action</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td style="font-size:0.78rem;white-space:nowrap;"><?= formatDate($l['created_at'], 'Y-m-d H:i') ?></td>
                                <td style="font-size:0.82rem;"><strong><?= h($l['user_name_snapshot'] ?? 'Unknown') ?></strong></td>
                                <td><span class="badge bg-<?= $roleColors[$l['role_snapshot']] ?? 'secondary' ?>" style="font-size:0.6rem;"><?= h($l['role_snapshot'] ? role_label($l['role_snapshot']) : '–') ?></span></td>
                                <td style="font-size:0.8rem;"><?= h($l['city_name'] ?? '–') ?></td>
                                <td><span class="badge bg-<?= $actionColors[$l['action']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= h($l['action']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= h($l['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="mt-3"><?= render_pagination($pager) ?></div>
                </div>
                <?php if (!$logs): ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No audit logs match this filter.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
