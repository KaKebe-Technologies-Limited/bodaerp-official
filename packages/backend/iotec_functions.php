<?php
/**
 * ioTec Pay Helper Functions for BodaERP
 * Lets a rider pay their annual tax online via mobile money instead of
 * only a chairperson recording a manual cash/bank/momo payment. The
 * amount charged is always the rider's own `annual_tax` — a snapshot
 * of the city's admin-configurable `annual_fee` taken at registration
 * (see pages/superadmin/cities.php and pages/citycouncil/council-setting.php) —
 * never a value hardcoded here.
 */

require_once __DIR__ . '/iotec_config.php';

class IotecApiClient
{
    private $clientId;
    private $clientSecret;
    private $token;

    public function __construct($clientId, $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    private function request($method, $url, $body = null, $token = null, $isForm = false)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL is required for ioTec Pay payments.');
        }

        $headers = ['Accept: application/json'];
        $headers[] = $isForm ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $isForm ? http_build_query($body) : json_encode($body);
        }

        curl_setopt_array($ch, $curlOptions);
        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('ioTec Pay cURL error: ' . $curlError);
        }

        $decoded = json_decode($response);

        if ($httpCode >= 400) {
            $msg = $decoded->message ?? $decoded->error_description ?? $decoded->error ?? $response;
            throw new Exception('ioTec Pay HTTP ' . $httpCode . ': ' . $msg);
        }

        return $decoded ?: (object) ['raw' => $response];
    }

    public function getToken()
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $response = $this->request('POST', IOTEC_AUTH_URL, [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => IOTEC_GRANT_TYPE,
        ], null, true);

        if (empty($response->access_token)) {
            throw new Exception('ioTec Pay token request failed.');
        }
        $this->token = $response->access_token;
        return $this->token;
    }

    public function collect($payload)
    {
        $token = $this->getToken();
        return $this->request('POST', IOTEC_BASE_URL . '/api/collections/collect', $payload, $token);
    }

    public function getCollectionStatus($requestId)
    {
        $token = $this->getToken();
        return $this->request('GET', IOTEC_BASE_URL . '/api/collections/status/' . urlencode($requestId), null, $token);
    }
}

function initializeIotec() {
    return new IotecApiClient(IOTEC_CLIENT_ID, IOTEC_CLIENT_SECRET);
}

function generateOrderId() {
    return 'BODA_' . uniqid() . '_' . time();
}

/**
 * Normalize a Ugandan phone number to local MSISDN format (0XXXXXXXXX)
 * as expected by the ioTec Pay `payer` field.
 */
function normalizeUgPhone($phone) {
    $digits = preg_replace('/[^0-9]/', '', $phone ?? '');
    if (substr($digits, 0, 3) === '256' && strlen($digits) === 12) {
        return '0' . substr($digits, 3);
    }
    if (substr($digits, 0, 1) === '0' && strlen($digits) === 10) {
        return $digits;
    }
    if (strlen($digits) === 9) {
        return '0' . $digits;
    }
    return $digits;
}

/**
 * Initiate a rider's annual-tax payment via ioTec Pay mobile money
 * collection. Charges the rider's own `annual_tax` — never a hardcoded
 * or client-supplied amount — so the admin-configurable city fee is
 * always what's actually collected.
 * Returns ['success'=>true,'payment_id'=>N,'status'=>'pending'|'completed']
 * or      ['error' => 'message'].
 */
function initiateRiderPayment(int $riderId, string $phone) {
    $rider = fetchOne("SELECT * FROM riders WHERE id = ? AND deleted_at IS NULL", [$riderId]);
    if (!$rider) {
        return ['error' => 'Rider not found.'];
    }

    $amount = (float) $rider['annual_tax'];
    if ($amount <= 0) {
        return ['error' => 'No amount owed for this rider.'];
    }

    $orderId = generateOrderId();
    $payerPhone = normalizeUgPhone($phone);

    runQuery(
        "INSERT INTO payments (rider_id, city_id, amount, payment_method, receipt_number,
                                transaction_reference, payer_phone, status, fiscal_year, paid_at)
         VALUES (:rider_id, :city_id, :amount, 'Mobile Money', :receipt,
                 :ref, :phone, 'Pending', :fy, NOW())",
        [
            'rider_id' => $riderId,
            'city_id'  => $rider['city_id'],
            'amount'   => $amount,
            'receipt'  => 'TMP-' . uniqid(),
            'ref'      => $orderId,
            'phone'    => $payerPhone,
            'fy'       => CURRENT_FISCAL_YEAR,
        ]
    );
    $paymentId = (int) db()->insert_id;

    $payload = [
        'category'   => 'MobileMoney',
        'currency'   => IOTEC_DEFAULT_CURRENCY,
        'walletId'   => IOTEC_WALLET_ID,
        'externalId' => (string) $paymentId,
        'payer'      => $payerPhone,
        'payerName'  => substr($rider['full_name'], 0, 150),
        'payerNote'  => substr('BodaERP annual tax - ' . $rider['id_number'], 0, 100),
        'amount'     => $amount,
        'payeeNote'  => 'BodaERP rider payment #' . $paymentId,
    ];

    try {
        $iotec    = initializeIotec();
        $response = $iotec->collect($payload);

        $transactionId = $response->id ?? null;
        $status        = $response->status ?? 'Pending';

        if (empty($transactionId)) {
            $errMsg = $response->message ?? json_encode($response);
            runQuery("UPDATE payments SET status='Failed' WHERE id=?", [$paymentId]);
            return ['error' => 'ioTec Pay error: ' . $errMsg];
        }

        runQuery("UPDATE payments SET iotec_transaction_id=? WHERE id=?", [$transactionId, $paymentId]);

        if ($status === 'Success') {
            markPaymentCompleted($paymentId);
            return ['success' => true, 'payment_id' => $paymentId, 'status' => 'completed'];
        }

        if (in_array($status, ['Failed', 'RolledBack', 'Cancelled', 'Rejected'], true)) {
            runQuery("UPDATE payments SET status='Failed' WHERE id=? AND status='Pending'", [$paymentId]);
            return ['error' => 'Payment was not accepted by your mobile money provider.'];
        }

        return ['success' => true, 'payment_id' => $paymentId, 'status' => 'pending'];

    } catch (Exception $e) {
        runQuery("UPDATE payments SET status='Failed' WHERE id=? AND status='Pending'", [$paymentId]);
        return ['error' => 'Payment exception: ' . $e->getMessage()];
    }
}

/**
 * Mark a payment completed: assign its real receipt number, activate
 * the rider and extend their expiry a year, and notify them — the
 * same effects collect-payments.php produces for a manually-recorded
 * payment, so a self-service online payment looks identical everywhere
 * (dashboards, reports, defaulter lists) once it's Confirmed.
 * Shared by the collect() immediate-success path, the status poll, and
 * the reconcile sweep. Only ever acts on a still-Pending row.
 */
function markPaymentCompleted(int $paymentId): bool {
    $payment = fetchOne("SELECT * FROM payments WHERE id = ? AND status = 'Pending'", [$paymentId]);
    if (!$payment) return false;

    runQuery("UPDATE payments SET status='Confirmed' WHERE id=?", [$paymentId]);
    $receipt = nextReceiptNumber($paymentId);
    runQuery("UPDATE payments SET receipt_number=? WHERE id=?", [$receipt, $paymentId]);

    runQuery(
        "UPDATE riders SET status='active', expiry_date = DATE_ADD(CURDATE(), INTERVAL 365 DAY) WHERE id = ?",
        [$payment['rider_id']]
    );
    runQuery(
        "INSERT INTO notifications (rider_id,type,message) VALUES (?, 'Payment Confirmed', ?)",
        [$payment['rider_id'], 'Your annual tax payment has been confirmed. Thank you!']
    );

    runQuery(
        "INSERT INTO audit_logs (user_id,user_name_snapshot,role_snapshot,city_id,action,entity_type,entity_id,details,ip_address)
         VALUES (NULL,'System (ioTec Pay)','system',:city,'UPDATE','payment',:pid,:details,:ip)",
        [
            'city'    => $payment['city_id'],
            'pid'     => (string) $paymentId,
            'details' => "Rider self-paid {$payment['amount']} via ioTec mobile money, receipt {$receipt}",
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
        ]
    );

    return true;
}

function markPaymentFailed(int $paymentId): void {
    runQuery("UPDATE payments SET status='Failed' WHERE id=? AND status='Pending'", [$paymentId]);
}

/**
 * Verify a payment's current status by asking ioTec Pay directly.
 * Called from the rider-facing status-poll endpoint while a payment
 * is still Pending locally (see api/payments.php action=check_status).
 */
function verifyIotecPaymentTransaction(int $paymentId): array {
    $payment = fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
    if (!$payment) {
        return ['error' => 'Payment not found'];
    }
    if ($payment['status'] !== 'Pending') {
        return ['success' => true, 'status' => strtolower($payment['status'])];
    }
    if (empty($payment['iotec_transaction_id'])) {
        return ['success' => true, 'status' => 'pending'];
    }

    try {
        $iotec    = initializeIotec();
        $response = $iotec->getCollectionStatus($payment['iotec_transaction_id']);
        $status   = $response->status ?? 'Pending';

        if ($status === 'Success') {
            markPaymentCompleted($paymentId);
            return ['success' => true, 'status' => 'confirmed'];
        }
        if (in_array($status, ['Failed', 'RolledBack', 'Cancelled', 'Rejected'], true)) {
            markPaymentFailed($paymentId);
            return ['success' => true, 'status' => 'failed'];
        }
        return ['success' => true, 'status' => 'pending'];
    } catch (Exception $e) {
        // Network/API hiccup — leave as pending, the next poll or reconcile sweep will resolve it.
        return ['success' => true, 'status' => 'pending', 'error' => $e->getMessage()];
    }
}

/**
 * Sweep locally-pending payments and re-check each directly against
 * ioTec Pay's status API. Catches payments that actually succeeded (or
 * failed) but the rider closed their browser before the on-page poll
 * caught up. Safe to run repeatedly; only ever touches rows still
 * sitting at 'Pending'. Wired to a "Reconcile Pending" admin button.
 */
function reconcilePendingPayments(int $limit = 50): array {
    $rows = fetchAll(
        "SELECT id FROM payments
         WHERE status = 'Pending' AND iotec_transaction_id IS NOT NULL AND iotec_transaction_id != ''
         ORDER BY paid_at ASC LIMIT " . (int) $limit
    );
    $summary = ['checked' => 0, 'confirmed' => 0, 'failed' => 0, 'still_pending' => 0, 'errors' => 0];

    foreach ($rows as $row) {
        $summary['checked']++;
        $r = verifyIotecPaymentTransaction((int) $row['id']);
        if (!empty($r['error'])) {
            $summary['errors']++;
        } elseif (($r['status'] ?? '') === 'confirmed') {
            $summary['confirmed']++;
        } elseif (($r['status'] ?? '') === 'failed') {
            $summary['failed']++;
        } else {
            $summary['still_pending']++;
        }
    }
    return $summary;
}

/**
 * Verify that an incoming callback request really came from ioTec Pay.
 * See the note in iotec_config.php — this wallet's callback URL isn't
 * currently pointed at BodaERP, so this path is a ready-to-use fallback
 * rather than the primary confirmation mechanism.
 */
function verifyIotecIpnSecret(): bool {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $value) {
        if ($value === IOTEC_IPN_SECRET || $value === 'Bearer ' . IOTEC_IPN_SECRET) {
            return true;
        }
    }
    return false;
}

/**
 * Process an ioTec Pay callback (IPN) notification. Called by
 * ipn_handler.php when/if ioTec posts a transaction status update
 * for this wallet to BodaERP.
 */
function processIotecIpn(): array {
    if (!verifyIotecIpnSecret()) {
        return ['error' => 'Invalid or missing IPN secret'];
    }

    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw);
    if (!$payload) {
        return ['error' => 'Invalid IPN payload'];
    }

    $transactionId = $payload->id ?? null;
    $externalId    = $payload->externalId ?? null;
    $status        = $payload->status ?? '';

    $payment = null;
    if (!empty($transactionId)) {
        $payment = fetchOne("SELECT * FROM payments WHERE iotec_transaction_id = ? LIMIT 1", [$transactionId]);
    }
    if (!$payment && !empty($externalId) && ctype_digit((string) $externalId)) {
        $payment = fetchOne("SELECT * FROM payments WHERE id = ? LIMIT 1", [(int) $externalId]);
    }
    if (!$payment) {
        return ['error' => 'Payment not found for transaction: ' . $transactionId];
    }

    if ($status === 'Success') {
        markPaymentCompleted((int) $payment['id']);
    } elseif (in_array($status, ['Failed', 'RolledBack', 'Cancelled', 'Rejected'], true)) {
        markPaymentFailed((int) $payment['id']);
    }
    // Pending / SentToVendor / AwaitingApproval / Scheduled — leave as-is

    return ['success' => true, 'status' => $status];
}
