<?php
/**
 * ioTec Pay Configuration — BodaERP
 * Same merchant account/credentials as ChamaFunds (shared ioTec Pay
 * client) — both apps collect into the same wallet.
 * Keep this file secure - DO NOT commit to public repositories
 */

require_once __DIR__ . '/../config/config.php'; // for BASE_URL, below

// ── Environment ───────────────────────────────────────────────
// ioTec Pay uses a single API host for both modes; "sandbox" here
// just means we charge against the TEST wallet (paired with ioTec's
// documented magic test phone numbers) instead of the LIVE wallet.
// true = test wallet, false = live wallet (real money).
// Flip to false only once rider payments have been tested end-to-end.
define('IOTEC_SANDBOX', true);   // ← TEST MODE — no real money moves

// ── API Credentials (same ioTec Pay client as ChamaFunds) ──────
define('IOTEC_CLIENT_ID',     'pay-019f314e-f9f1-70a0-b089-06f53b92df21');
define('IOTEC_CLIENT_SECRET', 'IO-87xRMPjXj99LezKzP884sJED8cnDQUynS');
define('IOTEC_GRANT_TYPE',    'client_credentials');

// ── Wallets ───────────────────────────────────────────────────
define('IOTEC_TEST_WALLET_ID', '019f314e-fa12-764a-b7a5-be6c5938974d');
define('IOTEC_LIVE_WALLET_ID', '019f37d2-82a0-721e-8d72-7fd11d81368a');
define('IOTEC_WALLET_ID', IOTEC_SANDBOX ? IOTEC_TEST_WALLET_ID : IOTEC_LIVE_WALLET_ID);

// ── IPN / Callback verification ─────────────────────────────────
// NOTE: ioTec Pay callback URLs are configured per-WALLET in the
// merchant portal, not per-request. This wallet's callback URL is
// currently pointed at ChamaFunds' ipn_handler.php, so ioTec will NOT
// push webhooks here unless that's reconfigured (or a separate wallet
// is created for BodaERP). Rider payment confirmation therefore relies
// on active status polling (see verifyIotecPaymentTransaction() /
// reconcilePendingPayments() in iotec_functions.php), not this IPN
// secret — ipn_handler.php exists here as a ready-to-use fallback for
// whenever the callback URL question gets resolved.
define('IOTEC_IPN_SECRET', '2ta6cfziH7W54kgDFGhUmZRq8esTXMw9SEBvLQyb');

// ── API Endpoints ─────────────────────────────────────────────
define('IOTEC_AUTH_URL', 'https://id.iotec.io/connect/token');
define('IOTEC_BASE_URL', 'https://pay.iotec.io');

// ── Currency ──────────────────────────────────────────────────
// ioTec Pay only settles in ITX, UGX or USD.
define('IOTEC_DEFAULT_CURRENCY', 'UGX');

// ── Public Base URL (for reference / portal callback setup) ──
if (!defined('IOTEC_PUBLIC_BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'bodaerp.site';
    define('IOTEC_PUBLIC_BASE_URL', $scheme . '://' . $host);
}
define('IOTEC_IPN_URL', IOTEC_PUBLIC_BASE_URL . BASE_URL . '/ipn_handler.php');
