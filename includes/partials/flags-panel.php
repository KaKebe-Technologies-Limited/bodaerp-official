<?php
// Shared "Data Integrity Monitor" panel.
// Expects (set by the including page):
//   $flagScopeCity  ?string  - lock to one city (City Council) or null for all
//   $flagCanReview  bool     - show review / scan controls (Super Admin only)
//   $flagPageUrl    string   - absolute URL of the including page
//   $flagScanResult ?array   - result of a scan just run by this request
require_once __DIR__ . '/../anomaly_engine.php';

$rules = anomaly_rules();
$cats  = anomaly_categories();

if (!anomaly_tables_ready()): ?>
    <div class="alert alert-warning"><i class="fas fa-database me-2"></i>
        The anomaly-detection tables are not installed yet. Run <code>database/add_anomaly_detection.sql</code> on this database, then reload.</div>
<?php return; endif;

$filters = [
    'status'   => $_GET['status'] ?? 'active',
    'severity' => $_GET['severity'] ?? '',
    'rule'     => $_GET['rule'] ?? '',
    'city'     => $flagScopeCity ?: ($_GET['city'] ?? ''),
    'q'        => trim($_GET['q'] ?? ''),
];
if (!in_array($filters['status'], ['active', 'open', 'confirmed', 'dismissed', 'resolved', 'all'], true)) $filters['status'] = 'active';
if (!isset($rules[$filters['rule']])) $filters['rule'] = '';
if (!in_array($filters['severity'], ['', 'high', 'medium', 'low'], true)) $filters['severity'] = '';

$perPage = 25;
$page    = max(1, (int) ($_GET['p'] ?? 1));
[$flags, $totalFlags] = anomaly_list($filters, $perPage, ($page - 1) * $perPage);
$pages   = max(1, (int) ceil($totalFlags / $perPage));

$sum     = anomaly_summary($flagScopeCity ?: null);
$lastRun = anomaly_last_run();
$cityList = $flagScopeCity ? [] : fetchAll("SELECT id, name FROM cities ORDER BY name");

$qs = function (array $over = []) use ($filters) {
    return http_build_query(array_filter(array_merge($filters, $over), fn($v) => $v !== '' && $v !== null));
};
$sevStyle = ['high' => 'background:#e5484d;', 'medium' => 'background:#f59e0b;color:#1f1b3a;', 'low' => 'background:#2cc6a8;'];
$stBadge  = ['open' => ['Open', 'danger'], 'confirmed' => ['Confirmed', 'warning text-dark'], 'dismissed' => ['Dismissed', 'secondary'], 'resolved' => ['Auto-resolved', 'success']];
$ruleLabels = array_map(fn($r) => $r[0], $rules);
?>
<style>
    .flag-rule-card { display:flex; gap:12px; align-items:flex-start; padding:14px; border-radius:16px; background:var(--off-white); height:100%; }
    .flag-rule-card .ico { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#fff; font-size:0.9rem; }
    .flag-rule-card h6 { font-size:0.84rem; font-weight:700; margin:0 0 3px; }
    .flag-rule-card p { font-size:0.75rem; color:var(--text-muted); margin:0; line-height:1.45; }
    .flag-sev { display:inline-block; padding:3px 11px; border-radius:99px; font-size:0.66rem; font-weight:800; color:#fff; letter-spacing:.4px; text-transform:uppercase; }
    .flag-title { font-weight:700; font-size:0.85rem; color:var(--text-dark); }
    .flag-detail { font-size:0.76rem; color:var(--text-muted); line-height:1.45; max-width:460px; }
    .flag-note { font-size:0.72rem; color:var(--primary-blue-dark); background:var(--primary-blue-light); border-radius:8px; padding:3px 9px; display:inline-block; margin-top:4px; }
    .flag-rule-chip { font-size:0.7rem; font-weight:600; color:var(--text-muted); white-space:nowrap; }
</style>

<?php if (!empty($flagScanResult)): ?>
    <?php if (isset($flagScanResult['error'])): ?>
        <div class="alert alert-danger"><?= h($flagScanResult['error']) ?></div>
    <?php elseif (!empty($flagScanResult['skipped'])): ?>
        <div class="alert alert-warning">A scan is already running. Try again in a moment.</div>
    <?php else: ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Scan complete in <?= (int) $flagScanResult['ms'] ?> ms —
            <strong><?= (int) $flagScanResult['new'] ?></strong> new flag(s), <strong><?= (int) $flagScanResult['resolved'] ?></strong> auto-resolved,
            <strong><?= (int) $flagScanResult['open'] ?></strong> open overall.
            <?php if (!empty($flagScanResult['errors'])): ?><br><small class="text-danger">Some rules failed: <?= h(implode(', ', array_keys($flagScanResult['errors']))) ?></small><?php endif; ?></div>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($flagReviewMsg)): ?><div class="alert alert-success"><?= h($flagReviewMsg) ?></div><?php endif; ?>

<div class="dash-banner theme-dark">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 dash-banner-row">
        <div>
            <h4><i class="fas fa-shield-alt me-2"></i>Data Integrity Monitor</h4>
            <p>Explainable AI-assisted checks across riders, payments, enforcement and sign-ins. Every flag states its reason, is reviewed by a person, and clears itself once the data is fixed.</p>
        </div>
        <div class="text-end">
            <span class="dash-badge"><i class="fas fa-clock me-1"></i><?= $lastRun ? 'Last scan ' . h(date('M d, H:i', strtotime($lastRun['started_at']))) . ' · ' . h($lastRun['triggered_by']) : 'No scan yet' ?></span>
            <?php if (!empty($flagCanReview)): ?>
                <form method="post" class="d-inline ms-2">
                    <?= csrf_field() ?><input type="hidden" name="action" value="scan">
                    <button class="btn btn-light btn-sm fw-bold" type="submit"><i class="fas fa-sync-alt me-1"></i>Run scan now</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 row-cols-2 row-cols-xl-5 mb-4">
    <div class="col"><div class="stat-hero-card grad-dark"><div class="stat-hero-icon"><i class="fas fa-flag"></i></div><div><div class="stat-hero-value"><?= number_format($sum['open']) ?></div><div class="stat-hero-label">Open Flags</div></div></div></div>
    <div class="col"><div class="stat-hero-card grad-red"><div class="stat-hero-icon"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-hero-value"><?= number_format($sum['sev']['high']) ?></div><div class="stat-hero-label">High Severity</div></div></div></div>
    <div class="col"><div class="stat-hero-card grad-gold"><div class="stat-hero-icon"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-hero-value"><?= number_format($sum['sev']['medium']) ?></div><div class="stat-hero-label">Medium Severity</div></div></div></div>
    <div class="col"><div class="stat-hero-card grad-blue"><div class="stat-hero-icon"><i class="fas fa-user-tag"></i></div><div><div class="stat-hero-value"><?= number_format($sum['flagged_riders']) ?></div><div class="stat-hero-label">Riders Flagged</div></div></div></div>
    <div class="col"><div class="stat-hero-card grad-green"><div class="stat-hero-icon"><i class="fas fa-heartbeat"></i></div><div><div class="stat-hero-value"><?= $sum['health'] ?>%</div><div class="stat-hero-label">Data Health Score</div></div></div></div>
</div>

<div class="row g-3 g-md-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie text-primary me-2"></i>Open flags by severity</h6>
            <?php if ($sum['open'] === 0): ?>
                <div class="empty-state" style="padding:30px 10px;"><i class="fas fa-check-circle" style="color:#2cc6a8;"></i><h5>All clear</h5><p>No open anomalies detected.</p></div>
            <?php else: ?><div id="sevChart"></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar text-primary me-2"></i>Open flags by check</h6>
            <?php if (!$sum['by_rule']): ?>
                <div class="empty-state" style="padding:30px 10px;"><i class="fas fa-check-circle" style="color:#2cc6a8;"></i><h5>Nothing to review</h5><p>Every check passed on the latest scan.</p></div>
            <?php else: ?><div id="ruleChart"></div><?php endif; ?>
        </div></div>
    </div>
</div>

<div class="card mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fas fa-microscope text-primary me-2"></i>How detection works <span class="text-muted fw-normal small">— <?= count($rules) ?> transparent checks, no black box</span></h6>
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#howItWorks">Show / hide</button>
    </div>
    <div class="collapse" id="howItWorks">
        <div class="row g-3 mt-1">
            <?php foreach ($rules as $code => [$label, $cat, $icon, $sev, $desc]): ?>
                <div class="col-md-6 col-xl-4"><div class="flag-rule-card">
                    <div class="ico" style="background:<?= h($cats[$cat][1]) ?>;"><i class="fas <?= h($icon) ?>"></i></div>
                    <div><h6><?= h($label) ?> <span class="flag-sev ms-1" style="<?= $sevStyle[$sev] ?>"><?= h($sev) ?></span></h6><p><?= h($desc) ?></p></div>
                </div></div>
            <?php endforeach; ?>
        </div>
        <p class="small text-muted mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>Flags are leads for human review, never automatic penalties. Severity is the check's default; a few checks raise it when the evidence is stronger.</p>
    </div>
</div></div>

<div class="card mb-3 no-print"><div class="card-body">
    <form class="row g-2 align-items-center" method="get">
        <div class="col-lg-3 col-md-6"><input type="text" class="form-control" name="q" value="<?= h($filters['q']) ?>" placeholder="Search rider, plate, finding..."></div>
        <div class="col-lg-2 col-md-6">
            <select class="form-select" name="status">
                <?php foreach (['active' => 'Needs review', 'open' => 'Open only', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed', 'resolved' => 'Auto-resolved', 'all' => 'All'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-2 col-md-4">
            <select class="form-select" name="severity">
                <option value="">All severities</option>
                <?php foreach (['high', 'medium', 'low'] as $s): ?><option value="<?= $s ?>" <?= $filters['severity'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-lg-<?= $flagScopeCity ? 4 : 2 ?> col-md-4">
            <select class="form-select" name="rule">
                <option value="">All checks</option>
                <?php foreach ($cats as $ck => [$cl]): ?>
                    <optgroup label="<?= h($cl) ?>">
                        <?php foreach ($rules as $code => $r): if ($r[1] !== $ck) continue; ?>
                            <option value="<?= h($code) ?>" <?= $filters['rule'] === $code ? 'selected' : '' ?>><?= h($r[0]) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!$flagScopeCity): ?>
        <div class="col-lg-2 col-md-4">
            <select class="form-select" name="city">
                <option value="">All cities</option>
                <?php foreach ($cityList as $c): ?><option value="<?= h($c['id']) ?>" <?= $filters['city'] === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-lg-1 col-md-12"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="fw-bold mb-0"><i class="fas fa-list text-primary me-2"></i>Findings <span class="text-muted fw-normal small">(<?= number_format($totalFlags) ?>)</span></h6>
        <?php $exportTableId = 'flagsTable'; $exportFilename = 'data_integrity_flags'; $exportTitle = 'Data Integrity Flags'; require __DIR__ . '/export-toolbar.php'; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle" id="flagsTable">
            <thead><tr><th>Severity</th><th style="min-width:340px;">Finding</th><th>Subject</th><?php if (!$flagScopeCity): ?><th>City</th><?php endif; ?><th>Check</th><th>Last seen</th><th>Status</th><?php if (!empty($flagCanReview)): ?><th class="no-export">Review</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($flags as $f): [$stLabel, $stClass] = $stBadge[$f['status']] ?? [$f['status'], 'secondary']; ?>
                <tr>
                    <td><span class="flag-sev" style="<?= $sevStyle[$f['severity']] ?>"><?= h($f['severity']) ?></span></td>
                    <td>
                        <div class="flag-title"><?= h($f['title']) ?></div>
                        <div class="flag-detail"><?= h($f['details']) ?></div>
                        <?php if ($f['review_note']): ?><span class="flag-note"><i class="fas fa-comment-dots me-1"></i><?= h($f['reviewer_name'] ?: 'Reviewer') ?>: <?= h($f['review_note']) ?></span><?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;">
                        <?php if ($f['rider_name']): ?><strong><?= h($f['rider_name']) ?></strong><br><small class="text-muted"><?= h($f['bike_plate']) ?></small>
                        <?php else: ?><span class="text-muted"><?= h(ucfirst($f['entity_type'])) ?></span><br><small><?= h($f['entity_id']) ?></small><?php endif; ?>
                    </td>
                    <?php if (!$flagScopeCity): ?><td style="font-size:0.82rem;"><?= h($f['city_name'] ?: '—') ?></td><?php endif; ?>
                    <td><span class="flag-rule-chip"><i class="fas <?= h($rules[$f['rule_code']][2] ?? 'fa-flag') ?> me-1" style="color:<?= h($cats[$f['category']][1] ?? '#5b4bd5') ?>;"></i><?= h($rules[$f['rule_code']][0] ?? $f['rule_code']) ?></span></td>
                    <td style="font-size:0.78rem;white-space:nowrap;"><?= h(date('M d, Y', strtotime($f['last_seen_at']))) ?><br><small class="text-muted">since <?= h(date('M d', strtotime($f['first_detected_at']))) ?></small></td>
                    <td><span class="badge bg-<?= $stClass ?>"><?= h($stLabel) ?></span></td>
                    <?php if (!empty($flagCanReview)): ?>
                    <td class="no-export" style="white-space:nowrap;">
                        <?php if ($f['status'] === 'open'): ?>
                            <button class="btn btn-sm btn-outline-warning" title="Confirm as a real issue" onclick='openReview(<?= (int) $f['id'] ?>,"confirm",<?= json_encode($f['title']) ?>)'><i class="fas fa-check"></i></button>
                        <?php endif; ?>
                        <?php if (in_array($f['status'], ['open', 'confirmed'], true)): ?>
                            <button class="btn btn-sm btn-outline-secondary" title="Dismiss as false positive" onclick='openReview(<?= (int) $f['id'] ?>,"dismiss",<?= json_encode($f['title']) ?>)'><i class="fas fa-ban"></i></button>
                        <?php endif; ?>
                        <?php if (in_array($f['status'], ['confirmed', 'dismissed'], true)): ?>
                            <button class="btn btn-sm btn-outline-primary" title="Reopen" onclick='openReview(<?= (int) $f['id'] ?>,"reopen",<?= json_encode($f['title']) ?>)'><i class="fas fa-undo"></i></button>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$flags): ?>
            <div class="empty-state"><i class="fas fa-check-circle" style="color:#2cc6a8;"></i><h5>No findings match</h5><p>Nothing to review with the current filters<?= $filters['status'] === 'active' ? ' — the data looks healthy.' : '.' ?></p></div>
        <?php endif; ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="mt-3 no-print"><ul class="pagination pagination-sm justify-content-center mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($flagPageUrl . '?' . $qs(['p' => $page - 1])) ?>">&laquo;</a></li>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h($flagPageUrl . '?' . $qs(['p' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= h($flagPageUrl . '?' . $qs(['p' => $page + 1])) ?>">&raquo;</a></li>
        </ul></nav>
    <?php endif; ?>
</div></div>

<?php if (!empty($flagCanReview)): ?>
<div class="modal fade" id="reviewModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="review"><input type="hidden" name="flag_id" id="rvFlagId"><input type="hidden" name="decision" id="rvDecision">
        <div class="modal-header" style="background:var(--primary-blue-gradient);color:#fff;border-bottom:none;">
            <h5 class="modal-title fw-bold" id="rvTitle">Review flag</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
            <p class="fw-semibold mb-2" id="rvFinding"></p>
            <label class="form-label fw-semibold">Note <span class="text-muted fw-normal">(optional, visible to government viewers)</span></label>
            <textarea class="form-control" name="note" rows="3" maxlength="300" placeholder="e.g. Verified with the chairperson — plate was re-registered after a sale."></textarea>
        </div>
        <div class="modal-footer border-0 pb-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="rvSubmit">Save</button>
        </div>
    </form>
</div></div></div>
<script>
    function openReview(id, decision, finding) {
        const t = { confirm: ['Confirm as real issue', 'Confirm'], dismiss: ['Dismiss as false positive', 'Dismiss'], reopen: ['Reopen flag', 'Reopen'] }[decision];
        document.getElementById('rvFlagId').value = id;
        document.getElementById('rvDecision').value = decision;
        document.getElementById('rvTitle').textContent = t[0];
        document.getElementById('rvSubmit').textContent = t[1];
        document.getElementById('rvFinding').textContent = finding;
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }
</script>
<?php endif; ?>

<script>
    <?php if ($sum['open'] > 0): ?>
    new ApexCharts(document.querySelector('#sevChart'), {
        series: [<?= $sum['sev']['high'] ?>, <?= $sum['sev']['medium'] ?>, <?= $sum['sev']['low'] ?>],
        chart: { type: 'donut', height: 250 },
        labels: ['High', 'Medium', 'Low'],
        colors: ['#e5484d', '#f59e0b', '#2cc6a8'],
        legend: { position: 'bottom', fontSize: '12px' },
        plotOptions: { pie: { donut: { size: '66%', labels: { show: true, total: { show: true, label: 'Open', formatter: () => '<?= number_format($sum['open']) ?>' } } } } },
        stroke: { width: 0 }, dataLabels: { enabled: false }
    }).render();
    new ApexCharts(document.querySelector('#ruleChart'), {
        series: [{ name: 'Open flags', data: <?= json_encode(array_values($sum['by_rule'])) ?> }],
        chart: { type: 'bar', height: <?= max(160, 42 * count($sum['by_rule']) + 40) ?>, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '58%', distributed: true } },
        colors: <?= json_encode(array_map(fn($c) => $cats[$rules[$c][1] ?? 'data_quality'][1] ?? '#5b4bd5', array_keys($sum['by_rule']))) ?>,
        dataLabels: { enabled: true, style: { fontSize: '11px' } }, legend: { show: false },
        xaxis: { categories: <?= json_encode(array_map(fn($c) => $ruleLabels[$c] ?? $c, array_keys($sum['by_rule']))) ?> },
        grid: { borderColor: '#efecfa', strokeDashArray: 4 }, tooltip: { theme: 'dark' }
    }).render();
    <?php endif; ?>
</script>
