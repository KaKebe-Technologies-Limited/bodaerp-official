<?php
// ============================================================
// BodaERP — api/payments.php
// Rider self-service ioTec Pay mobile-money collection: initiate a
// payment, poll its status, and (admin) reconcile stuck-pending ones.
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/iotec_functions.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Rider: initiate a payment for their own annual tax ──────────
if ($action === 'initiate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['rider']);
    csrf_verify();

    $riderId = (int) ($_SESSION['rider_id'] ?? 0);
    $phone   = trim($_POST['phone'] ?? '');

    if (!$riderId) {
        echo json_encode(['success' => false, 'message' => 'No rider profile linked to this account.']);
        exit;
    }
    if ($phone === '') {
        echo json_encode(['success' => false, 'message' => 'Mobile money number is required.']);
        exit;
    }

    $already = fetchValue(
        "SELECT COUNT(*) FROM payments WHERE rider_id=? AND status='Confirmed' AND fiscal_year=?",
        [$riderId, CURRENT_FISCAL_YEAR]
    );
    if ($already) {
        echo json_encode(['success' => false, 'message' => 'You have already paid for this fiscal year.']);
        exit;
    }

    $result = initiateRiderPayment($riderId, $phone);
    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'message' => $result['error']]);
        exit;
    }

    $message = $result['status'] === 'completed'
        ? 'Payment confirmed — thank you!'
        : 'Payment request sent. Approve it on your phone to complete.';
    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'payment_id' => $result['payment_id'],
        'status'     => $result['status'],
    ]);
    exit;
}

// ── Rider: poll a payment's status while it's pending ───────────
if ($action === 'check_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_role(['rider']);

    $paymentId = (int) ($_GET['payment_id'] ?? 0);
    $riderId   = (int) ($_SESSION['rider_id'] ?? 0);

    $payment = fetchOne("SELECT id FROM payments WHERE id=? AND rider_id=?", [$paymentId, $riderId]);
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found.']);
        exit;
    }

    $result = verifyIotecPaymentTransaction($paymentId);
    echo json_encode($result);
    exit;
}

// ── Admin: re-check stuck-pending payments directly against ioTec ──
if ($action === 'reconcile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['city_admin', 'super_admin']);
    csrf_verify();

    $summary = reconcilePendingPayments(50);
    echo json_encode(['success' => true] + $summary);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
