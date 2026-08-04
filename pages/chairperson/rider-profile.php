<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'my-riders';
$stageId = $_SESSION['stage_id'];
$riderId = (int) ($_GET['id'] ?? 0);

$rider = fetchOne("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id=r.stage_id WHERE r.id = ? AND r.stage_id = ?", [$riderId, $stageId]);
if (!$rider) redirect('/pages/chairperson/my-riders.php');

$payments = fetchAll("SELECT * FROM payments WHERE rider_id = ? ORDER BY paid_at DESC", [$riderId]);
$isNew = isset($_GET['new']);
$sc = ['active'=>'bg-success','expired'=>'bg-danger','pending'=>'bg-warning text-dark'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Rider Profile - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Rider Profile'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($isNew): ?><div class="alert alert-success">Rider registered successfully! <a href="<?= BASE_URL ?>/pages/chairperson/id-preview.php?id=<?= $riderId ?>">View ID Card</a></div><?php endif; ?>

        <div class="card mb-4"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold mb-1"><?= h($rider['full_name']) ?> <span class="badge <?= $sc[$rider['status']] ?>"><?= ucfirst($rider['status']) ?></span></h4>
                    <p class="text-muted mb-0"><?= h($rider['id_number']) ?> · <?= h($rider['stage_name']) ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/pages/chairperson/id-preview.php?id=<?= $riderId ?>" class="btn btn-outline-primary"><i class="fas fa-id-card me-1"></i>ID Card</a>
                    <a href="<?= BASE_URL ?>/pages/chairperson/edit-rider.php?id=<?= $riderId ?>" class="btn btn-primary"><i class="fas fa-edit me-1"></i>Edit</a>
                </div>
            </div>
        </div></div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#details">Details</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pmts">Payments</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="details">
                <div class="card"><div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary">Personal Information</h6>
                            <p><strong>NIN:</strong> <?= h($rider['nin']) ?></p>
                            <p><strong>DOB:</strong> <?= formatDate($rider['date_of_birth']) ?></p>
                            <p><strong>Gender:</strong> <?= h($rider['gender'] ?: '—') ?></p>
                            <p><strong>Marital Status:</strong> <?= h($rider['marital_status'] ?: '—') ?></p>
                            <p><strong>Phone:</strong> <?= h($rider['phone']) ?></p>
                            <p><strong>Email:</strong> <?= h($rider['email'] ?: '—') ?></p>
                            <p><strong>Address:</strong> <?= h($rider['physical_address'] ?: '—') ?></p>
                            <p><strong>Next of Kin:</strong> <?= h($rider['next_of_kin_name'] ?: '—') ?> (<?= h($rider['next_of_kin_contact'] ?: '—') ?>)</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary">Bike & Stage</h6>
                            <p><strong>Plate:</strong> <?= h($rider['bike_plate']) ?></p>
                            <p><strong>Model:</strong> <?= h($rider['bike_model'] ?: '—') ?></p>
                            <p><strong>Stage:</strong> <?= h($rider['stage_name']) ?></p>
                            <p><strong>Route:</strong> <?= h($rider['route'] ?: '—') ?></p>
                            <p><strong>Member Since:</strong> <?= formatDate($rider['member_since']) ?></p>
                            <p><strong>Expiry Date:</strong> <?= formatDate($rider['expiry_date']) ?></p>
                            <p><strong>Annual Tax:</strong> <?= h(formatCurrency($rider['annual_tax'])) ?></p>
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="tab-pane fade" id="pmts">
                <div class="card"><div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Fiscal Year</th><th>Amount</th><th>Date Paid</th><th>Receipt#</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$payments): ?><tr><td colspan="5" class="text-center py-4 text-muted">No payment history</td></tr><?php endif; ?>
                        <?php foreach ($payments as $p): $psc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                            <tr><td><?= h($p['fiscal_year']) ?></td><td><?= h(formatCurrency($p['amount'])) ?></td><td><?= formatDate($p['paid_at']) ?></td><td><code><?= h($p['receipt_number']) ?></code></td><td><span class="badge <?= $psc[$p['status']] ?>"><?= h($p['status']) ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div></div>
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
