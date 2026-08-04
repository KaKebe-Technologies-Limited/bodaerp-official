<?php
require_once __DIR__ . '/includes/functions.php';

$code = trim($_GET['code'] ?? '');
$rider = null;
if ($code !== '') {
    $rider = fetchOne("SELECT r.id_number, r.full_name, r.photo_path, r.status, r.expiry_date, r.member_since,
        s.name AS stage_name, c.name AS city_name, c.logo_path
        FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id
        WHERE r.id_number = ?", [$code]);
}
$statusMeta = [
    'active'  => ['label' => 'VALID / COMPLIANT', 'color' => '#198754', 'icon' => 'fa-check-circle'],
    'expired' => ['label' => 'EXPIRED — NOT COMPLIANT', 'color' => '#dc3545', 'icon' => 'fa-times-circle'],
    'pending' => ['label' => 'PENDING ACTIVATION', 'color' => '#f59e0b', 'icon' => 'fa-clock'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Verification - BodaERP</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e, #2d2d3f); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: 'Inter', sans-serif; }
        .verify-card { background: white; border-radius: 20px; max-width: 380px; width: 100%; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .verify-header { text-align: center; padding: 20px; }
        .verify-photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #f8f9fa; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
        .verify-status { padding: 12px 20px; text-align: center; color: white; font-weight: 800; font-size: 0.9rem; letter-spacing: 0.5px; }
        .verify-body { padding: 20px 24px; }
        .verify-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f3f5; font-size: 0.9rem; }
        .verify-row:last-child { border-bottom: none; }
        .verify-row strong { color: #6c757d; font-weight: 600; }
        .verify-footer { text-align: center; padding: 14px; background: #f8f9fa; font-size: 0.75rem; color: #6c757d; }
    </style>
</head>
<body>

<div class="verify-card">
    <?php if (!$rider): ?>
        <div class="verify-header pt-4">
            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-2"></i>
            <h5 class="fw-bold">ID Not Found</h5>
            <p class="text-muted small mb-0">No rider matches this code. It may be invalid or the card may have been revoked.</p>
        </div>
    <?php else: $meta = $statusMeta[$rider['status']] ?? $statusMeta['pending']; ?>
        <div class="verify-header">
            <img src="<?= $rider['photo_path'] ? h(BASE_URL.$rider['photo_path']) : BASE_URL.'/assets/images/avatar-placeholder.png' ?>" class="verify-photo mb-2">
            <h5 class="fw-bold mb-0"><?= h($rider['full_name']) ?></h5>
            <small class="text-muted"><?= h($rider['id_number']) ?></small>
        </div>
        <div class="verify-status" style="background: <?= $meta['color'] ?>;"><i class="fas <?= $meta['icon'] ?> me-1"></i> <?= $meta['label'] ?></div>
        <div class="verify-body">
            <div class="verify-row"><strong>City</strong><span><?= h($rider['city_name']) ?></span></div>
            <div class="verify-row"><strong>Stage</strong><span><?= h($rider['stage_name'] ?: '—') ?></span></div>
            <div class="verify-row"><strong>Member Since</strong><span><?= formatDate($rider['member_since'], 'M Y') ?></span></div>
            <div class="verify-row"><strong>Expiry Date</strong><span><?= formatDate($rider['expiry_date']) ?></span></div>
        </div>
    <?php endif; ?>
    <div class="verify-footer"><i class="fas fa-shield-alt me-1"></i>Verified via BodaERP · <?= date('M d, Y H:i') ?></div>
</div>

</body>
</html>
