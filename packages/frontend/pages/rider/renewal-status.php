<?php
// Renewal: how long the permit has left, what the next renewal costs (with any late fee) and how to pay.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/permits.php';
require_role(['rider']);
$riderId = (int) ($_SESSION['rider_id'] ?? 0);
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
$due = permit_amount_due($rider);
$daysLeft = daysUntil($rider['expiry_date']);
$valid = $rider['status'] === 'active' && $daysLeft !== null && $daysLeft >= 0;
$block = permit_payment_block_reason($rider);
$elapsed = $valid ? max(0, min(100, (int) round(100 * (365 - $daysLeft) / 365))) : 100;
$ussd = trim(get_setting('ussd_code', ''));

portal_start('rider', 'renewal-status', t('rider.renew.title', 'Renewal'), [
    'subtitle' => t('rider.renew.subtitle', 'When your permit runs out and how to renew it'),
]);
?>
<div class="layout-thirds">
    <section class="card">
        <div class="card-body">
            <h2 class="section-title"><?= h($valid ? t('rider.renew.days_left', '{days} days left on your permit', ['days' => $daysLeft]) : ($rider['status'] === 'pending' ? t('rider.renew.pending', 'Your permit starts when the first payment is confirmed') : t('rider.renew.expired', 'Your permit has expired'))) ?></h2>
            <?php if ($valid): ?>
                <div class="progress mb-2" role="progressbar" aria-label="<?= h(t('rider.renew.used', 'Part of the year used')) ?>" aria-valuenow="<?= $elapsed ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-<?= $daysLeft <= 30 ? 'danger' : 'success' ?>" style="width: <?= $elapsed ?>%"></div>
                </div>
                <p class="small text-muted mb-4"><?= h(t('rider.renew.valid_until', 'Valid until {date}.', ['date' => formatDate($rider['expiry_date'])])) ?></p>
            <?php elseif ($rider['expiry_date']): ?>
                <p class="mb-4"><?= h(t('rider.renew.expired_on', 'It expired on {date}. Renew now to ride legally again.', ['date' => formatDate($rider['expiry_date'])])) ?></p>
            <?php endif; ?>

            <dl class="row rider-facts mb-0">
                <dt class="col-sm-5"><?= h(t('rider.renew.fee', 'Permit fee')) ?></dt><dd class="col-sm-7"><?= h(formatCurrency($due['fee'])) ?></dd>
                <?php if ($due['late_fee'] > 0): ?>
                <dt class="col-sm-5"><?= h(t('rider.renew.late_fee', 'Late fee')) ?></dt><dd class="col-sm-7"><?= h(formatCurrency($due['late_fee'])) ?> <span class="small text-muted"><?= h(t('rider.renew.late_why', '(expired {days} days ago; the grace period is {grace} days)', ['days' => $due['days_late'], 'grace' => $due['grace_days']])) ?></span></dd>
                <?php endif; ?>
                <dt class="col-sm-5"><?= h(t('rider.renew.total', 'To pay at renewal')) ?></dt><dd class="col-sm-7 fw-bold"><?= h(formatCurrency($due['total'])) ?></dd>
            </dl>
        </div>
    </section>

    <aside class="card">
        <div class="card-body">
            <h2 class="section-title"><?= h(t('rider.renew.how', 'How to renew')) ?></h2>
            <?php if ($block): ?>
                <p class="mb-0"><?= h($block) ?></p>
            <?php else: ?>
                <ol class="ps-3 mb-3 rider-steps">
                    <li><?= h(t('rider.renew.step_momo', 'Pay with MTN or Airtel mobile money from the Payments page.')) ?></li>
                    <?php if ($ussd !== ''): ?><li><?= h(t('rider.renew.step_ussd', 'Or dial {code} from any phone, no internet needed.', ['code' => $ussd])) ?></li><?php endif; ?>
                    <li><?= h(t('rider.renew.step_cash', 'Or pay cash to your stage chairperson and ask for the receipt.')) ?></li>
                </ol>
                <a class="btn btn-primary w-100" href="<?= BASE_URL ?>/pages/rider/payment-history.php"><?= h(t('rider.renew.pay', 'Go to payments')) ?></a>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php portal_end(); ?>
