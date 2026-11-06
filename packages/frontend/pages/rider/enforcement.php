<?php
// Warnings, fines, suspensions and impoundments recorded against the rider, and an appeal for an open one.
// The city answers appeals on its Enforcement page; the answer comes back as a notice (and SMS).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/enforcement.php';
require_role(['rider']);
$riderId = (int) ($_SESSION['rider_id'] ?? 0);
if (!$riderId) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $error = enforcement_appeal($riderId, (int) ($_POST['action_id'] ?? 0), (string) ($_POST['appeal'] ?? ''), (int) $_SESSION['user_id']);
    flash($error === '' ? 'success' : 'error', $error === '' ? t('rider.enf.appeal_sent', 'Your appeal was sent to the city council. You will get their answer in your notifications.') : $error);
    redirect('/pages/rider/enforcement.php');
}

$actions = fetchAll("SELECT e.*, c.name AS city_name, c.contact_phone, c.address FROM enforcement_actions e LEFT JOIN cities c ON c.id = e.city_id
    WHERE e.rider_id = ? ORDER BY e.action_date DESC, e.id DESC", [$riderId]);
$suspension = rider_suspension($riderId);
$city = fetchOne("SELECT c.name, c.contact_phone, c.contact_email, c.address FROM riders r JOIN cities c ON c.id = r.city_id WHERE r.id = ?", [$riderId]);

portal_start('rider', 'enforcement', t('rider.enf.title', 'Enforcement records'), [
    'subtitle' => t('rider.enf.subtitle', 'Warnings, fines and suspensions the city council recorded against your permit'),
]);
?>
<?php if ($suspension): ?>
<div class="alert alert-danger d-flex gap-3 align-items-start" role="alert">
    <i class="fas fa-ban fa-lg mt-1" aria-hidden="true"></i>
    <div><strong><?= h(t('rider.enf.suspended', 'Your permit is suspended')) ?></strong><br>
    <?= h(t('rider.enf.suspended_text', 'Since {date} ({code}). A roadside check shows the permit as suspended until the city council lifts it. Paying the permit fee does not lift a suspension.', ['date' => formatDate($suspension['action_date']), 'code' => $suspension['action_code']])) ?></div>
</div>
<?php endif; ?>

<div class="layout-thirds">
    <section class="card"><div class="card-body">
        <h2 class="section-title"><?= h(t('rider.enf.list', 'Your records')) ?></h2>
        <?php if (!$actions): ?>
            <?= empty_state('fa-circle-check', t('rider.enf.none', 'No enforcement records'), t('rider.enf.none_text', 'Nothing has been recorded against your permit.')) ?>
        <?php endif; ?>
        <?php foreach ($actions as $a): ?>
        <article class="border rounded-3 p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div><strong><?= h(ENFORCEMENT_LABELS[$a['type']] ?? $a['type']) ?></strong> <code class="ms-1"><?= h($a['action_code']) ?></code><br>
                    <small class="text-muted"><?= formatDate($a['action_date']) ?><?= $a['plate'] ? ' · ' . h($a['plate']) : '' ?></small></div>
                <div class="text-end"><span class="badge bg-<?= $a['status'] === 'pending' ? 'danger' : 'secondary' ?>"><?= h(ENFORCEMENT_STATUS_LABELS[$a['status']] ?? $a['status']) ?></span>
                    <?php if ((float) $a['amount'] > 0): ?><br><strong><?= formatCurrency((float) $a['amount']) ?></strong><?php endif; ?></div>
            </div>
            <p class="small mb-2"><?= nl2br(h($a['description'])) ?></p>
            <?php if ($a['appeal_status'] !== null): ?>
                <div class="small bg-light rounded-2 p-2">
                    <strong><?= h(t('rider.enf.your_appeal', 'Your appeal')) ?></strong> (<?= formatDate($a['appeal_at']) ?>):
                    <?= h(['open' => t('rider.enf.appeal_open', 'waiting for an answer'), 'upheld' => t('rider.enf.appeal_upheld', 'accepted'), 'rejected' => t('rider.enf.appeal_rejected', 'not accepted')][$a['appeal_status']]) ?>
                    <div class="text-muted mt-1"><?= nl2br(h($a['appeal_text'])) ?></div>
                    <?php if ($a['appeal_response']): ?><div class="mt-1"><strong><?= h(t('rider.enf.answer', 'Answer')) ?>:</strong> <?= h($a['appeal_response']) ?></div><?php endif; ?>
                </div>
            <?php elseif ($a['status'] === 'pending'): ?>
                <details class="small">
                    <summary><?= h(t('rider.enf.appeal', 'Appeal this action')) ?></summary>
                    <form method="post" class="mt-2">
                        <?= csrf_field() ?><input type="hidden" name="action_id" value="<?= (int) $a['id'] ?>">
                        <label class="form-label" for="appeal<?= (int) $a['id'] ?>"><?= h(t('rider.enf.appeal_label', 'Why is this action wrong?')) ?></label>
                        <textarea class="form-control mb-2" id="appeal<?= (int) $a['id'] ?>" name="appeal" rows="3" minlength="20" maxlength="3000" required></textarea>
                        <button class="btn btn-primary btn-sm" data-busy-label="<?= h(t('rider.enf.sending', 'Sending')) ?>"><?= h(t('rider.enf.send', 'Send appeal')) ?></button>
                    </form>
                </details>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div></section>

    <aside class="card"><div class="card-body">
        <h2 class="section-title"><?= h(t('rider.enf.how', 'Fines and appeals')) ?></h2>
        <p class="small"><?= h(t('rider.enf.how_text', 'Pay a fine at the city council office and keep the receipt. The record shows Resolved once the council confirms it. If you think an action is wrong, appeal it here; one appeal per action.')) ?></p>
        <?php if ($city): ?>
        <p class="small mb-0"><strong><?= h($city['name']) ?></strong><?= $city['address'] ? '<br>' . h($city['address']) : '' ?><?= $city['contact_phone'] ? '<br><a href="tel:' . h(preg_replace('/[^\d+]/', '', $city['contact_phone'])) . '">' . h($city['contact_phone']) . '</a>' : '' ?></p>
        <?php endif; ?>
    </div></aside>
</div>
<?php portal_end();
