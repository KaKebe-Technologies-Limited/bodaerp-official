<?php
// Super Admin → Email Templates: edit every system email (CKEditor), preview it inside the branded
// layout, send a test, and see the sent log (including emails written to storage/mail when there is no SMTP).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_role(['super_admin']);

$tab = ($_GET['tab'] ?? '') === 'log' ? 'log' : 'templates';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$tpl = $id ? fetchOne("SELECT * FROM email_templates WHERE id = ?", [$id]) : null;

/** Sample values so previews and test emails show realistic content. */
$sample = function (?string $vars): array {
    $known = ['name' => 'Akello James', 'email' => 'akello@bodaerp.site', 'reset_url' => app_url('reset-password.php?token=sample'),
        'set_password_url' => app_url('reset-password.php?welcome=1&token=sample'), 'verify_url' => app_url('verify-email.php?token=sample'),
        'portal_url' => app_url('pages/rider/dashboard.php'), 'inbox_url' => app_url('pages/superadmin/inbox.php'), 'admin_url' => app_url('pages/superadmin/data-requests.php'),
        'code' => '482913', 'ip_address' => client_ip(), 'created_by' => $_SESSION['name'] ?? 'Administrator', 'role' => 'Rider',
        'receipt_number' => 'RCP-000123', 'amount' => 'UGX 50,000', 'method' => 'Mobile Money', 'fiscal_year' => current_fiscal_year(),
        'expiry_date' => formatDate(date('Y-m-d', strtotime('+1 year'))), 'days_left' => '14', 'id_number' => 'BODA-LIR-000123',
        'status' => 'active', 'reason' => 'cancelled on the phone', 'changed_at' => date('j M Y, H:i'), 'subject' => 'Bringing BodaERP to Soroti',
        'message' => "Hello, our council would like to register about 900 riders across 12 stages.\nWho should we talk to?", 'phone' => '+256 772 123456',
        'organisation' => 'Soroti City Council', 'type' => 'correction', 'details' => 'My bike plate is UFA 123K, not UFA 128K.',
        'reply_html' => '<p>Thank you for your message. Our team will call you this week.</p>'];
    $out = [];
    foreach (array_filter(array_map('trim', explode(',', (string) $vars))) as $v) $out[$v] = $known[$v] ?? '[' . $v . ']';
    return $out + $known;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tpl) {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'save') {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = (string) ($_POST['body_html'] ?? '');
        $errors = [];
        if ($subject === '' || mb_strlen($subject) > 200) $errors[] = 'Enter a subject of up to 200 characters.';
        if (trim(strip_tags($body)) === '' ) $errors[] = 'The email body cannot be empty.';
        if ($tpl['code'] === '_layout' && !str_contains($body, '{{content}}')) $errors[] = 'The layout must keep {{content}}, where each email\'s message is placed.';
        if ($errors) { flash('error', implode(' ', $errors)); }
        else {
            runQuery("UPDATE email_templates SET subject = ?, body_html = ?, is_active = ?, updated_by = ? WHERE id = ?",
                [$subject, safe_html($body), $tpl['code'] === '_layout' || !empty($_POST['is_active']) ? 1 : 0, $_SESSION['user_id'], $id]);
            audit_log('UPDATE', 'email_template', $tpl['code'], 'Email template edited');
            flash('success', 'Template saved.');
        }
    } elseif ($action === 'test') {
        $to = trim((string) ($_POST['test_to'] ?? $_SESSION['email']));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) flash('error', 'Enter a valid email address for the test.');
        elseif ($tpl['code'] === '_layout') flash('error', 'Send a test of any other template to see the layout.');
        else {
            $ok = send_template_email($tpl['code'], $to, $sample($tpl['variables']));
            flash($ok ? 'success' : 'error', $ok ? (mail_transport_name() === 'smtp' ? "Test email sent to $to." : "No mail server is set up, so the test was saved to storage/mail and the Sent log instead of being sent.") : 'The test email could not be sent. See the Sent log for the error.');
        }
    }
    redirect('/pages/superadmin/email-templates.php?id=' . $id);
}

if ($tab === 'log') {
    $status = in_array($_GET['status'] ?? '', ['sent', 'logged', 'failed'], true) ? $_GET['status'] : '';
    $q = trim((string) ($_GET['q'] ?? ''));
    $where = 'WHERE 1=1'; $params = [];
    if ($status) { $where .= ' AND status = ?'; $params[] = $status; }
    if ($q !== '') { $where .= ' AND (to_email LIKE ? OR subject LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $pg = paginate((int) fetchValue("SELECT COUNT(*) FROM email_log $where", $params), 50);
    $log = fetchAll("SELECT * FROM email_log $where ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
}
$templates = fetchAll("SELECT id, code, name, description, subject, is_active, updated_at FROM email_templates ORDER BY code = '_layout', name");

portal_start('superadmin', 'email-templates', 'Email templates', [
    'subtitle' => 'Every email BodaERP sends, and a log of what was sent',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Email Templates' => $tpl ? '/pages/superadmin/email-templates.php' : null] + ($tpl ? [$tpl['name'] => null] : []),
]);
?>
<?php if (mail_transport_name() !== 'smtp'): ?>
    <div class="alert alert-warning" data-keep><i class="fas fa-triangle-exclamation me-2"></i><strong>No mail server is set up.</strong> Emails are saved to <code>storage/mail/</code> and listed in the Sent log, but nobody receives them. Add <code>SMTP_HOST</code>, <code>SMTP_PORT</code>, <code>SMTP_USER</code>, <code>SMTP_PASS</code> and <code>MAIL_FROM</code> to the server's <code>.env</code> (see docs/DEPLOYMENT.md). Two-step sign-in stays off until then.</div>
<?php endif; ?>

<?php if ($tpl): $vars = $sample($tpl['variables']); $preview = render_email_template($tpl['code'] === '_layout' ? 'password_reset' : $tpl['code'], $vars); ?>
<div class="row g-3">
    <div class="col-xl-7">
        <form method="post" class="card">
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>"><input type="hidden" name="action" value="save">
                <p class="text-muted small"><?= h($tpl['description']) ?></p>
                <div class="mb-3"><label class="form-label fw-semibold" for="subject">Subject</label><input class="form-control" id="subject" name="subject" maxlength="200" required value="<?= h($tpl['subject']) ?>"></div>
                <label class="form-label fw-semibold" for="body_html">Message</label>
                <textarea id="body_html" name="body_html" data-rich-text="email" aria-label="Email message"><?= h($tpl['body_html']) ?></textarea>
                <div class="form-text mt-2">Placeholders you can use: <?php foreach (array_filter(array_map('trim', explode(',', (string) $tpl['variables']))) as $v): ?><code class="me-1">{{<?= h($v) ?>}}</code><?php endforeach; ?>. Buttons are links with the style "button" (in Source view: <code>&lt;a class="button" href="{{reset_url}}"&gt;</code>).</div>
                <?php if ($tpl['code'] !== '_layout'): ?>
                <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= $tpl['is_active'] ? ' checked' : '' ?>><label class="form-check-label" for="is_active">Send this email (switch off to stop it being sent)</label></div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white d-flex gap-2"><button class="btn btn-primary" data-busy-label="Saving">Save template</button><a class="btn btn-link" href="<?= BASE_URL ?>/pages/superadmin/email-templates.php">Back to all templates</a></div>
        </form>
        <?php if ($tpl['code'] !== '_layout'): ?>
        <form method="post" class="card mt-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>"><input type="hidden" name="action" value="test">
            <div class="flex-grow-1"><label class="form-label small fw-semibold" for="test_to">Send a test with sample values to</label><input class="form-control" type="email" id="test_to" name="test_to" value="<?= h($_SESSION['email']) ?>" required></div>
            <button class="btn btn-outline-primary" data-busy-label="Sending"><i class="fas fa-paper-plane me-1"></i>Send test</button>
        </div></form>
        <?php endif; ?>
    </div>
    <div class="col-xl-5">
        <div class="card"><div class="card-body">
            <h2 class="h6 fw-bold">Preview with sample values (saved version)</h2>
            <?php if ($preview): ?>
                <p class="small mb-2"><strong>Subject:</strong> <?= h($preview['subject']) ?></p>
                <iframe title="Email preview" sandbox="" class="w-100 border rounded" style="height:560px" srcdoc="<?= h($preview['html']) ?>"></iframe>
            <?php else: ?><p class="text-muted small">This template is switched off, so there is nothing to preview.</p><?php endif; ?>
        </div></div>
    </div>
</div>

<?php else: ?>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link<?= $tab === 'templates' ? ' active' : '' ?>" href="?tab=templates">Templates</a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'log' ? ' active' : '' ?>" href="?tab=log">Sent log</a></li>
</ul>
<?php if ($tab === 'templates'): ?>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Email</th><th>Subject</th><th>Status</th><th>Last edited</th><th class="text-end">Edit</th></tr></thead>
    <tbody>
    <?php foreach ($templates as $t): ?>
        <tr>
            <td><strong><?= h($t['name']) ?></strong><div class="small text-muted"><?= h($t['description']) ?></div></td>
            <td class="small"><?= h($t['subject']) ?></td>
            <td><?= $t['is_active'] ? '<span class="badge bg-success">On</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
            <td class="small text-muted"><?= h(timeAgo($t['updated_at'])) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?id=<?= (int) $t['id'] ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php else: ?>
<div class="card"><div class="card-body">
    <form class="row g-2 mb-3" method="get"><input type="hidden" name="tab" value="log">
        <div class="col-md-5"><input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search recipient or subject" aria-label="Search"></div>
        <div class="col-md-3"><select class="form-select" name="status" aria-label="Status" data-autosubmit><option value="">All statuses</option><?php foreach (['sent' => 'Sent', 'logged' => 'Saved (no mail server)', 'failed' => 'Failed'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
    </form>
    <?php if (!$log): echo empty_state('fa-envelope', 'No emails in the log', 'Emails appear here as soon as BodaERP sends one.'); else: ?>
    <div class="table-responsive"><table class="table table-sm align-middle" id="emailLog">
        <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Template</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?>
            <tr><td class="small text-nowrap"><?= h(formatDate($l['created_at'], 'j M Y H:i')) ?></td><td class="small"><?= h($l['to_email']) ?></td><td class="small"><?= h($l['subject']) ?></td><td class="small"><code><?= h($l['template_code']) ?></code></td>
                <td><span class="badge <?= ['sent' => 'bg-success', 'logged' => 'bg-info text-dark', 'failed' => 'bg-danger'][$l['status']] ?>"><?= h(['sent' => 'Sent', 'logged' => 'Saved only', 'failed' => 'Failed'][$l['status']]) ?></span><?php if ($l['error']): ?><div class="small text-danger"><?= h($l['error']) ?></div><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= render_pagination($pg) ?>
    <?php endif; ?>
</div></div>
<?php endif; ?>
<?php endif; ?>
<?php portal_end((bool) $tpl); ?>
