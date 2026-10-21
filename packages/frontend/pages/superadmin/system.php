<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);
require_once __DIR__ . '/../../../backend/datapacks.php';

$active = 'system';
$tabs = ['database' => 'Database updates', 'packs' => 'Data packs', 'automation' => 'Automation', 'backup' => 'Backup & scheduler'];
$tab = $_GET['tab'] ?? 'database';
if (!isset($tabs[$tab])) $tab = 'database';

// ---------- actions (post → redirect → get) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $uid = (int) $_SESSION['user_id'];
    $goto = $tab;

    if ($act === 'run_migrations') {
        $r = migrator_run_tracked('manual:' . $uid);
        if ($r['failed'] || ($r['error'] && !$r['skipped'])) $flash = ['danger', 'Update failed: ' . ($r['failed'] ?? '') . ' — ' . $r['error']];
        elseif ($r['skipped'])                                $flash = ['warning', 'Another update is already running. Try again in a moment.'];
        else                                                  $flash = ['success', $r['applied'] ? 'Applied ' . count($r['applied']) . ' database update(s).' : 'The database is already up to date.'];
        audit_log('UPDATE', 'database', null, 'Applied pending database updates');
        $goto = 'database';
    } elseif ($act === 'apply_pack') {
        $code = $_POST['pack'] ?? '';
        $r = datapack_apply($code, $uid);
        if ($r['ok']) { $flash = ['success', $r['summary']]; $_SESSION['flash_creds'] = $r['credentials']; audit_log('CREATE', 'data_pack', $code, 'Applied data pack'); }
        else          { $flash = ['danger', 'Data pack failed and was rolled back: ' . $r['error']]; }
        $goto = 'packs';
    } elseif ($act === 'run_job') {
        $r = automation_run_job($_POST['job'] ?? '', 'manual:' . $uid);
        $flash = [$r['ok'] ? 'success' : 'danger', ($r['ok'] ? 'Job finished: ' : 'Job failed: ') . $r['message']];
        $goto = 'automation';
    } elseif ($act === 'run_all') {
        $r = automation_run_due('manual:' . $uid, true);
        $flash = $r['skipped'] ? ['warning', 'Jobs are already running. Try again in a moment.'] : ['success', 'Ran ' . count($r['ran']) . ' enabled job(s).'];
        $goto = 'automation';
    } elseif ($act === 'toggle_job') {
        $code = $_POST['job'] ?? '';
        if (isset(automation_jobs()[$code])) {
            runQuery("UPDATE automation_jobs SET enabled = 1 - enabled WHERE job_code = ?", [$code])->close();
            audit_log('UPDATE', 'automation', $code, 'Toggled automation job');
        }
        $flash = ['success', 'Job setting saved.'];
        $goto = 'automation';
    } elseif ($act === 'regen_token') {
        automation_cron_token(true);
        audit_log('UPDATE', 'automation', null, 'Regenerated scheduler token');
        $flash = ['success', 'A new scheduler token was generated. Update any cron job that used the old one.'];
        $goto = 'backup';
    }
    if (isset($flash)) $_SESSION['flash_sys'] = $flash;
    redirect('/pages/superadmin/system.php?tab=' . $goto);
}
$flash = $_SESSION['flash_sys'] ?? null;   unset($_SESSION['flash_sys']);
$creds = $_SESSION['flash_creds'] ?? null; unset($_SESSION['flash_creds']);

// ---------- data ----------
$mig        = migrator_status();
$migFailure = migrator_last_failure();
$packs      = datapacks_list();
$autoReady  = automation_tables_ready();
$jobs       = $autoReady ? automation_state() : [];
$token      = $autoReady ? automation_cron_token() : '';
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$cronUrl    = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain') . BASE_URL . '/cron/run.php?token=' . $token;
$cronCli    = '*/5 * * * *  /usr/bin/php ' . dirname(__DIR__, 2) . '/cron/run.php';
$enabledJobs = count(array_filter($jobs, fn($j) => $j['enabled']));
$failedJobs  = count(array_filter($jobs, fn($j) => $j['last_status'] === 'error'));
$appliedPacks = count(array_filter($packs, fn($p) => $p['applied']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'System & Updates - BodaERP Platform'; require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .sys-code { background: var(--off-white); border-radius: 12px; padding: 10px 14px; font-family: Consolas, monospace; font-size: 0.78rem; word-break: break-all; border: 1px solid var(--light-gray); }
        .pack-card { border-radius: 18px; background: var(--off-white); padding: 20px; height: 100%; }
        .nav-pills .nav-link { border-radius: 99px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); padding: 8px 18px; }
        .nav-pills .nav-link.active { background: var(--primary-blue-gradient); color: #fff; box-shadow: 0 6px 16px rgba(91,75,213,0.3); }
        .job-msg { font-size: 0.76rem; color: var(--text-muted); max-width: 360px; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'System & Updates'; $pageSubtitle = 'Update the database, add cities and run automatic jobs — all online, no uploads'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">

        <?php if ($flash): ?><div class="alert alert-<?= h($flash[0]) ?>"><?= h($flash[1]) ?></div><?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-server"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= h(strtoupper(getDbEnvironment())) ?></div><div class="stat-hero-label">Environment · <?= h(DB_NAME) ?></div></div></div></div>
            <div class="col"><div class="stat-hero-card <?= $mig['pending'] || $migFailure ? 'grad-gold' : 'grad-green' ?>"><div class="stat-hero-icon"><i class="fas fa-database"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= $mig['pending'] ? (int) $mig['pending'] . ' pending' : 'Up to date' ?></div><div class="stat-hero-label">Database · <?= count($mig['migrations']) ?> updates tracked</div></div></div></div>
            <div class="col"><div class="stat-hero-card <?= $failedJobs ? 'grad-red' : 'grad-blue' ?>"><div class="stat-hero-icon"><i class="fas fa-robot"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= $enabledJobs ?>/<?= count($jobs) ?> on</div><div class="stat-hero-label">Automation<?= $failedJobs ? " · {$failedJobs} failing" : '' ?></div></div></div></div>
            <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-city"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= $appliedPacks ?>/<?= count($packs) ?> applied</div><div class="stat-hero-label">Data packs</div></div></div></div>
        </div>

        <ul class="nav nav-pills gap-2 mb-4 flex-wrap">
            <?php foreach ($tabs as $k => $label): ?>
                <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= h($label) ?></a></li>
            <?php endforeach; ?>
        </ul>

        <?php if ($tab === 'database'): ?>
        <div class="card"><div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-badge <?= $mig['pending'] || $migFailure ? 'gold' : 'green' ?>"><i class="fas fa-database"></i></div>
                    <div><h6 class="fw-bold mb-0">Database updates</h6><small class="text-muted">New tables and fields apply themselves after a deploy. Nothing to import.</small></div>
                </div>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_migrations">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-sync-alt me-1"></i>Check &amp; apply now</button></form>
            </div>
            <?php if ($migFailure): ?>
                <div class="alert alert-danger py-2 small"><strong>Last automatic update failed</strong> (<?= h($migFailure['at']) ?>): <?= h($migFailure['migration']) ?> — <?= h($migFailure['error']) ?>. It retries every 5 minutes; fix the cause or press “Check &amp; apply now”.</div>
            <?php endif; ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Update</th><th>What it does</th><th>Applied</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($mig['migrations']) as $m): ?>
                    <tr>
                        <td><code style="font-size:0.72rem;"><?= h($m['name']) ?></code></td>
                        <td style="font-size:0.82rem;"><?= h($m['description']) ?></td>
                        <td style="font-size:0.8rem;white-space:nowrap;"><?= $m['applied_at'] ? h(date('M d, Y H:i', strtotime($m['applied_at']))) . ' <small class="text-muted">· ' . h($m['applied_by']) . '</small>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div>
        <?php endif; ?>

        <?php if ($tab === 'packs'): ?>
            <?php if ($creds): ?>
            <div class="card mb-4"><div class="card-body">
                <h6 class="fw-bold mb-1"><i class="fas fa-key text-primary me-2"></i>Logins from this pack</h6>
                <p class="small text-muted mb-3">Share these with the people concerned and ask them to change the password after first sign-in. Passwords are only shown for accounts created just now.</p>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0" id="credsTable">
                    <thead><tr><th>Role</th><th>Name</th><th>Email</th><th>Password</th></tr></thead>
                    <tbody>
                    <?php foreach ($creds as $c): ?>
                        <tr><td><span class="badge bg-primary"><?= h(strtoupper(str_replace('_', ' ', $c['role']))) ?></span></td><td><?= h($c['name']) ?></td><td><?= h($c['email']) ?></td>
                            <td><?= $c['password'] ? '<code>' . h($c['password']) . '</code>' : '<span class="text-muted small">already existed — unchanged</span>' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div></div>
            <?php endif; ?>
            <div class="row g-3">
                <?php foreach ($packs as $p): ?>
                <div class="col-lg-6"><div class="pack-card">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-badge blue"><i class="fas fa-city"></i></div>
                            <div><h6 class="fw-bold mb-0"><?= h($p['title']) ?></h6>
                                <?php if ($p['applied']): ?><small class="text-success"><i class="fas fa-check-circle me-1"></i>Applied <?= h(date('M d, Y H:i', strtotime($p['applied']['applied_at']))) ?> · run <?= (int) $p['applied']['run_count'] ?>×</small>
                                <?php else: ?><small class="text-muted">Not applied yet</small><?php endif; ?></div>
                        </div>
                    </div>
                    <p class="small text-muted"><?= h($p['description']) ?></p>
                    <?php if ($p['hint']): ?><p class="small mb-2"><i class="fas fa-key text-primary me-1"></i><?= h($p['hint']) ?></p><?php endif; ?>
                    <?php if ($p['applied']): ?><p class="small mb-3"><i class="fas fa-info-circle text-primary me-1"></i><?= h($p['applied']['summary']) ?></p><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Apply “<?= h(addslashes($p['title'])) ?>”? Existing records are left untouched.');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="apply_pack"><input type="hidden" name="pack" value="<?= h($p['code']) ?>">
                        <button class="btn <?= $p['applied'] ? 'btn-outline-primary' : 'btn-primary' ?> btn-sm"><i class="fas fa-play me-1"></i><?= $p['applied'] ? 'Re-apply (fill anything missing)' : 'Apply this data pack' ?></button>
                    </form>
                </div></div>
                <?php endforeach; ?>
                <?php if (!$packs): ?><div class="col-12"><div class="empty-state"><i class="fas fa-box-open"></i><h5>No data packs found</h5></div></div><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'automation'): ?>
        <div class="card"><div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-badge blue"><i class="fas fa-robot"></i></div>
                    <div><h6 class="fw-bold mb-0">Automatic jobs</h6><small class="text-muted">Run by themselves while the system is in use — no cron needed. Switch any job off if you prefer.</small></div>
                </div>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_all">
                    <button class="btn btn-outline-primary btn-sm"><i class="fas fa-forward me-1"></i>Run all enabled jobs now</button></form>
            </div>
            <?php if (!$autoReady): ?>
                <div class="alert alert-warning">Automation tables are not installed yet — press “Check &amp; apply now” on the Database updates tab.</div>
            <?php else: ?>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Job</th><th>Schedule</th><th>Last run</th><th>Enabled</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $j): ?>
                    <tr>
                        <td style="min-width:260px;"><div class="fw-bold" style="font-size:0.88rem;"><?= h($j['title']) ?></div><div class="job-msg"><?= h($j['desc']) ?></div></td>
                        <td style="font-size:0.82rem;white-space:nowrap;"><?= h(automation_every_label($j['every'])) ?><br><small class="text-muted"><?= $j['enabled'] ? ($j['due'] ? 'due now' : 'next ' . h(date('M d H:i', strtotime($j['next_due']))) ) : 'paused' ?></small></td>
                        <td>
                            <?php if ($j['last_run_at']): ?>
                                <span class="badge bg-<?= $j['last_status'] === 'ok' ? 'success' : 'danger' ?>"><?= $j['last_status'] === 'ok' ? 'OK' : 'Error' ?></span>
                                <small class="text-muted ms-1"><?= h(date('M d, H:i', strtotime($j['last_run_at']))) ?> · <?= (int) $j['last_ms'] ?> ms · <?= h($j['by']) ?></small>
                                <div class="job-msg"><?= h($j['last_message']) ?></div>
                            <?php else: ?><span class="text-muted small">Never run</span><?php endif; ?>
                        </td>
                        <td>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_job"><input type="hidden" name="job" value="<?= h($j['code']) ?>">
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" <?= $j['enabled'] ? 'checked' : '' ?> onchange="this.form.submit()" title="Turn this job on or off"></div></form>
                        </td>
                        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="run_job"><input type="hidden" name="job" value="<?= h($j['code']) ?>">
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-play me-1"></i>Run now</button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>

        <?php if ($tab === 'backup'): ?>
        <div class="row g-3 g-md-4">
            <div class="col-lg-5"><div class="card h-100"><div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3"><div class="icon-badge green"><i class="fas fa-download"></i></div>
                    <div><h6 class="fw-bold mb-0">Database backup</h6><small class="text-muted">A full .sql copy you can restore in phpMyAdmin</small></div></div>
                <p class="small text-muted">Take a backup before big changes. It contains all tables and data (including password hashes), so keep the file private. Sign-in sessions are left out.</p>
                <form method="post" action="<?= BASE_URL ?>/pages/superadmin/db-backup.php"><?= csrf_field() ?>
                    <button class="btn btn-primary"><i class="fas fa-file-download me-2"></i>Download backup (.sql)</button></form>
            </div></div></div>
            <div class="col-lg-7"><div class="card h-100"><div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3"><div class="icon-badge gold"><i class="fas fa-clock"></i></div>
                    <div><h6 class="fw-bold mb-0">Scheduler (optional)</h6><small class="text-muted">Jobs already run while people use the system. Add a cron for exact timing on quiet days.</small></div></div>
                <div class="small fw-semibold mb-1">Hostinger cron job (command)</div>
                <div class="sys-code mb-3"><?= h($cronCli) ?></div>
                <div class="small fw-semibold mb-1">…or ping this URL every few minutes (e.g. cron-job.org)</div>
                <div class="sys-code mb-2" id="cronUrl"><?= h($cronUrl) ?></div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').textContent.trim()); this.innerHTML='<i class=&quot;fas fa-check me-1&quot;></i>Copied'"><i class="fas fa-copy me-1"></i>Copy URL</button>
                    <form method="post" onsubmit="return confirm('Generate a new token? Any cron job using the old URL will stop working until updated.');"><?= csrf_field() ?><input type="hidden" name="action" value="regen_token">
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-sync-alt me-1"></i>Regenerate token</button></form>
                </div>
            </div></div></div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script>
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
