<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);
$active = 'audit-logs';

$search       = trim($_GET['q'] ?? '');
$filterAction = $_GET['action'] ?? '';
$filterCity   = $_GET['city'] ?? '';

$sql = "SELECT a.*, c.name AS city_name FROM audit_logs a LEFT JOIN cities c ON c.id = a.city_id WHERE 1=1";
$params = [];
if ($search !== '')      { $sql .= " AND (a.user_name_snapshot LIKE ? OR a.details LIKE ? OR a.entity_type LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($filterAction !== '') { $sql .= " AND a.action = ?"; $params[] = $filterAction; }
if ($filterCity !== '')   { $sql .= " AND a.city_id = ?"; $params[] = $filterCity; }
$sql .= " ORDER BY a.created_at DESC LIMIT 200";
$logs = fetchAll($sql, $params);

$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");
$actionColors = ['LOGIN'=>'success','LOGOUT'=>'secondary','LOGIN_FAILED'=>'danger','CREATE'=>'primary','UPDATE'=>'warning','DELETE'=>'danger'];
$roleColors   = ['super_admin'=>'primary','city_admin'=>'success','chairperson'=>'warning','rider'=>'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Audit Logs - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Audit Logs</span></h5>
            </div>
            <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" class="rounded-circle" width="40" height="40">
                <div><span class="fw-bold d-block" style="font-size:0.85rem;"><?= h($_SESSION['name']) ?></span><small class="text-muted">Platform Admin</small></div>
            </div>
        </div>
    </header>
    <div class="content-area">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i>System Audit Trail</h6>
                    <form class="d-flex gap-2 flex-wrap" method="get">
                        <input type="text" class="form-control form-control-sm" name="q" value="<?= h($search) ?>" placeholder="Search user, details..." style="width:200px;">
                        <select class="form-select form-select-sm" style="width:auto;" name="action" onchange="this.form.submit()">
                            <option value="">All Actions</option>
                            <?php foreach (['LOGIN','LOGOUT','LOGIN_FAILED','CREATE','UPDATE','DELETE'] as $a): ?>
                                <option value="<?= $a ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select form-select-sm" style="width:auto;" name="city" onchange="this.form.submit()">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filter</button>
                        <?php $exportTableId='auditTable'; $exportFilename='audit_logs'; $exportTitle='System Audit Trail'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
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
                                <td><span class="badge bg-<?= $roleColors[$l['role_snapshot']] ?? 'secondary' ?>" style="font-size:0.6rem;"><?= strtoupper(str_replace('_',' ', $l['role_snapshot'] ?? '—')) ?></span></td>
                                <td style="font-size:0.8rem;"><?= h($l['city_name'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $actionColors[$l['action']] ?? 'secondary' ?>" style="font-size:0.65rem;"><?= h($l['action']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= h($l['details']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
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

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
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
