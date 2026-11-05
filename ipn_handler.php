<?php
/**
 * ioTec Pay callback (IPN). ioTec posts a status update here when a collection changes.
 * The request must carry IOTEC_IPN_SECRET in a header; even then the payment outcome is
 * re-read from ioTec's API before anything changes (see processIotecIpn()).
 * Note: the ioTec wallet's callback URL must point at <site>/ipn_handler.php for this to be used;
 * until then payments are confirmed by status polling and the reconcile sweep.
 */
require_once __DIR__ . '/packages/backend/functions.php';
require_once __DIR__ . '/packages/backend/iotec_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }

$raw = (string) file_get_contents('php://input', false, null, 0, 65536);
$payload = json_decode($raw, true);
app_log('ipn', 'callback received', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'id' => $payload['id'] ?? null,
    'externalId' => $payload['externalId'] ?? null, 'status' => $payload['status'] ?? null]);

$result = processIotecIpn($raw);
app_log('ipn', 'callback processed', $result);

http_response_code(isset($result['error']) && str_contains($result['error'], 'secret') ? 403 : 200);
header('Content-Type: text/plain');
echo isset($result['error']) ? 'REJECTED' : 'OK';
