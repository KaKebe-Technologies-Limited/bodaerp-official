<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'my-profile';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT r.*, s.name AS stage_name, s.route AS stage_route, s.chairperson_name, s.chairperson_phone
    FROM riders r LEFT JOIN stages s ON s.id = r.stage_id WHERE r.id = ?", [$riderId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'My Profile - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'My Profile'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>To update your details, please contact your stage chairperson.</div>
        <div class="card"><div class="card-body">
            <h6 class="fw-bold text-primary mb-3">Personal Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label fw-semibold">Full Name</label><input type="text" class="form-control" value="<?= h($rider['full_name']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">NIN</label><input type="text" class="form-control" value="<?= h($rider['nin']) ?>" readonly></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Date of Birth</label><input type="text" class="form-control" value="<?= formatDate($rider['date_of_birth']) ?>" readonly></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Gender</label><input type="text" class="form-control" value="<?= h($rider['gender'] ?: '—') ?>" readonly></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Marital Status</label><input type="text" class="form-control" value="<?= h($rider['marital_status'] ?: '—') ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Phone Number</label><input type="text" class="form-control" value="<?= h($rider['phone']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email Address</label><input type="text" class="form-control" value="<?= h($rider['email'] ?: '—') ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Physical Address</label><input type="text" class="form-control" value="<?= h($rider['physical_address'] ?: '—') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Next of Kin</label><input type="text" class="form-control" value="<?= h($rider['next_of_kin_name'] ?: '—') ?>" readonly></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Next of Kin Contact</label><input type="text" class="form-control" value="<?= h($rider['next_of_kin_contact'] ?: '—') ?>" readonly></div>
            </div>
            <h6 class="fw-bold text-primary mb-3">Bike & Stage</h6>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Bike Plate</label><input type="text" class="form-control" value="<?= h($rider['bike_plate']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Bike Model</label><input type="text" class="form-control" value="<?= h($rider['bike_model'] ?: '—') ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Stage</label><input type="text" class="form-control" value="<?= h($rider['stage_name']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Route</label><input type="text" class="form-control" value="<?= h($rider['stage_route'] ?: '—') ?>" readonly></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Stage Chairperson</label><input type="text" class="form-control" value="<?= h(($rider['chairperson_name'] ?: '—') . ($rider['chairperson_phone'] ? ' · ' . $rider['chairperson_phone'] : '')) ?>" readonly></div>
            </div>
        </div></div>
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
