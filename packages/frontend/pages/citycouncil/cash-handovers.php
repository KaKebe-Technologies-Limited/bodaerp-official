<?php
// Cash handovers from this city's chairpersons. The city counts the cash, enters what it received and confirms,
// or queries the handover with a reason. Any gap between receipts, what was handed over and what was received
// stays visible here and in Data checks.
require_once __DIR__ . '/../../../backend/admin_ui.php';
$me = require_role(['city_admin']);
$cityId = (string) $_SESSION['city_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['handover_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = mb_substr(trim((string) ($_POST['review_note'] ?? '')), 0, 500);
    $received = round((float) str_replace([',', ' '], '', (string) ($_POST['received_amount'] ?? '')), 2);
    $ho = fetchOne("SELECT h.*, u.phone AS chair_phone, u.name AS chair_name FROM cash_handovers h JOIN users u ON u.id = h.submitted_by
        WHERE h.id = ? AND h.city_id = ? AND h.status = 'submitted'", [$id, $cityId]);

    if (!$ho) flash('error', 'That handover was already reviewed or is not in your city.');
    elseif ($decision === 'confirm' && $received <= 0) flash('error', 'Enter the amount of cash you counted.');
    elseif ($decision === 'confirm' && abs($received - (float) $ho['handed_amount']) >= 1 && mb_strlen($note) < 5) flash('error', 'What you counted differs from what was handed over. Explain it in the note, or query the handover instead.');
    elseif ($decision === 'query' && mb_strlen($note) < 5) flash('error', 'Say what is wrong, so the chairperson can answer.');
    elseif (in_array($decision, ['confirm', 'query'], true)) {
        $status = $decision === 'confirm' ? 'confirmed' : 'queried';
        $done = runQuery("UPDATE cash_handovers SET status = ?, received_amount = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                          WHERE id = ? AND status = 'submitted'", [$status, $decision === 'confirm' ? $received : null, (int) $me['user_id'], $note ?: null, $id]);
        if ($done->affected_rows === 1) {
            if ($decision === 'query') runQuery("UPDATE payments SET handover_id = NULL WHERE handover_id = ?", [$id])->close();
            audit_log('UPDATE', 'cash_handover', (string) $id, "Handover {$ho['reference']} $status" . ($decision === 'confirm' ? ': ' . formatCurrency($received) . ' received' : ": $note"));
            if ($decision === 'confirm') send_template_sms('handover_confirmed', $ho['chair_phone'], ['amount' => formatCurrency($received), 'count' => $ho['payments_count'], 'reference' => $ho['reference']]);
            else send_template_sms('handover_disputed', $ho['chair_phone'], ['reference' => $ho['reference'], 'note' => mb_strimwidth($note, 0, 80, '…')]);
            flash('success', $decision === 'confirm' ? "Handover {$ho['reference']} confirmed." : "Handover {$ho['reference']} queried. Its payments go back to the chairperson's list to hand over again.");
        }
        $done->close();
    }
    redirect('/pages/citycouncil/cash-handovers.php');
}

$filter = in_array($_GET['status'] ?? '', ['submitted', 'confirmed', 'queried'], true) ? $_GET['status'] : 'submitted';
$total = (int) fetchValue("SELECT COUNT(*) FROM cash_handovers WHERE city_id = ? AND status = ?", [$cityId, $filter]);
$page = paginate($total, 25);
$rows = fetchAll("SELECT h.*, s.name AS stage_name, u.name AS chair_name FROM cash_handovers h
    JOIN stages s ON s.id = h.stage_id JOIN users u ON u.id = h.submitted_by
    WHERE h.city_id = ? AND h.status = ? ORDER BY h.created_at DESC LIMIT " . (int) $page['per_page'] . " OFFSET " . (int) $page['offset'], [$cityId, $filter]);
$counts = array_column(fetchAll("SELECT status, COUNT(*) AS c FROM cash_handovers WHERE city_id = ? GROUP BY status", [$cityId]), 'c', 'status');
$notHanded = fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(p.amount), 0) AS total, MIN(p.paid_at) AS oldest FROM payments p
    WHERE p.city_id = ? AND p.payment_method = 'Cash' AND p.status = 'Confirmed' AND p.channel = 'stage' AND p.handover_id IS NULL", [$cityId]);

portal_start('citycouncil', 'cash-handovers', 'Cash handovers', [
    'subtitle' => 'Confirm the cash chairpersons bring to the city office',
]);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-hero-card tone-gold"><div><div class="stat-hero-value"><?= (int) ($counts['submitted'] ?? 0) ?></div><div class="stat-hero-label">Waiting for you</div></div></div></div>
    <div class="col-md-4"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= h(formatCurrency($notHanded['total'])) ?></div><div class="stat-hero-label">Cash still at stages (<?= (int) $notHanded['c'] ?> receipts)</div></div></div></div>
    <div class="col-md-4"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= $notHanded['oldest'] ? h((string) max(0, -daysUntil($notHanded['oldest']))) . ' days' : 'None' ?></div><div class="stat-hero-label">Oldest cash not handed over</div></div></div></div>
</div>

<nav class="nav nav-pills mb-3" aria-label="Handover status">
    <?php foreach (['submitted' => 'Waiting', 'confirmed' => 'Confirmed', 'queried' => 'Queried'] as $key => $label): ?>
        <a class="nav-link<?= $filter === $key ? ' active' : '' ?>" href="?status=<?= $key ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?> <span class="badge bg-light text-dark ms-1"><?= (int) ($counts[$key] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>

<section class="card">
    <div class="card-body">
    <?php if (!$rows): ?>
        <?= empty_state('fa-hand-holding-dollar', $filter === 'submitted' ? 'No handovers waiting' : 'Nothing here yet', $filter === 'submitted' ? 'When a chairperson records a cash handover it appears here for you to count and confirm.' : '') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Reference</th><th scope="col">Stage</th><th scope="col">Date</th><th scope="col" class="text-end">On receipts</th><th scope="col" class="text-end">Handed over</th><th scope="col"><?= $filter === 'submitted' ? 'Your count' : 'Result' ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $ho): $gap = (float) $ho['handed_amount'] - (float) $ho['expected_amount']; ?>
                    <tr>
                        <td><strong><?= h($ho['reference']) ?></strong><div class="small text-muted"><?= (int) $ho['payments_count'] ?> receipts</div></td>
                        <td><?= h($ho['stage_name']) ?><div class="small text-muted"><?= h($ho['chair_name']) ?></div></td>
                        <td><?= h(formatDate($ho['created_at'])) ?></td>
                        <td class="text-end"><?= h(formatCurrency($ho['expected_amount'])) ?></td>
                        <td class="text-end"><?= h(formatCurrency($ho['handed_amount'])) ?>
                            <?php if (abs($gap) >= 1): ?><div class="small text-danger fw-semibold"><?= $gap < 0 ? 'Short by ' : 'Over by ' ?><?= h(formatCurrency(abs($gap))) ?></div><?php endif; ?>
                            <?php if ($ho['note']): ?><div class="small text-muted"><?= h($ho['note']) ?></div><?php endif; ?>
                        </td>
                        <td>
                        <?php if ($filter === 'submitted'): ?>
                            <form method="post" class="handover-review">
                                <?= csrf_field() ?><input type="hidden" name="handover_id" value="<?= (int) $ho['id'] ?>">
                                <label class="visually-hidden" for="rcv-<?= (int) $ho['id'] ?>">Cash counted for <?= h($ho['reference']) ?></label>
                                <input class="form-control form-control-sm mb-2" id="rcv-<?= (int) $ho['id'] ?>" name="received_amount" inputmode="numeric" value="<?= h((string) round((float) $ho['handed_amount'])) ?>">
                                <label class="visually-hidden" for="rn-<?= (int) $ho['id'] ?>">Note for <?= h($ho['reference']) ?></label>
                                <input class="form-control form-control-sm mb-2" id="rn-<?= (int) $ho['id'] ?>" name="review_note" maxlength="500" placeholder="Note (needed for a query or a difference)">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" name="decision" value="confirm" data-busy-label="Saving">Confirm</button>
                                    <button class="btn btn-outline-danger btn-sm" name="decision" value="query">Query</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <?php if ($ho['received_amount'] !== null): ?><?= h(formatCurrency($ho['received_amount'])) ?> received<?php endif; ?>
                            <?php if ($ho['review_note']): ?><div class="small text-muted"><?= h($ho['review_note']) ?></div><?php endif; ?>
                            <div class="small text-muted"><?= h(formatDate($ho['reviewed_at'])) ?></div>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_pagination($page) ?>
    <?php endif; ?>
    </div>
</section>
<?php portal_end(); ?>
