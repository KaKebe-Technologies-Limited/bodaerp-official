<?php
// Super Admin → Rider Data Requests: corrections and deletion requests sent from the rider portal
// (Data Protection and Privacy Act, 2019). Each request is answered with a note the rider can read.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/rider_records.php';
require_role(['super_admin']);

$types = ['export' => 'Copy of my data', 'correction' => 'Correct my details', 'objection' => 'Object to how my data is used', 'deletion' => 'Delete my data'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['op'] ?? '') === 'anonymise') {
        $req = fetchOne("SELECT d.rider_id, r.id_number, COALESCE(u.email, r.email) AS email, r.full_name FROM data_requests d JOIN riders r ON r.id = d.rider_id
            LEFT JOIN users u ON u.id = r.user_id WHERE d.id = ? AND d.type = 'deletion' AND d.status = 'open'", [$id]);
        $response = trim((string) ($_POST['response'] ?? ''));
        if (!$req || $response === '') { flash('error', 'Write the note for the rider first: it is emailed before the details are removed.'); redirect('/pages/superadmin/data-requests.php'); }
        // The answer goes out first, while there is still an address to send it to.
        if (!empty($req['email'])) send_email($req['email'], 'Your data deletion request', '<p>Hello ' . h($req['full_name']) . ',</p><p>Your personal details were removed from the permit register. ' . h($response) . '</p>', $response);
        rider_anonymise((int) $req['rider_id'], (int) $_SESSION['user_id'], $response);
        runQuery("UPDATE data_requests SET status = 'completed', response = ?, handled_by = ?, handled_at = NOW() WHERE id = ?", [$response, $_SESSION['user_id'], $id]);
        flash('success', 'Personal details of ' . $req['id_number'] . ' were removed. Payments and the permit number are kept for the city\'s accounts.');
        redirect('/pages/superadmin/data-requests.php');
    }
    $status = in_array($_POST['status'] ?? '', ['completed', 'rejected', 'open'], true) ? $_POST['status'] : 'open';
    $response = trim((string) ($_POST['response'] ?? ''));
    if ($status !== 'open' && $response === '') flash('error', 'Write a short note for the rider explaining what was done.');
    else {
        runQuery("UPDATE data_requests SET status = ?, response = ?, handled_by = ?, handled_at = IF(? = 'open', NULL, NOW()) WHERE id = ?",
            [$status, $response ?: null, $_SESSION['user_id'], $status, $id]);
        $req = fetchOne("SELECT rider_id, type FROM data_requests WHERE id = ?", [$id]);
        if ($req && $req['rider_id'] && $status !== 'open') {
            // Always reaches the rider: in the portal when they read it there, otherwise by email and SMS.
            $answer = 'Your request "' . ($types[$req['type']] ?? $req['type']) . '" was ' . $status . ($response !== '' ? ': ' . $response : '.');
            if (!rider_notify((int) $req['rider_id'], 'Data Request', $answer)) {
                $contact = fetchOne("SELECT COALESCE(u.email, r.email) AS email, r.full_name FROM riders r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$req['rider_id']]);
                if (!empty($contact['email'])) send_email($contact['email'], 'Your data request', '<p>Hello ' . h($contact['full_name']) . ',</p><p>' . h($answer) . '</p>', $answer);
                if ($target = rider_sms_target((int) $req['rider_id'])) send_sms($target['phone'], mb_strimwidth($answer, 0, 300, '…'));
            }
        }
        audit_log('UPDATE', 'data_request', (string) $id, "Marked $status");
        flash('success', 'Request updated and the rider was told.');
    }
    redirect('/pages/superadmin/data-requests.php' . query_with([]));
}

$status = in_array($_GET['status'] ?? '', ['open', 'completed', 'rejected'], true) ? $_GET['status'] : 'open';
$pg = paginate((int) fetchValue("SELECT COUNT(*) FROM data_requests WHERE status = ?", [$status]), 25);
$rows = fetchAll("SELECT d.*, r.full_name, r.id_number, r.id AS rid, c.name AS city_name, s.name AS stage_name, h.name AS handler
    FROM data_requests d LEFT JOIN riders r ON r.id = d.rider_id LEFT JOIN cities c ON c.id = r.city_id LEFT JOIN stages s ON s.id = r.stage_id
    LEFT JOIN users h ON h.id = d.handled_by WHERE d.status = ? ORDER BY d.created_at " . ($status === 'open' ? 'ASC' : 'DESC') . " LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", [$status]);

portal_start('superadmin', 'data-requests', 'Rider data requests', [
    'subtitle' => 'Corrections and deletion requests sent from the rider portal',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Rider Data Requests' => null],
]);
?>
<ul class="nav nav-pills mb-3 small">
    <?php foreach (['open' => 'Open', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?>
</ul>
<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><?= empty_state('fa-user-shield', 'No ' . $status . ' requests', 'Riders send these from My account in their portal.') ?></div></div>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <span class="badge <?= $r['type'] === 'deletion' ? 'bg-danger' : 'bg-primary' ?> mb-1"><?= h($types[$r['type']] ?? $r['type']) ?></span>
            <h2 class="h6 fw-bold mb-0"><?= h($r['full_name'] ?? 'Rider record removed') ?> <span class="text-muted fw-normal"><?= h($r['id_number'] ?? '') ?></span></h2>
            <div class="small text-muted"><?= h(($r['stage_name'] ?? '') . ($r['city_name'] ? ', ' . $r['city_name'] : '')) ?> · sent <?= h(formatDate($r['created_at'], 'j M Y, H:i')) ?></div>
        </div>
    </div>
    <?php if ($r['details']): ?><div class="message-body mt-3"><?= h($r['details']) ?></div><?php endif; ?>
    <?php if ($r['type'] === 'deletion'): ?><p class="small text-muted mt-2 mb-0">Permit and payment records may have to be kept for the city council's revenue records. Remove what is not legally required and explain what was kept.</p><?php endif; ?>
    <?php if ($r['rid'] && $status === 'open' && $r['type'] !== 'deletion'): ?><a class="btn btn-outline-primary btn-sm mt-3" href="<?= BASE_URL ?>/pages/superadmin/edit-rider.php?id=<?= (int) $r['rid'] ?>"><i class="fas fa-pen me-1" aria-hidden="true"></i>Open and correct the rider record</a><?php endif; ?>
    <?php if ($r['rid'] && $status === 'open' && $r['type'] === 'deletion'): ?>
    <form method="post" class="mt-3 border-top pt-3" data-confirm="Remove the personal details of <?= h($r['full_name'] ?? '') ?> (<?= h($r['id_number'] ?? '') ?>)? Name, NIN, phone, email, address, next of kin and photo are erased, the portal account is closed and the ID card stops working. Payments and the permit number stay. This cannot be undone." data-confirm-ok="Remove the details" data-confirm-typed="REMOVE">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="op" value="anonymise">
        <label class="form-label small fw-semibold" for="anon<?= (int) $r['id'] ?>">Note to the rider (emailed before the details are removed)</label>
        <textarea class="form-control mb-2" id="anon<?= (int) $r['id'] ?>" name="response" rows="2" required>Payments and your permit number are kept because the city council must keep revenue records.</textarea>
        <button class="btn btn-danger btn-sm"><i class="fas fa-user-slash me-1" aria-hidden="true"></i>Remove personal details and complete</button>
    </form>
    <?php endif; ?>
    <?php if ($status === 'open'): ?>
    <form method="post" class="mt-3">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <label class="form-label small fw-semibold" for="resp<?= (int) $r['id'] ?>">Note to the rider</label>
        <textarea class="form-control mb-2" id="resp<?= (int) $r['id'] ?>" name="response" rows="2" required></textarea>
        <div class="d-flex gap-2"><button class="btn btn-success btn-sm" name="status" value="completed">Mark completed</button><button class="btn btn-outline-danger btn-sm" name="status" value="rejected">Reject</button></div>
    </form>
    <?php else: ?>
    <p class="small mt-3 mb-0"><strong><?= h(ucfirst($r['status'])) ?></strong> <?= h(formatDate($r['handled_at'], 'j M Y')) ?><?= $r['handler'] ? ' by ' . h($r['handler']) : '' ?>: <?= h($r['response']) ?></p>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?= render_pagination($pg) ?>
<?php portal_end(); ?>
