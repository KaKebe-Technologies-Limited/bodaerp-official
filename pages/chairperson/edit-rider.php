<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'my-riders';
$stageId = $_SESSION['stage_id'];
$riderId = (int) ($_GET['id'] ?? 0);

$rider = fetchOne("SELECT * FROM riders WHERE id = ? AND stage_id = ?", [$riderId, $stageId]);
if (!$rider) redirect('/pages/chairperson/my-riders.php');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = strtoupper(trim($_POST['full_name'] ?? ''));
    $phone = '+256' . preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '');
    $plate = strtoupper(trim($_POST['bike_plate'] ?? ''));
    $model = trim($_POST['bike_model'] ?? '');
    $status = $_POST['status'] ?? $rider['status'];

    if ($fullName === '' || $plate === '') {
        $errors[] = 'Full name and bike plate are required.';
    } else {
        runQuery("UPDATE riders SET full_name=?,phone=?,email=?,physical_address=?,bike_plate=?,bike_model=?,status=? WHERE id=? AND stage_id=?",
            [$fullName,$phone,$email,$address,$plate,$model,$status,$riderId,$stageId]);
        audit_log('UPDATE', 'rider', (string) $riderId, 'Rider updated');
        redirect('/pages/chairperson/rider-profile.php?id=' . $riderId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Edit Rider - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Edit Rider'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card"><div class="card-body p-5">
            <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e); ?></div><?php endif; ?>
            <h4 class="fw-bold mb-4"><i class="fas fa-user-edit text-primary me-2"></i>Edit Rider</h4>
            <form method="post">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" name="full_name" class="form-control form-control-lg" value="<?= h($rider['full_name']) ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">+256</span><input type="tel" name="phone" class="form-control form-control-lg" value="<?= h(preg_replace('/^\+256/', '', $rider['phone'])) ?>" required></div>
                    </div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control form-control-lg" value="<?= h($rider['email']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Bike Plate</label><input type="text" name="bike_plate" class="form-control form-control-lg" value="<?= h($rider['bike_plate']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Bike Model</label><input type="text" name="bike_model" class="form-control form-control-lg" value="<?= h($rider['bike_model']) ?>"></div>
                    <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-lg">
                            <option value="active" <?= $rider['status']==='active'?'selected':'' ?>>Active</option>
                            <option value="expired" <?= $rider['status']==='expired'?'selected':'' ?>>Expired</option>
                            <option value="pending" <?= $rider['status']==='pending'?'selected':'' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3"><label class="form-label fw-semibold">Physical Address</label><input type="text" name="address" class="form-control form-control-lg" value="<?= h($rider['physical_address']) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>Save Changes</button>
                <a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= $riderId ?>" class="btn btn-outline-secondary btn-lg px-5">Cancel</a>
            </form>
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
