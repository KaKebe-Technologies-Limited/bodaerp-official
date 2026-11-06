<?php
// Cash the chairperson has collected but not yet handed to the city office. A handover bundles those payments,
// records what was actually handed over (it can differ, with a note), and waits for the city to confirm it.
// Only cash recorded through Collect payments since handovers began counts; older cash is outside this process.
require_once __DIR__ . '/../../../backend/admin_ui.php';
$me = require_role(['chairperson']);
$stageId = (int) $_SESSION['stage_id'];
$cityId = (string) $_SESSION['city_id'];

$openCash = fn() => fetchAll("SELECT p.id, p.amount, p.receipt_number, p.paid_at, r.full_name
    FROM payments p JOIN riders r ON r.id = p.rider_id
    WHERE r.stage_id = ? AND p.payment_method = 'Cash' AND p.status = 'Confirmed' AND p.channel = 'stage' AND p.handover_id IS NULL
    ORDER BY p.paid_at", [$stageId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $payments = $openCash();
    $expected = round(array_sum(array_column($payments, 'amount')), 2);
    $handed = round((float) str_replace([',', ' '], '', (string) ($_POST['handed_amount'] ?? '')), 2);
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 500);
    $seen = (int) ($_POST['payments_count'] ?? -1);

    if (!$payments) flash('error', 'There is no cash waiting to be handed over.');
    elseif ($seen !== count($payments)) flash('error', 'New cash was recorded while this page was open. Check the list and submit again.');
    elseif ($handed <= 0) flash('error', 'Enter the amount of cash you are handing over.');
    elseif (abs($handed - $expected) >= 1 && mb_strlen($note) < 5) flash('error', 'The amount differs from the receipts. Explain the difference in the note.');
    else {
        $db = db();
        $db->begin_transaction();
        try {
            $reference = 'HO-' . $cityId . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            runQuery("INSERT INTO cash_handovers (reference, city_id, stage_id, submitted_by, payments_count, expected_amount, handed_amount, note)
                      VALUES (?,?,?,?,?,?,?,?)", [$reference, $cityId, $stageId, (int) $me['user_id'], count($payments), $expected, $handed, $note ?: null])->close();
            $handoverId = (int) $db->insert_id;
            $ids = array_map('intval', array_column($payments, 'id'));
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $linked = runQuery("UPDATE payments SET handover_id = ? WHERE handover_id IS NULL AND id IN ($marks)", array_merge([$handoverId], $ids));
            if ($linked->affected_rows !== count($ids)) throw new RuntimeException('Some payments were already handed over.');
            $linked->close();
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            app_log('handover', 'submit failed: ' . $e->getMessage(), ['stage' => $stageId]);
            flash('error', 'The handover could not be saved because the list changed. Check it and submit again.');
            redirect('/pages/chairperson/cash-handover.php');
        }
        audit_log('CREATE', 'cash_handover', (string) $handoverId, "Handover $reference: " . formatCurrency($handed) . ' handed, ' . formatCurrency($expected) . ' on receipts');
        flash('success', "Handover $reference recorded. Give the cash to the city office; they will confirm it here.");
    }
    redirect('/pages/chairperson/cash-handover.php');
}

$payments = $openCash();
$expected = array_sum(array_column($payments, 'amount'));
$history = fetchAll("SELECT * FROM cash_handovers WHERE stage_id = ? ORDER BY created_at DESC LIMIT 20", [$stageId]);
$statusBadge = ['submitted' => ['Waiting for the city', 'warning text-dark'], 'confirmed' => ['Confirmed', 'success'], 'queried' => ['Queried', 'danger']];

portal_start('chairperson', 'cash-handover', 'Cash handover', [
    'subtitle' => 'Hand the cash you collected to the city office and get it confirmed',
]);
?>
<div class="layout-thirds">
    <section class="card">
        <div class="card-body">
            <h2 class="section-title">Cash waiting to be handed over</h2>
            <?php if (!$payments): ?>
                <?= empty_state('fa-hand-holding-dollar', 'Nothing to hand over', 'Cash you record in Collect payments appears here until you hand it to the city office.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Receipt</th><th scope="col">Rider</th><th scope="col">Date</th><th scope="col" class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr><td><?= h($p['receipt_number']) ?></td><td><?= h($p['full_name']) ?></td><td><?= h(formatDate($p['paid_at'])) ?></td><td class="text-end"><?= h(formatCurrency($p['amount'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><th colspan="3">Total on receipts (<?= count($payments) ?>)</th><th class="text-end"><?= h(formatCurrency($expected)) ?></th></tr></tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="card">
        <div class="card-body">
            <h2 class="section-title">Record a handover</h2>
            <?php if ($payments): ?>
                <form method="post" data-confirm="Record that you are handing <?= h(formatCurrency($expected)) ?> in cash from <?= count($payments) ?> receipts to the city office? The city office then confirms the amount it received." data-confirm-ok="Record handover">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payments_count" value="<?= count($payments) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="handed">Cash you are handing over (UGX)</label>
                        <input class="form-control" id="handed" name="handed_amount" inputmode="numeric" value="<?= h((string) round($expected)) ?>" required>
                        <div class="form-text">The receipts add up to <?= h(formatCurrency($expected)) ?>.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="hnote">Note for the city office</label>
                        <textarea class="form-control" id="hnote" name="note" rows="3" maxlength="500" aria-describedby="hnoteHint"></textarea>
                        <div class="form-text" id="hnoteHint">Needed only when the amount is different from the receipts.</div>
                    </div>
                    <button class="btn btn-primary w-100" data-busy-label="Saving">Record handover</button>
                </form>
            <?php else: ?>
                <p class="text-muted mb-0">When you have collected cash, record the handover here before you take it to the city office.</p>
            <?php endif; ?>
        </div>
    </aside>
</div>

<section class="card mt-4">
    <div class="card-body">
        <h2 class="section-title">Your handovers</h2>
        <?php if (!$history): ?>
            <p class="text-muted mb-0">No handovers yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th scope="col">Reference</th><th scope="col">Date</th><th scope="col" class="text-end">On receipts</th><th scope="col" class="text-end">Handed over</th><th scope="col" class="text-end">City received</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $ho): [$label, $tone] = $statusBadge[$ho['status']]; ?>
                        <tr>
                            <td><?= h($ho['reference']) ?></td>
                            <td><?= h(formatDate($ho['created_at'])) ?></td>
                            <td class="text-end"><?= h(formatCurrency($ho['expected_amount'])) ?></td>
                            <td class="text-end"><?= h(formatCurrency($ho['handed_amount'])) ?></td>
                            <td class="text-end"><?= $ho['received_amount'] !== null ? h(formatCurrency($ho['received_amount'])) : '<span class="text-muted">Not yet</span>' ?></td>
                            <td><span class="badge bg-<?= $tone ?>"><?= h($label) ?></span><?php if ($ho['review_note']): ?><div class="small text-muted mt-1"><?= h($ho['review_note']) ?></div><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php portal_end(); ?>
