<?php
/**
 * IPN Handler — BodaERP
 * ioTec Pay posts payment status updates here in the background
 * (configured per-wallet in the ioTec Pay portal under Callback URLs).
 * This endpoint must respond with HTTP 200 quickly.
 *
 * NOTE: this ioTec wallet's callback URL is currently pointed at
 * ChamaFunds, not here — see the note in includes/iotec_config.php.
 * Rider payment confirmation works today via active status polling
 * (api/payments.php action=check_status / reconcile) regardless of
 * whether this endpoint ever receives a real callback. This file is a
 * ready-to-use fallback for whenever the callback URL is reconfigured
 * (or a separate wallet is created for BodaERP).
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/iotec_functions.php';

function logIpn($data) {
    $logFile = __DIR__ . '/ipn_logs.txt';
    $entry   = '[' . date('Y-m-d H:i:s') . '] ' . print_r($data, true) . "\n---\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

logIpn(['request' => $_REQUEST, 'input' => file_get_contents('php://input')]);

$result = processIotecIpn();

logIpn(['result' => $result]);

http_response_code(200);
echo 'OK';
exit;
