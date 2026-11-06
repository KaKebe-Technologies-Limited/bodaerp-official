<?php
// The rider's own ID card: both sides at real size, print or save as PDF, the QR code as an image, the card's
// history, and "my card is lost" which stops the old QR code at once so nobody else can pass a check with it.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['rider']);
$riderId = (int) ($_SESSION['rider_id'] ?? 0);
if (!$riderId) redirect('/login.php');
$cardRider = id_card_rider($riderId);
if (!$cardRider) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['card_action'] ?? '') === 'lost') {
    csrf_verify();
    if (!rate_limit('card-lost:' . $riderId, 3, 86400)) {
        flash('error', t('rider.card.lost_limit', 'You have reported a lost card several times today. Ask your stage chairperson for help.'));
    } elseif (id_card_end($riderId, 'lost', t('rider.card.lost_by_rider', 'Reported lost by the rider'), (int) $_SESSION['user_id'])) {
        flash('success', t('rider.card.lost_done', 'Your card is marked lost and its QR code no longer works. Ask your stage chairperson for a new card.'));
    } else {
        flash('error', t('rider.card.lost_none', 'There is no valid card to report.'));
    }
    redirect('/pages/rider/my-id-card.php');
}

$cardRow = id_card_ensure($riderId);
$cardHistory = id_card_history($riderId);
$cardCity = fetchOne("SELECT * FROM cities WHERE id = ?", [$cardRider['city_id']]);
$cardCfg = id_card_settings();
$chair = fetchOne("SELECT name, phone FROM users WHERE stage_id = ? AND role = 'chairperson' AND status = 'active' AND deleted_at IS NULL ORDER BY id LIMIT 1", [(int) $cardRider['stage_id']]);

portal_start('rider', 'my-id-card', t('rider.card.title', 'My ID card'), [
    'subtitle' => t('rider.card.subtitle', 'Carry it whenever you ride. Officers scan the QR code to check your permit.'),
    'head' => '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . @filemtime(__DIR__ . '/../../assets/css/id-card.css') . '">',
]);
?>
<div class="doc-layout">
    <aside class="doc-rail">
        <?= action_rail([
            t('rider.card.rail_print', 'Print') => $cardRow ? [
                ['label' => t('rider.card.print', 'Print or save as PDF'), 'icon' => 'print', 'tone' => 'primary', 'attrs' => ['data-card-print' => 'both']],
            ] : [],
            t('rider.card.rail_share', 'Check and share') => $cardRow ? [
                ['label' => t('rider.card.enlarge', 'Enlarge'), 'icon' => 'magnifying-glass-plus', 'attrs' => ['data-card-zoom' => true]],
                ['label' => t('rider.card.save_qr', 'Save QR image'), 'icon' => 'download', 'attrs' => ['data-qr-download' => true]],
                ['label' => t('rider.card.copy_link', 'Copy my check link'), 'icon' => 'link', 'attrs' => ['data-copy' => verifyUrl($riderId)]],
            ] : [],
            t('rider.card.rail_go', 'Go to') => [
                ['label' => t('rider.card.go_payments', 'Payments and receipts'), 'icon' => 'receipt', 'href' => BASE_URL . '/pages/rider/payment-history.php'],
            ],
        ], t('rider.card.actions', 'Card actions')) ?>
    </aside>

    <div class="doc-main no-print">
        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title"><?= h(t('rider.card.about', 'About this card')) ?></h2>
            <?php if ($cardRow): ?>
            <dl class="row small mb-0">
                <dt class="col-5 text-muted fw-normal"><?= h(t('rider.card.col_card', 'Card')) ?></dt><dd class="col-7"><code><?= h($cardRow['serial']) ?></code></dd>
                <dt class="col-5 text-muted fw-normal"><?= h(t('rider.card.col_status', 'Status')) ?></dt><dd class="col-7"><?= id_card_status_badge($cardRow['status']) ?></dd>
                <dt class="col-5 text-muted fw-normal"><?= h(t('rider.card.valid', 'Permit valid until')) ?></dt><dd class="col-7"><?= formatDate($cardRider['expiry_date'], 'd M Y') ?></dd>
            </dl>
            <?php if ($cardRow['status'] === 'issued'): ?><p class="small text-muted mt-2 mb-0"><?= h(t('rider.card.waiting', 'Your chairperson has not printed this card yet.')) ?></p><?php endif; ?>
            <?php if ($cardRow['valid_until'] && $cardRider['expiry_date'] && $cardRow['valid_until'] !== $cardRider['expiry_date']): ?><p class="small text-muted mt-2 mb-0"><?= h(t('rider.card.stale', 'Your permit was renewed. The QR check already shows the new date; ask your chairperson for a card with the new date printed.')) ?></p><?php endif; ?>
            <?php endif; ?>
            <?php if ($chair): ?><p class="small mt-3 mb-0"><?= h(t('rider.card.chair', 'Your stage chairperson: {name}', ['name' => $chair['name']])) ?><?= $chair['phone'] ? ', <a href="tel:' . h($chair['phone']) . '">' . h($chair['phone']) . '</a>' : '' ?></p><?php endif; ?>
        </div></div>

        <?php if ($cardRow): ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title"><?= h(t('rider.card.lost_title', 'Lost or stolen card')) ?></h2>
            <p class="small text-muted"><?= h(t('rider.card.lost_text', 'Report it here at once. The QR code on the missing card stops working, so nobody else can use it at a roadside check. Then ask your chairperson for a new card.')) ?></p>
            <form method="post" data-confirm="<?= h(t('rider.card.lost_confirm', 'Report card {serial} as lost? Its QR code stops working straight away, even if you find the card later.', ['serial' => $cardRow['serial']])) ?>" data-confirm-ok="<?= h(t('rider.card.lost_ok', 'Report it lost')) ?>">
                <?= csrf_field() ?><input type="hidden" name="card_action" value="lost">
                <button class="btn btn-outline-danger w-100"><i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i><?= h(t('rider.card.lost_button', 'My card is lost or stolen')) ?></button>
            </form>
        </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-body">
            <h2 class="section-title"><?= h(t('rider.card.history', 'My cards')) ?></h2>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th><?= h(t('rider.card.col_card', 'Card')) ?></th><th><?= h(t('rider.card.col_status', 'Status')) ?></th><th><?= h(t('rider.card.col_issued', 'Made')) ?></th><th><?= h(t('rider.card.col_collected', 'Collected')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($cardHistory as $past): ?>
                    <tr><td><code><?= h($past['serial']) ?></code><br><small class="text-muted"><?= h(ID_CARD_REASONS[$past['reason']] ?? '') ?></small></td>
                        <td><?= id_card_status_badge($past['status']) ?></td>
                        <td><?= formatDate($past['issued_at'], 'd M Y') ?></td>
                        <td><?= $past['collected_at'] ? formatDate($past['collected_at'], 'd M Y') : '–' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div>
    </div>

    <aside class="doc-preview" aria-label="<?= h(t('rider.card.preview', 'My card')) ?>">
        <div class="card"><div class="card-body">
            <?php if ($cardRow): ?>
                <?php require __DIR__ . '/../../partials/id-card.php'; ?>
                <p class="small text-muted text-center mt-3 mb-0 no-print"><?= h(t('rider.card.print_hint', 'A copy you print at home is for your records. The card officers accept is the one your chairperson prints and hands to you.')) ?></p>
            <?php else: ?>
                <?= empty_state('fa-id-card', t('rider.card.none', 'You have no valid card right now'), t('rider.card.none_text', 'Your last card was reported lost or cancelled, so its QR code no longer works. Your stage chairperson can print you a new card.')) ?>
            <?php endif; ?>
        </div></div>

    </aside>
</div>
<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/id-card.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/id-card.js') ?>"></script>
<?php portal_end();
