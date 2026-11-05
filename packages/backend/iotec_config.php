<?php
/**
 * ioTec Pay Configuration: BodaERP
 * Same merchant account/credentials as ChamaFunds (shared ioTec Pay
 * client): both apps collect into the same wallet.
 * Real credentials live in the untracked .env file at the project
 * root (see .env.example for the required keys): never hardcode
 * them here again; this file is tracked in git.
 */

require_once __DIR__ . '/config/config.php'; // for BASE_URL, below
require_once __DIR__ . '/env_loader.php';
loadEnvFile(__DIR__ . '/../../.env');

// ioTec Pay uses one API host for both modes; "test" charges the TEST wallet (paired with ioTec's
// documented test phone numbers), "live" charges the LIVE wallet with real money.
// Set IOTEC_MODE=live in the server's .env only after an end-to-end test. Anything else means test.
define('IOTEC_SANDBOX', strtolower((string) env('IOTEC_MODE', 'test')) !== 'live');

// API Credentials (same ioTec Pay client as ChamaFunds)
define('IOTEC_CLIENT_ID',     getenv('IOTEC_CLIENT_ID') ?: '');
define('IOTEC_CLIENT_SECRET', getenv('IOTEC_CLIENT_SECRET') ?: '');
define('IOTEC_GRANT_TYPE',    getenv('IOTEC_GRANT_TYPE') ?: 'client_credentials');

define('IOTEC_TEST_WALLET_ID', getenv('IOTEC_TEST_WALLET_ID') ?: '');
define('IOTEC_LIVE_WALLET_ID', getenv('IOTEC_LIVE_WALLET_ID') ?: '');
define('IOTEC_WALLET_ID', IOTEC_SANDBOX ? IOTEC_TEST_WALLET_ID : IOTEC_LIVE_WALLET_ID);

// NOTE: ioTec Pay callback URLs are configured per-WALLET in the
// merchant portal, not per-request. This wallet's callback URL is
// currently pointed at ChamaFunds' ipn_handler.php, so ioTec will NOT
// push webhooks here unless that's reconfigured (or a separate wallet
// is created for BodaERP). Rider payment confirmation therefore relies
// on active status polling (see verifyIotecPaymentTransaction() /
// reconcilePendingPayments() in iotec_functions.php), not this IPN
// secret: ipn_handler.php exists here as a ready-to-use fallback for
// whenever the callback URL question gets resolved.
define('IOTEC_IPN_SECRET', $_ENV['IOTEC_IPN_SECRET'] ?? (getenv('IOTEC_IPN_SECRET') ?: ''));
define('IOTEC_IPN_HEADER', trim((string) ($_ENV['IOTEC_IPN_HEADER'] ?? (getenv('IOTEC_IPN_HEADER') ?: ''))));

define('IOTEC_AUTH_URL', 'https://id.iotec.io/connect/token');
define('IOTEC_BASE_URL', 'https://pay.iotec.io');

// ioTec Pay only settles in ITX, UGX or USD.
define('IOTEC_DEFAULT_CURRENCY', 'UGX');

// Public Base URL (for reference / portal callback setup)
if (!defined('IOTEC_PUBLIC_BASE_URL')) {
    // app_url() prefers APP_URL and never trusts a Host header that differs from the server name.
    define('IOTEC_PUBLIC_BASE_URL', rtrim(app_url(), '/'));
}
define('IOTEC_IPN_URL', IOTEC_PUBLIC_BASE_URL . '/ipn_handler.php');
