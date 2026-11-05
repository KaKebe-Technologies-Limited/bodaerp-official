<?php
// For uptime monitors. Answers 200 when the database responds and the scheduler ran within a day, otherwise 503,
// and says nothing more because it is public. Pointing a free monitor here every few minutes also keeps the
// scheduled jobs running on a quiet site, since each visit gives them a chance to start.
define('BODAERP_BACKGROUND', true);
require_once __DIR__ . '/packages/backend/automation.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$healthy = false;
try {
    $lastRun = fetchValue("SELECT MAX(last_run_at) FROM automation_jobs");
    $healthy = $lastRun === null || strtotime((string) $lastRun) > time() - 86400;
} catch (Throwable $e) {
    error_log('BodaERP health check failed: ' . $e->getMessage());
}
http_response_code($healthy ? 200 : 503);
echo json_encode(['status' => $healthy ? 'ok' : 'degraded']);
automation_tick();
