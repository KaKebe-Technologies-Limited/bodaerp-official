<?php
// Super Admin → SMS: EgoSMS status and balance, the text of every SMS template (per language), a test send,
// and the send log. Credentials stay in .env; this page never shows them.
require_once __DIR__ . '/../../../backend/admin_ui.php';
$me = require_role(['super_admin']);

$tab = in_array($_GET['tab'] ?? '', ['templates', 'log'], true) ? $_GET['tab'] : 'templates';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $body = trim(preg_replace('/\s+/', ' ', (string) ($_POST['body'] ?? '')));
        $tpl = fetchOne("SELECT * FROM sms_templates WHERE id = ?", [$id]);
        if (!$tpl) flash('error', 'That template no longer exists.');
        elseif ($body === '' || mb_strlen($body) > 480) flash('error', 'The message must be between 1 and 480 characters.');
        else {
            $reviewed = $tpl['language'] === 'en' || !empty($_POST['reviewed']) ? 0 : 1;
            runQuery("UPDATE sms_templates SET body = ?, is_active = ?, needs_review = ?, updated_by = ? WHERE id = ?",
                [$body, !empty($_POST['is_active']) ? 1 : 0, $reviewed, (int) $me['user_id'], $id]);
            audit_log('UPDATE', 'sms_template', $tpl['code'] . '/' . $tpl['language'], 'SMS template edited');
            flash('success', "\"{$tpl['name']}\" saved. It is " . sms_segments($body) . ' SMS long.');
        }
    } elseif ($action === 'add_language') {
        $code = (string) ($_POST['code'] ?? '');
        $lang = (string) ($_POST['language'] ?? '');
        $en = fetchOne("SELECT * FROM sms_templates WHERE code = ? AND language = 'en'", [$code]);
        if (!$en || !isset(LANGUAGES[$lang]) || $lang === 'en') flash('error', 'Choose a template and a language.');
        else {
            runQuery("INSERT IGNORE INTO sms_templates (code, language, name, description, body, variables, is_active, needs_review, updated_by) VALUES (?,?,?,?,?,?,1,1,?)",
                [$code, $lang, $en['name'], $en['description'], $en['body'], $en['variables'], (int) $me['user_id']]);
            flash('success', LANGUAGES[$lang] . ' copy added with the English text. Translate it, then tick "Reviewed by a native speaker".');
        }
    } elseif ($action === 'test') {
        $phone = (string) ($_POST['phone'] ?? '');
        if (!sms_normalize_phone($phone)) flash('error', 'Enter an MTN or Airtel number, for example 0772 123456.');
        elseif (!rate_limit('sms-test:' . (int) $me['user_id'], 5, 3600)) flash('error', 'Five test messages were sent in the last hour. Try again later.');
        else {
            $ok = send_sms($phone, get_setting('site_name', 'BodaERP') . ': test message from Super Admin at ' . date('H:i') . '. SMS works.', 'test');
            flash($ok ? 'success' : 'error', $ok ? (sms_transport_name() === 'egosms' ? 'Test SMS sent. It should arrive within a minute.' : 'EgoSMS is not set up, so the message was written to storage/sms/ instead of sent.') : 'EgoSMS refused the test message. See the log below for the reason.');
        }
        $tab = 'log';
    }
    redirect('/pages/superadmin/sms.php?tab=' . $tab);
}

$transport = sms_transport_name();
$cfg = sms_config();
$balance = $transport === 'egosms' ? egosms_balance() : null;
$templates = fetchAll("SELECT * FROM sms_templates ORDER BY code, language = 'en' DESC, language");
$byCode = [];
foreach ($templates as $t) $byCode[$t['code']][] = $t;
$month = fetchOne("SELECT COUNT(*) AS c, COALESCE(SUM(segments), 0) AS parts, COALESCE(SUM(cost), 0) AS cost, SUM(status = 'failed') AS failed
    FROM sms_log WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

portal_start('superadmin', 'sms', 'SMS', [
    'subtitle' => 'Text messages to riders and staff through EgoSMS',
]);
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="stat-hero-card <?= $transport === 'egosms' ? 'tone-green' : 'tone-gold' ?>"><div class="stat-hero-icon"><i class="fas fa-tower-cell" aria-hidden="true"></i></div><div>
        <div class="stat-hero-value"><?= $transport === 'egosms' ? ($cfg['mode'] === 'live' ? 'Live' : 'Sandbox') : 'Not set up' ?></div>
        <div class="stat-hero-label"><?= $transport === 'egosms' ? 'Sender ID ' . h($cfg['sender']) : 'Messages go to storage/sms/' ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-wallet" aria-hidden="true"></i></div><div>
        <div class="stat-hero-value"><?= $balance !== null ? h($balance) : 'Unknown' ?></div><div class="stat-hero-label">EgoSMS balance</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-paper-plane" aria-hidden="true"></i></div><div>
        <div class="stat-hero-value"><?= number_format((int) $month['c']) ?></div><div class="stat-hero-label">Sent this month (<?= number_format((int) $month['parts']) ?> parts)</div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-hero-card <?= (int) $month['failed'] ? 'tone-red' : 'tone-green' ?>"><div class="stat-hero-icon"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i></div><div>
        <div class="stat-hero-value"><?= number_format((int) $month['failed']) ?></div><div class="stat-hero-label">Failed this month</div></div></div></div>
</div>

<?php if ($transport !== 'egosms'): ?>
    <div class="alert alert-light border mb-4" data-keep>To send real messages, put <code>EGOSMS_USERNAME</code>, <code>EGOSMS_PASSWORD</code> and <code>EGOSMS_SENDER</code> in the server's <code>.env</code> file, and set <code>EGOSMS_MODE=live</code> when you are ready. Until then every message is saved in <code>storage/sms/</code> and listed in the log.</div>
<?php elseif (!setting_bool('feature_sms', true)): ?>
    <div class="alert alert-warning mb-4">SMS is switched off in Text &amp; branding → Features, so nothing is being sent.</div>
<?php endif; ?>

<nav class="nav nav-tabs mb-3" aria-label="SMS sections">
    <a class="nav-link<?= $tab === 'templates' ? ' active' : '' ?>" href="?tab=templates"<?= $tab === 'templates' ? ' aria-current="page"' : '' ?>>Message text</a>
    <a class="nav-link<?= $tab === 'log' ? ' active' : '' ?>" href="?tab=log"<?= $tab === 'log' ? ' aria-current="page"' : '' ?>>Send log and test</a>
</nav>

<?php if ($tab === 'templates'): ?>
    <p class="text-muted measure">One SMS holds 160 plain characters. Longer messages are split and cost more. Words in double braces, such as <code>{{amount}}</code>, are filled in when the message is sent; keep them as they are.</p>
    <?php foreach ($byCode as $code => $versions): $en = $versions[0]; ?>
    <section class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div><h2 class="h6 mb-1"><?= h($en['name']) ?></h2><p class="small text-muted mb-0"><?= h($en['description']) ?></p></div>
                <?php $missing = array_diff(array_keys(LANGUAGES), array_column($versions, 'language')); if ($missing): ?>
                <form method="post" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="add_language"><input type="hidden" name="code" value="<?= h($code) ?>">
                    <label class="visually-hidden" for="lang-<?= h($code) ?>">Language to add for <?= h($en['name']) ?></label>
                    <select class="form-select form-select-sm w-auto" id="lang-<?= h($code) ?>" name="language"><?php foreach ($missing as $l): ?><option value="<?= h($l) ?>"><?= h(LANGUAGES[$l]) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-outline-secondary btn-sm">Add a translation</button>
                </form>
                <?php endif; ?>
            </div>
            <?php foreach ($versions as $t): $fid = 'sms-' . (int) $t['id']; ?>
            <form method="post" class="sms-template-row">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <label class="form-label small fw-semibold" for="<?= $fid ?>"><?= h(LANGUAGES[$t['language']] ?? $t['language']) ?>
                    <?php if ($t['language'] !== 'en' && (int) $t['needs_review']): ?><span class="badge bg-warning text-dark ms-1">Not reviewed: riders get English</span><?php endif; ?>
                    <span class="text-muted fw-normal ms-1"><?= mb_strlen($t['body']) ?> characters, <?= sms_segments($t['body']) ?> SMS</span></label>
                <textarea class="form-control mb-2" id="<?= $fid ?>" name="body" rows="2" maxlength="480"><?= h($t['body']) ?></textarea>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="<?= $fid ?>-on"<?= (int) $t['is_active'] ? ' checked' : '' ?>><label class="form-check-label small" for="<?= $fid ?>-on">Send this message</label></div>
                    <?php if ($t['language'] !== 'en'): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="reviewed" value="1" id="<?= $fid ?>-ok"<?= (int) $t['needs_review'] ? '' : ' checked' ?>><label class="form-check-label small" for="<?= $fid ?>-ok">Reviewed by a native speaker</label></div><?php endif; ?>
                    <span class="small text-muted">Fills in: <?= h($t['variables']) ?></span>
                    <button class="btn btn-outline-primary btn-sm ms-auto" data-busy-label="Saving">Save</button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
<?php else:
    $total = (int) fetchValue("SELECT COUNT(*) FROM sms_log");
    $page = paginate($total, 50);
    $log = fetchAll("SELECT * FROM sms_log ORDER BY id DESC LIMIT " . (int) $page['per_page'] . " OFFSET " . (int) $page['offset']);
?>
    <div class="layout-thirds">
        <section class="card">
            <div class="card-body">
                <h2 class="section-title">Send log</h2>
                <?php if (!$log): ?>
                    <?= empty_state('fa-comment-sms', 'No messages yet', 'Receipts, reminders and sign-in codes appear here as they are sent.') ?>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Message</th><th scope="col">Result</th></tr></thead>
                        <tbody>
                        <?php foreach ($log as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= h(formatDate($row['created_at'], 'j M, H:i')) ?></td>
                                <td class="text-nowrap">…<?= h(sms_phone_hint($row['to_phone'])) ?></td>
                                <td><?= h($row['template_code'] ?? '') ?><?= $row['language'] && $row['language'] !== 'en' ? ' (' . h($row['language']) . ')' : '' ?><div class="small text-muted"><?= (int) $row['segments'] ?> part<?= (int) $row['segments'] === 1 ? '' : 's' ?><?= $row['cost'] !== null ? ', UGX ' . h(number_format((float) $row['cost'])) : '' ?></div></td>
                                <td><?php if ($row['status'] === 'sent'): ?><span class="badge bg-success">Sent</span><?php elseif ($row['status'] === 'logged'): ?><span class="badge bg-secondary">Saved only</span><?php else: ?><span class="badge bg-danger">Failed</span><div class="small text-danger"><?= h($row['error'] ?? '') ?></div><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= render_pagination($page) ?>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">Only the last three digits of each number are shown. Message text is not stored, because sign-in codes pass through here.</p>
            </div>
        </section>
        <aside class="card">
            <div class="card-body">
                <h2 class="section-title">Send a test message</h2>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="test">
                    <div class="mb-3"><label class="form-label" for="test-phone">Mobile number</label><input class="form-control" id="test-phone" name="phone" type="tel" inputmode="tel" placeholder="0772 123456" required></div>
                    <button class="btn btn-primary w-100" data-busy-label="Sending">Send test SMS</button>
                </form>
            </div>
        </aside>
    </div>
<?php endif; ?>
<?php portal_end(); ?>
