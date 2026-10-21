<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['government']);
$active = 'registry';

$search = trim($_GET['q'] ?? '');
$filterCity = $_GET['city'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$sql = "SELECT r.*, c.name AS city_name, s.name AS stage_name,
        (SELECT COUNT(*) FROM payments p WHERE p.rider_id = r.id AND p.status = 'Confirmed') AS payment_count,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.rider_id = r.id AND p.status = 'Confirmed') AS payment_total,
        (SELECT COUNT(*) FROM enforcement_actions e WHERE e.rider_id = r.id) AS enforcement_count
        FROM riders r
        LEFT JOIN cities c ON c.id = r.city_id
        LEFT JOIN stages s ON s.id = r.stage_id
        WHERE r.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= " AND (r.full_name LIKE ? OR r.nin LIKE ? OR r.bike_plate LIKE ? OR r.id_number LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($filterCity !== '')   { $sql .= " AND r.city_id = ?"; $params[] = $filterCity; }
if ($filterStatus !== '') { $sql .= " AND r.status = ?"; $params[] = $filterStatus; }
$sql .= " ORDER BY r.id DESC LIMIT 300";
$riders = fetchAll($sql, $params);

$ridersById = [];
foreach ($riders as $r) { $ridersById[$r['id']] = $r; }

$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");

$totalRidersAll  = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
$activeRidersAll = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL AND status = 'active'");
$expiredRidersAll = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL AND status = 'expired'");
$flaggedRidersAll = (int) fetchValue("SELECT COUNT(DISTINCT rider_id) FROM enforcement_actions WHERE rider_id IS NOT NULL");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'National Rider Registry - BodaERP Government Portal'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">National Rider Registry</span></h5>
                    <small class="text-muted d-none d-sm-block">Search any rider by name, NIN, plate, or ID number</small>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRidersAll) ?></div><div class="stat-hero-label">Total Riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-user-check"></i></div><div><div class="stat-hero-value"><?= number_format($activeRidersAll) ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-user-clock"></i></div><div><div class="stat-hero-value"><?= number_format($expiredRidersAll) ?></div><div class="stat-hero-label">Expired</div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-flag"></i></div><div><div class="stat-hero-value"><?= number_format($flaggedRidersAll) ?></div><div class="stat-hero-label">Flagged (Enforcement)</div></div></div></div>
        </div>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center" method="get">
                    <div class="col-md-4"><input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search name, NIN, plate, or ID number..."></div>
                    <div class="col-md-3">
                        <select class="form-select" name="city">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= h($c['id']) ?>" <?= $filterCity === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Active</option>
                            <option value="expired" <?= $filterStatus==='expired'?'selected':'' ?>>Expired</option>
                            <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Search</button></div>
                    <div class="col-md-1 text-md-end">
                        <?php $exportTableId='registryTable'; $exportFilename='national_rider_registry'; $exportTitle='National Rider Registry'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="registryTable">
                        <thead><tr><th>Rider</th><th>ID Number</th><th>NIN</th><th>Plate</th><th>City / Stage</th><th>Status</th><th>Expiry</th><th class="no-export">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($riders as $r): $sc = ['active'=>'success','expired'=>'danger','pending'=>'warning']; ?>
                            <tr>
                                <td><strong style="font-size:0.85rem;"><?= h($r['full_name']) ?></strong><br><small class="text-muted"><?= h($r['phone']) ?></small></td>
                                <td><code><?= h($r['id_number']) ?></code></td>
                                <td style="font-size:0.8rem;"><?= h($r['nin'] ?: '—') ?></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td style="font-size:0.82rem;"><?= h($r['city_name'] ?? '—') ?><br><small class="text-muted"><?= h($r['stage_name'] ?? '') ?></small></td>
                                <td><span class="badge bg-<?= $sc[$r['status']] ?? 'secondary' ?>"><?= strtoupper($r['status']) ?></span></td>
                                <td style="font-size:0.8rem;"><?= $r['expiry_date'] ? formatDate($r['expiry_date']) : '—' ?></td>
                                <td class="no-export"><button class="btn btn-sm btn-outline-primary" onclick="viewRider(<?= (int) $r['id'] ?>)"><i class="fas fa-eye"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$riders): ?>
                    <div class="empty-state">
                        <i class="fas fa-id-card"></i>
                        <h5>No riders found</h5>
                        <p>Try adjusting your search or filters.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="riderModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:var(--dark-black-gradient);color:white;border-bottom:none;">
                <h5 class="modal-title fw-bold" id="riderModalTitle"><i class="fas fa-id-card me-2"></i>Rider Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="riderModalBody"></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    const ridersData = <?= json_encode($ridersById) ?>;

    function viewRider(id) {
        const r = ridersData[id];
        if (!r) return;
        document.getElementById('riderModalTitle').innerHTML = '<i class="fas fa-id-card me-2"></i>' + r.full_name;
        const statusColor = { active: 'success', expired: 'danger', pending: 'warning' }[r.status] || 'secondary';
        document.getElementById('riderModalBody').innerHTML = `
            <div class="row g-3">
                <div class="col-md-6"><small class="text-muted d-block">ID Number</small><strong>${r.id_number}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">NIN</small><strong>${r.nin || '—'}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">Phone</small><strong>${r.phone}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">Bike Plate</small><strong>${r.bike_plate}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">City / Stage</small><strong>${r.city_name || '—'} · ${r.stage_name || '—'}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">Status</small><span class="badge bg-${statusColor}">${(r.status||'').toUpperCase()}</span></div>
                <div class="col-md-6"><small class="text-muted d-block">Member Since</small><strong>${r.member_since || '—'}</strong></div>
                <div class="col-md-6"><small class="text-muted d-block">Expiry Date</small><strong>${r.expiry_date || '—'}</strong></div>
            </div>
            <hr>
            <div class="row g-3 text-center">
                <div class="col-4"><div class="stat-card-modern"><div style="width:100%"><span class="stat-number">${r.payment_count}</span><span class="stat-label d-block">Payments</span></div></div></div>
                <div class="col-4"><div class="stat-card-modern"><div style="width:100%"><span class="stat-number">UGX ${Number(r.payment_total).toLocaleString()}</span><span class="stat-label d-block">Total Paid</span></div></div></div>
                <div class="col-4"><div class="stat-card-modern"><div style="width:100%"><span class="stat-number" style="color:${r.enforcement_count > 0 ? '#dc3545' : ''}">${r.enforcement_count}</span><span class="stat-label d-block">Enforcement</span></div></div></div>
            </div>
        `;
        new bootstrap.Modal(document.getElementById('riderModal')).show();
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
