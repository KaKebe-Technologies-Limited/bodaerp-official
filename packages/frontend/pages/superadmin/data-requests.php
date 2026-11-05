<?php
// Super Admin → Rider Data Requests: corrections and deletion requests sent from the rider portal
// (Data Protection and Privacy Act, 2019). Each request is answered with a note the rider can read.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_role(['super_admin']);

$types = ['export' => 'Copy of my data', 'correction' => 'Correct my details', 'deletion' => 'Delete my data'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['completed', 'rejected', 'open'], true) ? $_POST['status'] : 'open';
    $response = trim((string) ($_POST['response'] ?? ''));
    if ($status !== 'open' && $response === '') flash('error', 'Write a short note for the rider explaining what was done.');
    else {
        runQuery("UPDATE data_requests SET status = ?, response = ?, handled_by = ?, handled_at = IF(? = 'open', NULL, NOW()) WHERE id = ?",
            [$status, $response ?: null, $_SESSION['user_id'], $status, $id]);
        $req = fetchOne("SELECT rider_id, type FROM data_requests WHERE id = ?", [$id]);
        if ($req && $req['rider_id'] && $status !== 'open') {
            runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, 'Data Request', ?)",
                [$req['rider_id'], 'Your request "' . ($types[$req['type']] ?? $req['type']) . '" was ' . $status . ': ' . mb_substr($response, 0, 400)]);
        }
        audit_log('UPDATE', 'data_request', (string) $id, "Marked $status");
        flash('success', 'Request updated and the rider was notified in their portal.');
    }
    redirect('/pages/superadmin/data-requests.php' . query_with([]));
}

$status = in_array($_GET['status'] ?? '', ['open', 'completed', 'rejected'], true) ? $_GET['status'] : 'open';
$pg = paginate((int) fetchValue("SELECT COUNT(*) FROM data_requests WHERE status = ?", [$status]), 25);
$rows = fetchAll("SELECT d.*, r.full_name, r.id_number, r.id AS rid, c.name AS city_name, s.name AS stage_name, h.name AS handler
    FROM data_requests d LEFT JOIN riders r ON r.id = d.rider_id LEFT JOIN cities c ON c.id = r.city_id LEFT JOIN stages s ON s.id = r.stage_id
    LEFT JOIN users h ON h.id = d.handled_by WHERE d.status = ? ORDER BY d.created_at " . ($status === 'open' ? 'ASC' : 'DESC') . " LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", [$status]);

portal_start('superadmin', 'data-requests', 'Rider Data Requests', [
    'subtitle' => 'Corrections and deletion requests sent from the rider portal',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Rider Data Requests' => null],
]);
?>
<ul class="nav nav-pills mb-3 small">
    <?php foreach (['open' => 'Open', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?>
</ul>
<?php if (!$rows): ?>
    <div class="card"><div class="card-body"><?= empty_state('fa-user-shield', 'No ' . $status . ' requests', 'Riders send these from My Account in their portal.') ?></div></div>
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
