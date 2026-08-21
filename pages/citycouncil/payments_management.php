<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'payments_management';
$cityId = $_SESSION['city_id'];

$totalCollected = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id=? AND status='Confirmed'", [$cityId]);
$txnCount = (int) fetchValue("SELECT COUNT(*) FROM payments WHERE city_id=? AND status='Confirmed'", [$cityId]);
$pendingAmt = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id=? AND status='Pending'", [$cityId]);
$failedAmt = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE city_id=? AND status='Failed'", [$cityId]);

$byMethod = fetchAll("SELECT payment_method, COUNT(*) cnt, COALESCE(SUM(amount),0) total FROM payments WHERE city_id=? AND status='Confirmed' GROUP BY payment_method", [$cityId]);
$byMethodMap = [];
foreach ($byMethod as $m) { $byMethodMap[$m['payment_method']] = $m; }

$recent = fetchAll("SELECT p.*, r.full_name, s.name AS stage_name FROM payments p
    JOIN riders r ON r.id = p.rider_id LEFT JOIN stages s ON s.id = r.stage_id
    WHERE p.city_id = ? ORDER BY p.paid_at DESC LIMIT 15", [$cityId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Payments Management - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .council-stat-card { background: white; border-radius: 14px; padding: 16px 20px; border-left: 4px solid #0d6efd; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        .council-stat-card .stat-number { font-size: 1.6rem; font-weight: 900; color: #1a1a2e; line-height: 1; }
        .council-stat-card .stat-label { font-size: 0.75rem; color: #6c757d; font-weight: 500; }
        .council-stat-card .stat-icon { font-size: 1.6rem; opacity: 0.15; }
        .council-stat-card.gold { border-left-color: #f59e0b; }
        .council-stat-card.red { border-left-color: #dc3545; }
        .method-card { background: white; border-radius: 12px; padding: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); text-align: center; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Payments Management</span></h5>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="reconcileBtn" onclick="reconcilePending()" title="Re-check pending ioTec payments — catches ones that succeeded but never got marked confirmed">
                    <i class="fas fa-sync-alt me-1"></i>Reconcile Pending
                </button>
                <a href="<?= BASE_URL ?>/pages/citycouncil/payment-transactions.php" class="btn btn-primary btn-sm"><i class="fas fa-list me-1"></i>All Transactions</a>
            </div>
        </div>
    </header>

    <div class="content-area">
        <p id="reconcileStatus" class="small mb-3 d-none"></p>
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6"><div class="council-stat-card"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= h(formatCurrency($totalCollected)) ?></span><div class="stat-label">Total Collected</div></div><i class="fas fa-money-bill-wave stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= number_format($txnCount) ?></span><div class="stat-label">Transactions</div></div><i class="fas fa-exchange-alt stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card gold"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= h(formatCurrency($pendingAmt)) ?></span><div class="stat-label">Pending</div></div><i class="fas fa-clock stat-icon"></i></div></div></div>
            <div class="col-md-3 col-6"><div class="council-stat-card red"><div class="d-flex justify-content-between align-items-center"><div><span class="stat-number"><?= h(formatCurrency($failedAmt)) ?></span><div class="stat-label">Failed</div></div><i class="fas fa-times-circle stat-icon"></i></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach (['Mobile Money'=>'fa-mobile-alt','Cash'=>'fa-money-bill','Bank Transfer'=>'fa-university'] as $method => $icon):
                $m = $byMethodMap[$method] ?? ['cnt'=>0,'total'=>0];
            ?>
            <div class="col-md-4">
                <div class="method-card">
                    <i class="fas <?= $icon ?> fa-2x text-primary mb-2"></i>
                    <h5 class="fw-bold mb-0"><?= h(formatCurrency($m['total'])) ?></h5>
                    <small class="text-muted"><?= h($method) ?> · <?= (int) $m['cnt'] ?> transactions</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 no-print">
            <h6 class="fw-bold mb-0">Recent Transactions</h6>
            <?php $exportTableId='recentPaymentsTable'; $exportFilename='recent_payments'; $exportTitle='Recent Payments'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="recentPaymentsTable">
                        <thead><tr><th>Receipt</th><th>Rider</th><th>Stage</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $t): $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                            <tr>
                                <td><code><?= h($t['receipt_number']) ?></code></td>
                                <td><?= h($t['full_name']) ?></td>
                                <td><?= h($t['stage_name']) ?></td>
                                <td><?= h(formatCurrency($t['amount'])) ?></td>
                                <td><?= h($t['payment_method']) ?></td>
                                <td><?= formatDate($t['paid_at']) ?></td>
                                <td><span class="badge <?= $sc[$t['status']] ?>"><?= h($t['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });

    async function reconcilePending() {
        const btn = document.getElementById('reconcileBtn');
        const status = document.getElementById('reconcileStatus');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking…';
        status.classList.add('d-none');

        const fd = new FormData();
        fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);

        try {
            const res = await fetch('<?= BASE_URL ?>/api/payments.php?action=reconcile', { method: 'POST', body: fd });
            const d = await res.json();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Reconcile Pending';

            if (!d.success) {
                status.className = 'small mb-3 text-danger';
                status.textContent = d.message || 'Reconcile failed.';
                status.classList.remove('d-none');
                return;
            }

            status.className = 'small mb-3 ' + (d.confirmed > 0 ? 'text-success' : 'text-muted');
            status.textContent = `Checked ${d.checked} pending payment(s): ${d.confirmed} newly confirmed, ${d.failed} failed, ${d.still_pending} still pending` + (d.errors ? `, ${d.errors} errors` : '') + '.';
            status.classList.remove('d-none');
            if (d.confirmed > 0) setTimeout(() => location.reload(), 1800);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Reconcile Pending';
            status.className = 'small mb-3 text-danger';
            status.textContent = 'Network error.';
            status.classList.remove('d-none');
        }
    }
</script>
</body>
</html>
