<?php
/**
 * ioTec Pay Configuration — BodaERP
 * Same merchant account/credentials as ChamaFunds (shared ioTec Pay
 * client) — both apps collect into the same wallet.
 * Real credentials live in the untracked .env file at the project
 * root (see .env.example for the required keys) — never hardcode
 * them here again; this file is tracked in git.
 */

require_once __DIR__ . '/../config/config.php'; // for BASE_URL, below
require_once __DIR__ . '/env_loader.php';
loadEnvFile(__DIR__ . '/../.env');

// ── Environment ───────────────────────────────────────────────
// ioTec Pay uses a single API host for both modes; "sandbox" here
// just means we charge against the TEST wallet (paired with ioTec's
// documented magic test phone numbers) instead of the LIVE wallet.
// true = test wallet, false = live wallet (real money).
// Flip to false only once rider payments have been tested end-to-end.
define('IOTEC_SANDBOX', true);   // ← TEST MODE — no real money moves

// ── API Credentials (same ioTec Pay client as ChamaFunds) ──────
define('IOTEC_CLIENT_ID',     getenv('IOTEC_CLIENT_ID') ?: '');
define('IOTEC_CLIENT_SECRET', getenv('IOTEC_CLIENT_SECRET') ?: '');
define('IOTEC_GRANT_TYPE',    getenv('IOTEC_GRANT_TYPE') ?: 'client_credentials');

// ── Wallets ───────────────────────────────────────────────────
define('IOTEC_TEST_WALLET_ID', getenv('IOTEC_TEST_WALLET_ID') ?: '');
define('IOTEC_LIVE_WALLET_ID', getenv('IOTEC_LIVE_WALLET_ID') ?: '');
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
define('IOTEC_IPN_SECRET', getenv('IOTEC_IPN_SECRET') ?: '');

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
