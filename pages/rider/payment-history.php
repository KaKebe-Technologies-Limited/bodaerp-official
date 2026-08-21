<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['rider']);
$active = 'payment-history';
$riderId = $_SESSION['rider_id'];
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
$payments = fetchAll("SELECT * FROM payments WHERE rider_id = ? ORDER BY paid_at DESC", [$riderId]);
$totalPaid = array_sum(array_column(array_filter($payments, fn($p) => $p['status']==='Confirmed'), 'amount'));
$currentYearAmt = 0;
foreach ($payments as $p) { if ($p['fiscal_year'] === CURRENT_FISCAL_YEAR && $p['status']==='Confirmed') $currentYearAmt += $p['amount']; }
$hasPaidThisYear = fetchValue(
    "SELECT COUNT(*) FROM payments WHERE rider_id=? AND status='Confirmed' AND fiscal_year=?",
    [$riderId, CURRENT_FISCAL_YEAR]
) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Payment History - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-rider.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Payment History'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <?php if (!$hasPaidThisYear): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
            <div><i class="fas fa-exclamation-triangle me-2"></i>You haven't paid your annual tax for <strong><?= h(CURRENT_FISCAL_YEAR) ?></strong> yet — <strong><?= h(formatCurrency($rider['annual_tax'])) ?></strong> due.</div>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payNowModal"><i class="fas fa-mobile-alt me-1"></i>Pay Now</button>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= h(formatCurrency($currentYearAmt)) ?></h4><small class="text-muted">Current Year Tax</small></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= count($payments) ?></h4><small class="text-muted">Total Payments</small></div></div></div>
            <div class="col-md-4 col-6"><div class="card"><div class="card-body text-center"><h4 class="fw-bold"><?= h(formatCurrency($totalPaid)) ?></h4><small class="text-muted">Total Paid</small></div></div></div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <?php $exportTableId='myPaymentsTable'; $exportFilename='my_payment_history'; $exportTitle='My Payment History'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="myPaymentsTable">
                    <thead><tr><th>Year</th><th>Amount</th><th>Date Paid</th><th>Receipt #</th><th>Method</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$payments): ?><tr><td colspan="6" class="text-center py-4 text-muted">No payment history yet</td></tr><?php endif; ?>
                    <?php foreach ($payments as $p): $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                        <tr>
                            <td><?= h($p['fiscal_year']) ?></td>
                            <td><?= h(formatCurrency($p['amount'])) ?></td>
                            <td><?= formatDate($p['paid_at']) ?></td>
                            <td><code><?= h($p['receipt_number']) ?></code></td>
                            <td><?= h($p['payment_method']) ?></td>
                            <td><span class="badge <?= $sc[$p['status']] ?>"><?= h($p['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<!-- ══════════════ Pay Now Modal ══════════════ -->
<div class="modal fade" id="payNowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-mobile-alt text-primary me-2"></i>Pay Annual Tax</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="payNowBody">
        <div class="alert alert-light border text-center mb-3">
          <div class="text-muted small">Amount Due (<?= h(CURRENT_FISCAL_YEAR) ?>)</div>
          <div class="fs-3 fw-bold"><?= h(formatCurrency($rider['annual_tax'])) ?></div>
          <div class="text-muted small">Set by your city council — not editable</div>
        </div>
        <div id="payNowAlert" class="alert d-none"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Mobile Money Number <span class="text-danger">*</span></label>
          <input type="tel" class="form-control" id="payNowPhone" value="<?= h($rider['phone']) ?>" placeholder="0771234567">
          <small class="text-muted">You'll get a prompt on this number to approve the payment.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="payNowSubmitBtn" onclick="submitPayNow()"><i class="fas fa-lock me-1"></i>Pay Now</button>
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

    // ── Pay Now (ioTec Pay mobile money collection) ──────────────
    const PAY_CSRF = <?= json_encode(csrf_token()) ?>;
    let payNowPolling = null;

    function payNowAlert(msg, type) {
        const el = document.getElementById('payNowAlert');
        el.className = 'alert alert-' + type;
        el.textContent = msg;
    }

    async function submitPayNow() {
        const phone = document.getElementById('payNowPhone').value.trim();
        if (!phone) { payNowAlert('Enter your mobile money number.', 'danger'); return; }

        const btn = document.getElementById('payNowSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending request…';
        payNowAlert('Sending payment request…', 'info');

        const fd = new FormData();
        fd.append('action', 'initiate');
        fd.append('phone', phone);
        fd.append('csrf_token', PAY_CSRF);

        try {
            const res = await fetch('<?= BASE_URL ?>/api/payments.php?action=initiate', { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                payNowAlert(data.message || 'Payment failed.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock me-1"></i>Pay Now';
                return;
            }

            if (data.status === 'completed') {
                payNowAlert('✅ ' + data.message, 'success');
                setTimeout(() => location.reload(), 1500);
                return;
            }

            payNowAlert('📲 Check your phone (' + phone + ') and approve the payment prompt…', 'warning');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Waiting for approval…';
            pollPaymentStatus(data.payment_id);
        } catch (err) {
            payNowAlert('Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-1"></i>Pay Now';
        }
    }

    function pollPaymentStatus(paymentId) {
        let attempts = 0;
        const maxAttempts = 40; // ~2 minutes at 3s intervals
        clearInterval(payNowPolling);
        payNowPolling = setInterval(async () => {
            attempts++;
            try {
                const res = await fetch('<?= BASE_URL ?>/api/payments.php?action=check_status&payment_id=' + paymentId);
                const data = await res.json();

                if (data.status === 'confirmed') {
                    clearInterval(payNowPolling);
                    payNowAlert('✅ Payment confirmed — thank you!', 'success');
                    setTimeout(() => location.reload(), 1500);
                    return;
                }
                if (data.status === 'failed') {
                    clearInterval(payNowPolling);
                    payNowAlert('❌ Payment failed or was declined. Please try again.', 'danger');
                    const btn = document.getElementById('payNowSubmitBtn');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock me-1"></i>Pay Now';
                    return;
                }
            } catch (err) { /* keep polling — a transient network hiccup isn't fatal */ }

            if (attempts >= maxAttempts) {
                clearInterval(payNowPolling);
                payNowAlert('Still waiting on your mobile money provider. Refresh this page in a minute to check again.', 'warning');
            }
        }, 3000);
    }
</script>
</body>
</html>
