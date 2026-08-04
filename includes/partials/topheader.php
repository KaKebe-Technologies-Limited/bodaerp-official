<?php
$roleLabels = [
    'super_admin' => 'Kakebe Tech · Platform',
    'city_admin'  => $_SESSION['city_name'] ?? 'City Council',
    'chairperson' => 'Stage Chairperson',
    'rider'       => 'Rider',
];
$roleLabel = $roleLabels[$_SESSION['role']] ?? $_SESSION['role'];
?>
<header class="top-header">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 fw-bold">
                <span class="text-gradient-blue"><?= h($pageTitle ?? '') ?></span>
            </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="user-info">
                <img src="<?= BASE_URL ?>/assets/images/avatar-placeholder.png" alt="<?= h($_SESSION['name']) ?>" class="rounded-circle" width="40" height="40">
                <div>
                    <span class="fw-bold d-block" style="font-size: 0.85rem;"><?= h($_SESSION['name']) ?></span>
                    <small class="text-muted" style="font-size: 0.7rem;"><?= h($roleLabel) ?></small>
                </div>
            </div>
        </div>
    </div>
</header>
