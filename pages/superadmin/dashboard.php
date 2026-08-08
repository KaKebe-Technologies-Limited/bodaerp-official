<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

$pageTitle = 'Platform Overview';
$active = 'dashboard';

$totalCities = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'active'");
$totalRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
$totalStages = (int) fetchValue("SELECT COUNT(*) FROM stages");
$totalUsers  = (int) fetchValue("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");

$cities = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count
    FROM cities c ORDER BY c.created_at");

$recentUsers = fetchAll("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5");
$roleColors = ['super_admin' => 'primary', 'city_admin' => 'success', 'chairperson' => 'warning', 'rider' => 'info'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Super Admin - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .tenant-card { background: white; border-radius: 14px; padding: 20px; border-left: 4px solid #0d6efd; box-shadow: 0 2px 12px rgba(0,0,0,0.05); transition: all 0.3s ease; cursor: pointer; }
        .tenant-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.1); }
        .tenant-card.pending { border-left-color: #f59e0b; }
        .tenant-card.inactive { border-left-color: #adb5bd; opacity: 0.7; }
        .role-badge-sa { background: linear-gradient(135deg,#0d6efd,#1a3a5c); color: white; font-size: 0.65rem; padding: 3px 12px; border-radius: 20px; font-weight: 700; }
        @media (max-width: 576px) {
            .tenant-card { padding: 14px; }
        }
    </style>
</head>
<body>

<?php $active = 'dashboard'; require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <div>
                    <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Platform Overview</span></h5>
                    <small class="text-muted d-none d-sm-block">Kakebe Technologies — Multi-City Dashboard</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-wrap">
                <a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">Add </span>City</a>
                <div class="user-info">
                    <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" class="rounded-circle" width="40" height="40">
                    <div>
                        <span class="fw-bold d-block" style="font-size:0.85rem;"><?= h($_SESSION['name']) ?></span>
                        <span class="role-badge-sa">Platform Admin</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="content-area">

        <div class="dash-banner theme-dark">
            <div class="d-flex justify-content-between align-items-center flex-wrap dash-banner-row">
                <div>
                    <h4>⚙️ Platform Command Center</h4>
                    <p>Overseeing <?= $totalCities ?> cities, <?= number_format($totalRiders) ?> riders and <?= $totalStages ?> stages across BodaERP.</p>
                </div>
                <span class="dash-badge"><i class="fas fa-signal me-1"></i>All systems operational</span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="stat-card-modern"><div class="icon-badge blue"><i class="fas fa-city"></i></div><div><span class="stat-number"><?= $totalCities ?></span><div class="stat-label">Active Cities</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card-modern"><div class="icon-badge green"><i class="fas fa-users"></i></div><div><span class="stat-number"><?= number_format($totalRiders) ?></span><div class="stat-label">Total Riders</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card-modern"><div class="icon-badge gold"><i class="fas fa-map-marker-alt"></i></div><div><span class="stat-number"><?= $totalStages ?></span><div class="stat-label">Total Stages</div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card-modern"><div class="icon-badge dark"><i class="fas fa-user-shield"></i></div><div><span class="stat-number"><?= $totalUsers ?></span><div class="stat-label">Platform Users</div></div></div></div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0"><i class="fas fa-city text-primary me-2"></i>Registered Cities</h6>
            <a href="<?= BASE_URL ?>/pages/superadmin/cities.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-cog me-1"></i>Manage All</a>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ($cities as $t): $statusColor = $t['status'] === 'active' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'secondary'); ?>
            <div class="col-sm-6 col-xl-3">
                <div class="tenant-card <?= h($t['status']) ?>" onclick="window.location='<?= BASE_URL ?>/pages/superadmin/cities.php#<?= h($t['id']) ?>'">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="<?= h(BASE_URL . $t['logo_path']) ?>" width="36" height="36" class="rounded-circle border" style="object-fit:contain;background:white;" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                        <div>
                            <div class="fw-bold" style="font-size:0.9rem;"><?= h($t['name']) ?></div>
                            <span class="badge bg-<?= $statusColor ?>" style="font-size:0.6rem;"><?= strtoupper($t['status']) ?></span>
                        </div>
                    </div>
                    <div class="row g-0 text-center">
                        <div class="col-4 border-end"><div class="fw-bold text-primary"><?= (int) $t['stage_count'] ?></div><small class="text-muted" style="font-size:0.65rem;">Stages</small></div>
                        <div class="col-4 border-end"><div class="fw-bold text-success"><?= number_format($t['rider_count']) ?></div><small class="text-muted" style="font-size:0.65rem;">Riders</small></div>
                        <div class="col-4"><div class="fw-bold text-warning"><?= (int) $t['revenue_split_platform'] ?>%</div><small class="text-muted" style="font-size:0.65rem;">Platform</small></div>
                    </div>
                    <div class="mt-3 pt-2 border-top d-flex gap-1 flex-wrap">
                        <a href="<?= BASE_URL ?>/pages/superadmin/cities.php#<?= h($t['id']) ?>" class="btn btn-outline-primary btn-sm" style="font-size:0.7rem;" onclick="event.stopPropagation()"><i class="fas fa-edit me-1"></i>Edit</a>
                        <a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php#<?= h($t['id']) ?>" class="btn btn-outline-warning btn-sm" style="font-size:0.7rem;" onclick="event.stopPropagation()"><i class="fas fa-percentage me-1"></i>Splits</a>
                        <a href="<?= BASE_URL ?>/pages/superadmin/users.php?city=<?= h($t['id']) ?>" class="btn btn-outline-success btn-sm" style="font-size:0.7rem;" onclick="event.stopPropagation()"><i class="fas fa-users me-1"></i>Users</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-percentage text-warning me-2"></i>Platform Revenue Splits Summary</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead><tr><th>City</th><th>City %</th><th>Assoc %</th><th>Kakebe %</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($cities as $t): ?>
                                    <tr>
                                        <td><strong><?= h($t['name']) ?></strong></td>
                                        <td><span class="badge bg-primary"><?= (int) $t['revenue_split_city'] ?>%</span></td>
                                        <td><span class="badge bg-success"><?= (int) $t['revenue_split_association'] ?>%</span></td>
                                        <td><span class="badge bg-info text-dark"><?= (int) $t['revenue_split_platform'] ?>%</span></td>
                                        <td><a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php#<?= h($t['id']) ?>" class="btn btn-xs btn-outline-secondary" style="font-size:0.65rem;padding:2px 8px;"><i class="fas fa-edit"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="<?= BASE_URL ?>/pages/superadmin/revenue-splits.php" class="btn btn-sm btn-outline-warning mt-2"><i class="fas fa-edit me-1"></i>Edit Splits</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-users text-primary me-2"></i>Recent Users Added</h6>
                        <div>
                        <?php foreach ($recentUsers as $u): ?>
                            <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" width="32" height="32" class="rounded-circle">
                                <div class="flex-1">
                                    <div class="fw-bold" style="font-size:0.82rem;"><?= h($u['name']) ?></div>
                                    <small class="text-muted"><?= h($u['email']) ?></small>
                                </div>
                                <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>" style="font-size:0.6rem;"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <a href="<?= BASE_URL ?>/pages/superadmin/users.php" class="btn btn-sm btn-outline-primary mt-2"><i class="fas fa-list me-1"></i>All Users</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
</body>
</html>
