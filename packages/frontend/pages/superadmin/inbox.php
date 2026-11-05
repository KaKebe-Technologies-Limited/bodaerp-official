<?php
// Super Admin → Contact Inbox: messages from the public contact form. Read, reply by email
// (CKEditor, sent with the contact_reply template), archive, mark as spam, export.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_role(['super_admin']);

$statuses = ['new' => ['New', 'bg-primary'], 'read' => ['Read', 'bg-secondary'], 'replied' => ['Replied', 'bg-success'], 'archived' => ['Archived', 'bg-light text-dark border'], 'spam' => ['Spam', 'bg-danger']];
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'reply') {
        $msg = fetchOne("SELECT * FROM contact_messages WHERE id = ?", [(int) $_POST['id']]);
        $reply = safe_html((string) ($_POST['reply'] ?? ''));
        if (!$msg || trim(strip_tags($reply)) === '') { flash('error', 'Write a reply first.'); }
        else {
            $sent = send_template_email('contact_reply', $msg['email'], ['name' => $msg['name'], 'subject' => $msg['subject'], 'message' => $msg['message'], 'reply_html' => $reply]);
            if ($sent) {
                runQuery("UPDATE contact_messages SET status = 'replied', reply_body = ?, replied_by = ?, replied_at = NOW() WHERE id = ?", [$reply, $_SESSION['user_id'], $msg['id']]);
                audit_log('REPLY', 'contact_message', (string) $msg['id'], 'Replied to ' . $msg['email']);
                flash('success', 'Reply sent to ' . $msg['email'] . '.');
            } else flash('error', 'The reply could not be sent. Check Email Templates → Sent log for the reason.');
        }
        redirect('/pages/superadmin/inbox.php?id=' . (int) $_POST['id']);
    }
    $ids = $action === 'bulk' ? posted_ids() : [(int) ($_POST['id'] ?? 0)];
    $op = $action === 'bulk' ? (string) ($_POST['bulk'] ?? '') : $action;
    $ids = array_filter($ids);
    if ($ids && in_array($op, ['read', 'new', 'archived', 'spam', 'delete'], true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'delete') runQuery("DELETE FROM contact_messages WHERE id IN ($in)", $ids);
        else runQuery("UPDATE contact_messages SET status = ? WHERE id IN ($in)", [$op, ...$ids]);
        audit_log(strtoupper($op), 'contact_message', implode(',', $ids), count($ids) . ' message(s)');
        flash('success', count($ids) . ' message' . (count($ids) === 1 ? '' : 's') . ($op === 'delete' ? ' deleted.' : ' updated.'));
    }
    redirect('/pages/superadmin/inbox.php' . ($action !== 'bulk' && $op !== 'delete' && $id ? '?id=' . $id : ''));
}

if ($id) {
    $msg = fetchOne("SELECT m.*, u.name AS replier FROM contact_messages m LEFT JOIN users u ON u.id = m.replied_by WHERE m.id = ?", [$id]);
    if (!$msg) app_error_page(404);
    if ($msg['status'] === 'new') { runQuery("UPDATE contact_messages SET status = 'read' WHERE id = ?", [$id]); $msg['status'] = 'read'; }
}

$status = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = $status ? 'WHERE status = ?' : "WHERE status NOT IN ('archived','spam')";
$params = $status ? [$status] : [];
if ($q !== '') { $where .= ' AND (name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ? OR organisation LIKE ?)'; array_push($params, ...array_fill(0, 5, "%$q%")); }

if (isset($_GET['export'])) {
    $rows = fetchAll("SELECT created_at, name, email, phone, organisation, subject, message, status FROM contact_messages $where ORDER BY id DESC", $params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="contact-messages-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Received', 'Name', 'Email', 'Phone', 'Organisation', 'Subject', 'Message', 'Status']);
    foreach ($rows as $r) fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $r));
    audit_log('EXPORT', 'contact_message', null, count($rows) . ' messages exported');
    exit;
}

$counts = array_column(fetchAll("SELECT status, COUNT(*) n FROM contact_messages GROUP BY status"), 'n', 'status');
$pg = paginate((int) fetchValue("SELECT COUNT(*) FROM contact_messages $where", $params), 25);
$rows = fetchAll("SELECT id, name, email, organisation, subject, status, created_at FROM contact_messages $where ORDER BY "
    . sort_sql(['date' => 'created_at', 'name' => 'name', 'subject' => 'subject'], 'date') . " LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);

portal_start('superadmin', 'inbox', 'Contact inbox', [
    'subtitle' => 'Messages sent through the public contact form',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Contact Inbox' => $id ? '/pages/superadmin/inbox.php' : null] + ($id ? [$msg['subject'] => null] : []),
    'actions' => $id ? '' : '<a class="btn btn-outline-secondary btn-sm" href="' . h(query_with(['export' => 1])) . '"><i class="fas fa-file-csv me-1"></i>Export CSV</a>',
]);
?>
<?php if ($id): ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                <div><h2 class="h5 fw-bold mb-1"><?= h($msg['subject']) ?></h2><div class="small text-muted">From <strong><?= h($msg['name']) ?></strong> &lt;<a href="mailto:<?= h($msg['email']) ?>"><?= h($msg['email']) ?></a>&gt; · <?= h(formatDate($msg['created_at'], 'j M Y, H:i')) ?></div></div>
                <span class="badge align-self-start <?= $statuses[$msg['status']][1] ?>"><?= h($statuses[$msg['status']][0]) ?></span>
            </div>
            <div class="message-body"><?= h($msg['message']) ?></div>
            <?php if ($msg['reply_body']): ?>
                <h3 class="h6 fw-bold mt-4">Reply sent <?= h(formatDate($msg['replied_at'], 'j M Y, H:i')) ?><?= $msg['replier'] ? ' by ' . h($msg['replier']) : '' ?></h3>
                <div class="border rounded p-3"><?= safe_html($msg['reply_body']) ?></div>
            <?php endif; ?>
        </div></div>
        <form method="post" class="card mt-3"><div class="card-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= (int) $msg['id'] ?>">
            <label class="form-label fw-semibold" for="reply">Reply by email to <?= h($msg['email']) ?></label>
            <textarea id="reply" name="reply" data-rich-text="email" required aria-label="Reply"></textarea>
            <button class="btn btn-primary mt-3" data-busy-label="Sending"><i class="fas fa-paper-plane me-1"></i>Send reply</button>
        </div></form>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h2 class="h6 fw-bold">Details</h2>
            <dl class="small mb-3">
                <dt>Phone</dt><dd><?= $msg['phone'] ? '<a href="tel:' . h(preg_replace('/[^\d+]/', '', $msg['phone'])) . '">' . h($msg['phone']) . '</a>' : 'Not given' ?></dd>
                <dt>Organisation</dt><dd><?= h($msg['organisation'] ?: 'Not given') ?></dd>
                <dt>Sent from</dt><dd><?= h($msg['ip_address']) ?></dd>
            </dl>
            <div class="d-grid gap-2">
                <?php foreach (['new' => 'Mark as unread', 'archived' => 'Archive', 'spam' => 'Mark as spam'] as $op => $label): if ($msg['status'] === $op) continue; ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $op ?>"><input type="hidden" name="id" value="<?= (int) $msg['id'] ?>"><button class="btn btn-outline-secondary w-100"><?= h($label) ?></button></form>
                <?php endforeach; ?>
                <form method="post" data-confirm="Delete this message permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $msg['id'] ?>"><button class="btn btn-outline-danger w-100">Delete</button></form>
            </div>
        </div></div>
    </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body">
    <ul class="nav nav-pills mb-3 small">
        <li class="nav-item"><a class="nav-link<?= $status === '' ? ' active' : '' ?>" href="?">Inbox</a></li>
        <?php foreach ($statuses as $k => [$label]): ?><li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= h($label) ?> <span class="badge bg-light text-dark"><?= (int) ($counts[$k] ?? 0) ?></span></a></li><?php endforeach; ?>
    </ul>
    <form class="row g-2 mb-3" method="get"><?php if ($status): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
        <div class="col-md-6"><input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search name, email, subject or message" aria-label="Search"></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Search</button></div>
    </form>
    <?php if (!$rows): echo empty_state('fa-inbox', $q !== '' ? 'No message matches that search' : 'No messages here', 'New messages from the contact page appear here and are emailed to the contact inbox address.'); else: ?>
    <form method="post" data-confirm="Apply this action to the selected messages?">
        <?= csrf_field() ?><input type="hidden" name="action" value="bulk">
        <div class="d-flex gap-2 mb-2"><select name="bulk" class="form-select form-select-sm w-auto" aria-label="Bulk action"><option value="read">Mark as read</option><option value="new">Mark as unread</option><option value="archived">Archive</option><option value="spam">Mark as spam</option><option value="delete">Delete</option></select><button class="btn btn-sm btn-outline-secondary">Apply to selected</button></div>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th><input class="form-check-input" type="checkbox" data-check-all="ids[]" aria-label="Select all"></th><th><?= sort_link('name', 'From', 'date') ?></th><th><?= sort_link('subject', 'Subject', 'date') ?></th><th>Status</th><th><?= sort_link('date', 'Received', 'date') ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="inbox-row<?= $r['status'] === 'new' ? ' unread' : '' ?>">
                    <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Select message from <?= h($r['name']) ?>"></td>
                    <td><?= h($r['name']) ?><div class="small text-muted"><?= h($r['organisation'] ?: $r['email']) ?></div></td>
                    <td><a href="?id=<?= (int) $r['id'] ?>"><?= h($r['subject']) ?></a></td>
                    <td><span class="badge <?= $statuses[$r['status']][1] ?>"><?= h($statuses[$r['status']][0]) ?></span></td>
                    <td class="small text-nowrap"><?= h(timeAgo($r['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </form>
    <?= render_pagination($pg) ?>
    <?php endif; ?>
</div></div>
<?php endif; ?>
<?php portal_end((bool) $id); ?>
