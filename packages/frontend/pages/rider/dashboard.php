<?php
// Rider home: is my permit valid, what do I owe, and what happened lately. Text goes through t() so it can be
// shown in the rider's language once a reviewed translation exists (Super Admin → Languages).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/permits.php';
require_role(['rider']);
$riderId = (int) ($_SESSION['rider_id'] ?? 0);
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT r.*, s.name AS stage_name FROM riders r LEFT JOIN stages s ON s.id = r.stage_id WHERE r.id = ?", [$riderId]);
$due = permit_amount_due($rider);
$daysLeft = daysUntil($rider['expiry_date']);
$valid = $rider['status'] === 'active' && $daysLeft !== null && $daysLeft >= 0;
$renewalOpen = !$valid || $daysLeft <= permit_renewal_window_days();
$recent = fetchAll("SELECT type, message, created_at FROM notifications WHERE rider_id = ? ORDER BY created_at DESC LIMIT 5", [$riderId]);
$firstName = mb_convert_case(explode(' ', trim($rider['full_name']))[0], MB_CASE_TITLE);

$statusText = match (true) {
    $valid && $daysLeft <= 30 => t('rider.dash.expires_soon', 'Your permit is valid, but it expires in {days} days.', ['days' => $daysLeft]),
    $valid                    => t('rider.dash.valid', 'Your permit is valid until {date}.', ['date' => formatDate($rider['expiry_date'])]),
    $rider['status'] === 'pending' => t('rider.dash.pending', 'Your registration is waiting for the first permit payment.'),
    default                   => t('rider.dash.expired', 'Your permit expired on {date}. Riding without a valid permit can lead to a fine.', ['date' => formatDate($rider['expiry_date'])]),
};

portal_start('rider', 'dashboard', t('rider.dash.title', 'My permit'), [
    'subtitle' => t('rider.dash.subtitle', 'Hello {name}. Here is where your permit stands.', ['name' => $firstName]),
]);
?>
<div class="layout-thirds">
    <section class="card">
        <div class="card-body">
            <p class="mb-1"><span class="badge bg-<?= $valid ? 'success' : ($rider['status'] === 'pending' ? 'warning text-dark' : 'danger') ?>"><?= h($valid ? t('rider.status.valid', 'Valid') : ($rider['status'] === 'pending' ? t('rider.status.pending', 'Waiting for payment') : t('rider.status.expired', 'Expired'))) ?></span></p>
            <h2 class="section-title mt-2"><?= h($statusText) ?></h2>
            <dl class="row mb-4 rider-facts">
                <dt class="col-sm-4"><?= h(t('rider.fact.permit', 'Permit number')) ?></dt><dd class="col-sm-8"><?= h($rider['id_number']) ?></dd>
                <dt class="col-sm-4"><?= h(t('rider.fact.stage', 'Stage')) ?></dt><dd class="col-sm-8"><?= h($rider['stage_name'] ?? '') ?></dd>
                <dt class="col-sm-4"><?= h(t('rider.fact.plate', 'Motorcycle')) ?></dt><dd class="col-sm-8"><?= h($rider['bike_plate']) ?></dd>
                <dt class="col-sm-4"><?= h(t('rider.fact.renewal', 'Next renewal')) ?></dt><dd class="col-sm-8"><?= h(formatCurrency($due['total'])) ?><?php if ($due['late_fee'] > 0): ?> <span class="text-danger small"><?= h(t('rider.fact.late', 'including a late fee of {amount}', ['amount' => formatCurrency($due['late_fee'])])) ?></span><?php endif; ?></dd>
            </dl>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($renewalOpen): ?>
                    <a class="btn btn-primary" href="<?= BASE_URL ?>/pages/rider/payment-history.php"><?= h(t('rider.dash.pay', 'Renew with mobile money')) ?></a>
                <?php endif; ?>
                <a class="btn btn-outline-primary" href="<?= BASE_URL ?>/pages/rider/my-id-card.php"><?= h(t('rider.dash.card', 'Show my ID card')) ?></a>
            </div>
            <?php if ($renewalOpen): ?><p class="small text-muted mt-3 mb-0"><?= h(t('rider.dash.cash', 'You can also pay cash to your stage chairperson and get a receipt.')) ?></p><?php endif; ?>
        </div>
    </section>

    <aside class="card">
        <div class="card-body">
            <h2 class="section-title"><?= h(t('rider.dash.recent', 'Recent messages')) ?></h2>
            <?php if (!$recent): ?>
                <p class="text-muted mb-0"><?= h(t('rider.dash.no_recent', 'No messages yet. Receipts and reminders appear here.')) ?></p>
            <?php else: ?>
                <ul class="list-unstyled mb-3 rider-activity">
                    <?php foreach ($recent as $n): ?>
                        <li><div class="fw-semibold small"><?= h(notification_label($n['type'])) ?></div><div class="small"><?= h(mb_strimwidth($n['message'], 0, 110, '…')) ?></div><div class="small text-muted"><?= h(timeAgo($n['created_at'])) ?></div></li>
                    <?php endforeach; ?>
                </ul>
                <a class="small" href="<?= BASE_URL ?>/pages/rider/notifications.php"><?= h(t('rider.dash.all_messages', 'See all messages')) ?></a>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php portal_end(); ?>
