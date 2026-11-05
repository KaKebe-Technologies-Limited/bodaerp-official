<?php
// BodaERP - permit payments (shared by cash collection and ioTec mobile money) One place decides what a
// confirmed payment does: receipt number, permit renewal, in-app notification, receipt email and audit
// entry. Safe to call twice for the same payment: only the first call that moves it out of Pending has any
// effect.
require_once __DIR__ . '/auth.php';

const PAYMENT_METHODS = ['Cash', 'Mobile Money', 'Bank Transfer'];

/** Days before expiry when a renewal may be paid (earlier payments would be charged twice for the same period). */
function permit_renewal_window_days(): int { return 30; }

/** Null when the rider may pay now, otherwise the reason they cannot. */
function permit_payment_block_reason(array $rider): ?string {
    if ($rider['status'] === 'active' && $rider['expiry_date'] && daysUntil($rider['expiry_date']) > permit_renewal_window_days()) {
        return 'This permit is valid until ' . formatDate($rider['expiry_date']) . '. Renewal opens ' . permit_renewal_window_days() . ' days before it expires.';
    }
    $pending = fetchValue("SELECT COUNT(*) FROM payments WHERE rider_id = ? AND status = 'Pending' AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)", [$rider['id']]);
    if ($pending) return 'A mobile-money payment for this rider is waiting for approval on the phone. Wait a few minutes before trying again.';
    return null;
}

/**
 * Applies the effects of a confirmed payment. $paymentId must currently be Pending (online) or just inserted
 * as Pending by the caller (cash). Returns false when another request already confirmed it.
 */
function payment_confirm(int $paymentId, string $actorLabel = 'System (ioTec Pay)'): bool {
    $db = db();
    $db->begin_transaction();
    try {
        $receipt = nextReceiptNumber($paymentId);
        $done = runQuery("UPDATE payments SET status = 'Confirmed', receipt_number = ?, paid_at = NOW() WHERE id = ? AND status = 'Pending'",
            [$receipt, $paymentId])->affected_rows === 1;
        if (!$done) { $db->rollback(); return false; }
        $payment = fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
        // Renew from the current expiry when paying early, so riders never lose the days they already paid for.
        runQuery("UPDATE riders SET status = 'active',
                    expiry_date = DATE_ADD(GREATEST(COALESCE(expiry_date, CURDATE()), CURDATE()), INTERVAL 1 YEAR)
                  WHERE id = ?", [$payment['rider_id']]);
        runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, 'Payment Confirmed', ?)",
            [$payment['rider_id'], 'Your permit payment of ' . formatCurrency($payment['amount']) . " was confirmed. Receipt $receipt."]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    $rider = fetchOne("SELECT r.full_name, r.email, r.expiry_date, r.city_id, u.email AS account_email, u.notify_email
        FROM riders r LEFT JOIN users u ON u.id = r.user_id WHERE r.id = ?", [$payment['rider_id']]);
    runQuery("INSERT INTO audit_logs (user_id,user_name_snapshot,role_snapshot,city_id,action,entity_type,entity_id,details,ip_address)
              VALUES (:uid,:name,:role,:city,'PAYMENT_CONFIRMED','payment',:pid,:details,:ip)", [
        'uid' => $_SESSION['user_id'] ?? null, 'name' => $_SESSION['name'] ?? $actorLabel, 'role' => $_SESSION['role'] ?? 'system',
        'city' => $payment['city_id'], 'pid' => (string) $paymentId,
        'details' => "{$payment['payment_method']} payment of {$payment['amount']} from {$rider['full_name']}, receipt $receipt", 'ip' => client_ip(),
    ]);

    $to = $rider['account_email'] ?: $rider['email'];
    if ($to && ($rider['notify_email'] === null || (int) $rider['notify_email'] === 1)) {
        send_template_email('payment_receipt', $to, [
            'name' => $rider['full_name'], 'receipt_number' => $receipt, 'amount' => formatCurrency($payment['amount']),
            'method' => $payment['payment_method'], 'fiscal_year' => $payment['fiscal_year'], 'expiry_date' => formatDate($rider['expiry_date']),
            'portal_url' => app_url('pages/rider/payment-history.php'),
        ]);
    }
    return true;
}

function payment_mark_failed(int $paymentId, string $reason = 'declined or cancelled'): void {
    if (runQuery("UPDATE payments SET status = 'Failed' WHERE id = ? AND status = 'Pending'", [$paymentId])->affected_rows !== 1) return;
    $p = fetchOne("SELECT p.amount, p.rider_id, r.full_name, r.email, u.email AS account_email, u.notify_email
        FROM payments p JOIN riders r ON r.id = p.rider_id LEFT JOIN users u ON u.id = r.user_id WHERE p.id = ?", [$paymentId]);
    if ($p) runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, 'Payment Failed', ?)",
        [$p['rider_id'], 'Your mobile-money payment of ' . formatCurrency($p['amount']) . " was not completed ($reason). No money was taken."]);
    $to = $p ? ($p['account_email'] ?: $p['email']) : null;
    if ($to && ($p['notify_email'] === null || (int) $p['notify_email'] === 1)) {
        send_template_email('payment_failed', $to, ['name' => $p['full_name'], 'amount' => formatCurrency($p['amount']), 'reason' => $reason,
            'portal_url' => app_url('pages/rider/renewal-status.php')]);
    }
}

/** Records a cash/bank payment taken by a chairperson and confirms it. Returns the receipt number. */
function record_manual_payment(array $rider, string $method, ?int $collectedBy): string {
    if (!in_array($method, PAYMENT_METHODS, true)) $method = 'Cash';
    runQuery("INSERT INTO payments (rider_id, city_id, amount, payment_method, receipt_number, status, fiscal_year, paid_at, collected_by)
              VALUES (?,?,?,?,?,'Pending',?,NOW(),?)",
        [$rider['id'], $rider['city_id'], (float) $rider['annual_tax'], $method, 'TMP-' . bin2hex(random_bytes(6)), current_fiscal_year(), $collectedBy]);
    $paymentId = (int) db()->insert_id;
    payment_confirm($paymentId, $_SESSION['name'] ?? 'Chairperson');
    return nextReceiptNumber($paymentId);
}
