<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);

$active = 'database';
$uid = (int) ($_SESSION['user_id'] ?? 0);
migrator_auto();   // let the automatic update finish first, so the status below is what the database really looks like now

// actions (post → redirect → get)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';

    if ($act === 'update_now') {
        $m = migrator_run_tracked('manual:' . $uid);
        if ($m['skipped']) {
            $flash = ['warning', 'Another update is already running. Try again in a moment.'];
        } elseif ($m['failed'] || $m['error']) {
            $flash = ['danger', 'Update stopped at ' . ($m['failed'] ?? 'a migration') . ': ' . $m['error']];
        } else {
            $s = schema_sync_run_tracked('manual:' . $uid);
            $changes = count($m['applied']) + count($s['applied']);
            if ($s['skipped'])                    $flash = ['warning', 'Another update is already running. Try again in a moment.'];
            elseif ($s['failed'] || $s['error'])  $flash = ['danger', count($s['failed']) . ' change(s) could not be applied: details below. Everything else was updated.'];
            elseif ($changes)                     $flash = ['success', 'Database updated: ' . count($m['applied']) . ' migration(s) and ' . count($s['applied']) . ' structure change(s) applied.'];
            else                                  $flash = ['success', 'The database is already up to date.'];
        }
        audit_log('UPDATE', 'database', null, 'Updated database (migrations + schema sync)');
    } elseif ($act === 'save_snapshot') {
        if (!schema_capture_possible()) {
            $flash = ['warning', 'The schema file is only saved from your own computer (localhost). Nothing was changed.'];
        } else {
            try {
                $r = schema_capture('manual:' . $uid, true);
                $flash = ['success', 'Saved the structure of ' . $r['tables'] . ' tables to database/schema.snapshot.json. Commit that file with your code.'];
                audit_log('UPDATE', 'database', null, 'Saved schema snapshot');
            } catch (Throwable $e) {
                $flash = ['danger', 'Could not save the schema file: ' . $e->getMessage()];
            }
        }
    }
    if (isset($flash)) $_SESSION['flash_db'] = $flash;
    redirect('/pages/superadmin/database.php');
}
$flash = $_SESSION['flash_db'] ?? null; unset($_SESSION['flash_db']);

// data
$db        = db();
$info      = db_connection_info();
$mig       = migrator_status();
$migFail   = migrator_last_failure();
$snap      = schema_snapshot_load();
$snapMeta  = $snap['meta'] ?? null;
$state     = schema_state_load($db);
$plan      = $snap ? schema_plan($db, $snap) : ['actions' => [], 'drift' => [], 'extras' => []];
$unsupported = schema_unsupported_objects($db);
$failedItems = $state['failed'] ?? [];
$tables    = fetchAll("SELECT TABLE_NAME AS name, TABLE_ROWS AS row_est, (DATA_LENGTH + INDEX_LENGTH) AS bytes
                       FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY bytes DESC, name");
$totalBytes = array_sum(array_column($tables, 'bytes'));

$pending  = $mig['pending'] + count($plan['actions']);
$status   = ($migFail || $failedItems) ? 'attention' : ($pending ? 'pending' : 'ok');
$canSave  = schema_capture_possible();
$autoSave = schema_autocapture_enabled();
$isLocal  = $info['environment'] === 'local';

$lastApplied = null;                                   // most recent thing that changed this database
foreach ($mig['migrations'] as $m) if ($m['applied_at'] && (!$lastApplied || $m['applied_at'] > $lastApplied)) $lastApplied = $m['applied_at'];
if ($state && !empty($state['at']) && (!$lastApplied || $state['at'] > $lastApplied)) $lastApplied = $state['at'];

$typeLabel = ['create_table' => 'New table', 'add_column' => 'New column', 'widen_column' => 'Safe widening', 'add_index' => 'New index', 'add_fk' => 'New link'];
$fmtDate = fn(?string $d): string => $d ? date('M d, Y H:i', strtotime($d)) : '–';
$fmtSize = function (float $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Database | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .db-step { display: flex; gap: 14px; align-items: flex-start; }
        .db-step .num { flex: 0 0 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; color: #fff; background: var(--primary-blue); box-shadow: 0 6px 14px rgba(5, 108, 254,.28); }
        .db-step.off .num { background: #6b7280; box-shadow: none; }
        .db-step h6 { margin: 0 0 2px; font-weight: 700; font-size: .92rem; }
        .db-step p { margin: 0; font-size: .8rem; color: var(--text-muted); line-height: 1.5; }
        .db-kv { display: grid; grid-template-columns: 150px 1fr; gap: 10px 14px; font-size: .84rem; margin: 0; }
        .db-kv dt { color: var(--text-muted); font-weight: 600; }
        .db-kv dd { margin: 0; word-break: break-word; }
        .db-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 99px; padding: 4px 12px; font-size: .74rem; font-weight: 700; }
        .db-pill.ok { background: rgba(25,135,84,.12); color: #198754; }
        .db-pill.pending { background: rgba(245,158,11,.14); color: #b86e00; }
        .db-pill.attention { background: rgba(220,53,69,.12); color: #c0392b; }
        .db-pill.info { background: rgba(5, 108, 254,.1); color: var(--primary-blue); }
        .db-code { background: var(--off-white); border: 1px solid var(--light-gray); border-radius: 10px; padding: 6px 10px; font-family: Consolas, monospace; font-size: .74rem; word-break: break-all; }
        .db-sync-hero { border-radius: 20px; padding: 24px 26px; color: #fff; background: var(--primary-blue); position: relative; overflow: hidden; }
        .db-sync-hero.ok { background: #15803d; }
        .db-sync-hero.pending { background: #b45309; }
        .db-sync-hero.attention { background: #c0263a; }
        .db-sync-hero h4 { font-weight: 800; margin: 0 0 4px; }
        .db-sync-hero p { margin: 0; opacity: .92; font-size: .88rem; }
        .db-sync-hero .btn-light { font-weight: 700; border-radius: 99px; padding: 10px 22px; }
        .db-err { font-size: .76rem; color: #b02a37; word-break: break-word; }
        .btn-toggle-more { font-size: .8rem; font-weight: 600; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Database'; $pageSubtitle = 'Update the database from here: new tables and fields apply themselves'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">

        <?php if ($flash): ?><div class="alert alert-<?= h($flash[0]) ?>"><?= h($flash[1]) ?></div><?php endif; ?>
        <?php if ($info['source'] === 'legacy config/database.php'): ?>
            <div class="alert alert-warning py-2 small"><i class="fas fa-info-circle me-1"></i>The connection is read from the old <code>config/database.php</code>. It works, but move <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> into the <code>.env</code> file when convenient (see <code>.env.example</code>).</div>
        <?php endif; ?>

        <div class="db-sync-hero <?= $status ?> mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h4>
                        <?php if ($status === 'ok'): ?><i class="fas fa-check-circle me-2"></i>Database is up to date
                        <?php elseif ($status === 'pending'): ?><i class="fas fa-hourglass-half me-2"></i><?= (int) $pending ?> change<?= $pending === 1 ? '' : 's' ?> waiting to be applied
                        <?php else: ?><i class="fas fa-exclamation-triangle me-2"></i>Some changes need attention<?php endif; ?>
                    </h4>
                    <p>
                        <?php if ($status === 'ok'): ?>Every table and field from your code exists here. Last change applied: <?= h($fmtDate($lastApplied)) ?>.
                        <?php elseif ($status === 'pending'): ?>They apply automatically on the next visit, or you can apply them now.
                        <?php else: ?>The rest of the database was updated. Review the problems below, fix the cause, then press Update again.<?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_now">
                        <button class="btn btn-light"><i class="fas fa-sync-alt me-2"></i>Update database now</button></form>
                    <form method="post" action="<?= BASE_URL ?>/pages/superadmin/db-backup.php"><?= csrf_field() ?>
                        <button class="btn btn-outline-light" style="border-radius:99px;font-weight:600;"><i class="fas fa-file-download me-2"></i>Backup (.sql)</button></form>
                </div>
            </div>
        </div>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-server"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= h(strtoupper($info['environment'])) ?></div><div class="stat-hero-label">Environment · <?= h($info['database']) ?></div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-table"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= count($tables) ?> tables</div><div class="stat-hero-label">Size on disk · <?= h($fmtSize((float) $totalBytes)) ?></div></div></div></div>
            <div class="col"><div class="stat-hero-card <?= $mig['pending'] ? 'tone-gold' : 'tone-green' ?>"><div class="stat-hero-icon"><i class="fas fa-code-branch"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= count($mig['migrations']) - $mig['pending'] ?>/<?= count($mig['migrations']) ?> applied</div><div class="stat-hero-label">Migrations</div></div></div></div>
            <div class="col"><div class="stat-hero-card <?= $plan['actions'] ? 'tone-gold' : 'tone-green' ?>"><div class="stat-hero-icon"><i class="fas fa-columns"></i></div><div><div class="stat-hero-value" style="font-size:1.15rem;"><?= $snap ? ($plan['actions'] ? count($plan['actions']) . ' pending' : 'In sync') : 'No file yet' ?></div><div class="stat-hero-label">Schema file<?= $plan['drift'] ? ' · ' . count($plan['drift']) . ' to review' : '' ?></div></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7">

                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="icon-badge blue"><i class="fas fa-route"></i></div>
                        <div><h6 class="fw-bold mb-0">How your changes reach the live server</h6><small class="text-muted">You only work locally: nothing to import or upload by hand</small></div>
                    </div>
                    <div class="d-flex flex-column gap-4">
                        <div class="db-step <?= $isLocal ? '' : 'off' ?>">
                            <div class="num">1</div>
                            <div>
                                <h6>Change the database on your computer</h6>
                                <p>Add tables or fields however you prefer, in phpMyAdmin or with a migration. The structure (never the data) is saved to <code>database/schema.snapshot.json</code>
                                    <?php if ($isLocal): ?>
                                        <?= $autoSave ? '<span class="db-pill ok ms-1"><i class="fas fa-circle" style="font-size:.45rem;"></i>auto-save on</span>' : '<span class="db-pill attention ms-1">auto-save off</span>' ?>
                                    <?php else: ?>
                                        <span class="db-pill info ms-1">saved on your computer, not here</span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($isLocal && $canSave): ?>
                                    <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_snapshot">
                                        <button class="btn btn-sm btn-outline-primary"><i class="fas fa-save me-1"></i>Save local schema now</button></form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="db-step <?= $snap ? '' : 'off' ?>">
                            <div class="num">2</div>
                            <div>
                                <h6>Deploy your code as usual</h6>
                                <p>Commit and push <code>database/schema.snapshot.json</code> together with your code.
                                    <?php if ($snap && $snapMeta): ?>Current file: <strong><?= (int) ($snapMeta['tables'] ?? count($snap['tables'])) ?> tables</strong>, saved <?= h($fmtDate($snapMeta['saved_at'] ?? null)) ?>.
                                    <?php else: ?><strong>No schema file yet</strong>: it is created automatically the first time you use the app on localhost.<?php endif; ?></p>
                            </div>
                        </div>
                        <div class="db-step">
                            <div class="num">3</div>
                            <div>
                                <h6>The server updates itself</h6>
                                <p>On the first visit after a deploy, missing tables, fields, indexes and links are added automatically. Nothing is ever deleted or shrunk, and your data is never touched.
                                    <?= $state ? ' Last sync here: <strong>' . h($fmtDate($state['at'] ?? null)) . '</strong> (' . h($state['by'] ?? 'auto') . ').' : '' ?></p>
                            </div>
                        </div>
                    </div>
                </div></div>

                <?php if ($plan['actions']): ?>
                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge gold"><i class="fas fa-list-check"></i></div>
                        <div><h6 class="fw-bold mb-0">Waiting to be applied</h6><small class="text-muted">These will be added to this database</small></div>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Change</th><th>Where</th><th>Detail</th></tr></thead>
                        <tbody>
                        <?php foreach ($plan['actions'] as $a): ?>
                            <tr>
                                <td><span class="db-pill pending"><?= h($typeLabel[$a['type']] ?? $a['type']) ?></span></td>
                                <td style="font-size:.82rem;"><code><?= h($a['table']) ?></code><?= $a['type'] === 'create_table' ? '' : ' · <code>' . h($a['name']) . '</code>' ?></td>
                                <td style="font-size:.76rem;" class="text-muted"><?= h(mb_strimwidth($a['detail'], 0, 90, '…')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div></div>
                <?php endif; ?>

                <?php if ($migFail || $failedItems): ?>
                <div class="card mb-4 border-danger"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge red"><i class="fas fa-exclamation-triangle"></i></div>
                        <div><h6 class="fw-bold mb-0">Could not be applied</h6><small class="text-muted">The site keeps working. Fix the cause (often duplicate data for a new unique index), then press “Update database now”.</small></div>
                    </div>
                    <?php if ($migFail): ?>
                        <div class="alert alert-danger py-2 small mb-2"><strong>Migration <?= h($migFail['migration'] ?? '') ?></strong> failed (<?= h($migFail['at'] ?? '') ?>): <?= h($migFail['error'] ?? '') ?></div>
                    <?php endif; ?>
                    <?php foreach ($failedItems as $f): ?>
                        <div class="mb-2"><span class="db-pill attention"><?= h($typeLabel[$f['type']] ?? $f['type']) ?></span>
                            <code class="ms-1"><?= h($f['table']) ?>.<?= h($f['name']) ?></code><div class="db-err mt-1"><?= h($f['error']) ?></div></div>
                    <?php endforeach; ?>
                </div></div>
                <?php endif; ?>

                <?php if ($plan['drift']): ?>
                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge gold"><i class="fas fa-eye"></i></div>
                        <div><h6 class="fw-bold mb-0">Needs review: not changed automatically</h6><small class="text-muted">These differ from your code but could lose data. Ask for a migration to change them safely.</small></div>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Where</th><th>Your code expects</th><th>This database has</th></tr></thead>
                        <tbody>
                        <?php foreach ($plan['drift'] as $d): ?>
                            <tr>
                                <td style="font-size:.82rem;"><code><?= h($d['table']) ?></code><?= $d['kind'] === 'object' ? '' : ' · <code>' . h($d['name']) . '</code>' ?> <small class="text-muted"><?= h($d['kind']) ?></small></td>
                                <td style="font-size:.76rem;"><code><?= h(mb_strimwidth($d['snapshot'], 0, 70, '…')) ?></code></td>
                                <td style="font-size:.76rem;"><code><?= h(mb_strimwidth($d['current'], 0, 70, '…')) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div></div>
                <?php endif; ?>

                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-badge green"><i class="fas fa-table"></i></div>
                            <div><h6 class="fw-bold mb-0">Tables</h6><small class="text-muted"><?= count($tables) ?> tables · <?= h($fmtSize((float) $totalBytes)) ?></small></div>
                        </div>
                        <button class="btn btn-sm btn-light btn-toggle-more" data-bs-toggle="collapse" data-bs-target="#tablesBox">Show / hide</button>
                    </div>
                    <div class="collapse mt-3" id="tablesBox">
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Table</th><th class="text-end">Rows (approx.)</th><th class="text-end">Size</th></tr></thead>
                            <tbody>
                            <?php foreach ($tables as $t): ?>
                                <tr><td><code style="font-size:.78rem;"><?= h($t['name']) ?></code><?= schema_ignored_table($t['name']) ? ' <span class="db-pill info" style="font-size:.64rem;padding:2px 8px;">not synced</span>' : '' ?></td><td class="text-end" style="font-size:.82rem;"><?= number_format((int) $t['row_est']) ?></td><td class="text-end" style="font-size:.8rem;"><?= h($fmtSize((float) $t['bytes'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                        <?php if ($plan['extras']): ?>
                            <p class="small text-muted mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>Only in this database, not in the schema file: <?= h(implode(', ', $plan['extras'])) ?>. They are left alone.</p>
                        <?php endif; ?>
                    </div>
                </div></div>

                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-badge blue"><i class="fas fa-code-branch"></i></div>
                            <div><h6 class="fw-bold mb-0">Migration history</h6><small class="text-muted">For data fixes, renames and other one-off changes</small></div>
                        </div>
                        <button class="btn btn-sm btn-light btn-toggle-more" data-bs-toggle="collapse" data-bs-target="#migBox">Show / hide</button>
                    </div>
                    <div class="collapse mt-3" id="migBox">
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Update</th><th>What it does</th><th>Applied</th></tr></thead>
                            <tbody>
                            <?php foreach (array_reverse($mig['migrations']) as $m): ?>
                                <tr>
                                    <td><code style="font-size:.72rem;"><?= h($m['name']) ?></code></td>
                                    <td style="font-size:.8rem;"><?= h($m['description']) ?></td>
                                    <td style="font-size:.78rem;white-space:nowrap;"><?= $m['applied_at'] ? h($fmtDate($m['applied_at'])) . ' <small class="text-muted">· ' . h($m['applied_by']) . '</small>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    </div>
                </div></div>
            </div>

            <div class="col-xl-5">
                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge dark"><i class="fas fa-plug"></i></div>
                        <div><h6 class="fw-bold mb-0">Connection</h6><small class="text-muted">Connected · answered in <?= h((string) $info['latency_ms']) ?> ms</small></div>
                    </div>
                    <dl class="db-kv">
                        <dt>Environment</dt><dd><span class="db-pill <?= $isLocal ? 'info' : 'ok' ?>"><?= $isLocal ? 'Local (your computer)' : 'Live server' ?></span></dd>
                        <dt>Server</dt><dd><?= h($info['host']) ?>:<?= (int) $info['port'] ?></dd>
                        <dt>Database</dt><dd><code><?= h($info['database']) ?></code></dd>
                        <dt>User</dt><dd><?= h($info['user']) ?></dd>
                        <dt>Version</dt><dd><?= h($info['server']) ?></dd>
                        <dt>Settings from</dt><dd><?= h($info['source']) ?></dd>
                    </dl>
                    <hr>
                    <p class="small text-muted mb-1"><strong>Nothing to configure on your computer</strong>: it connects to MySQL on localhost and creates the database if it is missing.</p>
                    <p class="small text-muted mb-0">On the live server put the connection in one file, <code>.env</code> (copy <code>.env.example</code>): <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>.</p>
                </div></div>

                <div class="card mb-4"><div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-badge green"><i class="fas fa-shield-alt"></i></div>
                        <div><h6 class="fw-bold mb-0">Safe by design</h6><small class="text-muted">What the automatic update will and won’t do</small></div>
                    </div>
                    <ul class="small text-muted ps-3 mb-0" style="line-height:1.8;">
                        <li><strong class="text-dark">Adds</strong> tables, fields, indexes and links that are missing.</li>
                        <li><strong class="text-dark">Widens</strong> safely: longer text, bigger numbers, new choices at the end of a list, “allow empty”.</li>
                        <li><strong class="text-dark">Never</strong> deletes, renames or shrinks anything, and never changes your records.</li>
                        <li>Risky differences are listed for review instead of being applied.</li>
                        <li>A problem never takes the site down: it is reported here and retried.</li>
                        <?php if ($unsupported['views'] || $unsupported['triggers'] || $unsupported['routines']): ?>
                            <li class="text-warning-emphasis">Views (<?= (int) $unsupported['views'] ?>), triggers (<?= (int) $unsupported['triggers'] ?>) and routines (<?= (int) $unsupported['routines'] ?>) are updated through migrations, not by this sync.</li>
                        <?php endif; ?>
                    </ul>
                </div></div>

                <div class="card"><div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-badge blue"><i class="fas fa-server"></i></div>
                        <div><h6 class="fw-bold mb-0">System &amp; Updates</h6><small class="text-muted">Add a city (data packs), automatic jobs and scheduler</small></div>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/superadmin/system.php" class="btn btn-outline-primary btn-sm">Open</a>
                </div></div>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
