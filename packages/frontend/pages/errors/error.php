<?php
// Branded error page for 403, 404, 419 (expired form), 500 and 503 (maintenance).
// Reached through app_error_page() or the .htaccess fallback for unknown URLs (?code=404).
// Text comes from Super Admin → Content → Settings (Errors); built-in defaults apply if the database is down.
if (!function_exists('menu_items')) {
    try { require_once __DIR__ . '/../../../backend/site.php'; }
    catch (Throwable $e) { error_log('BodaERP error page bootstrap: ' . $e->getMessage()); }
}
$status = (int) ($errorStatus ?? ($_GET['code'] ?? 404));
if (!in_array($status, [403, 404, 419, 500, 503], true)) $status = 404;
if (!headers_sent()) http_response_code($status === 419 ? 400 : $status);   // 419 is not a standard code; Apache would turn it into 500

$text = function (string $key, string $default): string {
    try { return function_exists('get_setting') ? get_setting($key, $default) : $default; }
    catch (Throwable $e) { error_log('BodaERP error page text ' . $key . ': ' . $e->getMessage()); return $default; }
};
[$title, $body] = match ($status) {
    403 => [$text('error_403_title', 'This page is not part of your account'), $text('error_403_text', 'Your role does not give access to this page.')],
    419 => ['This form has expired', $text('msg_form_expired', 'The page was open for a long time. Go back, reload it and submit again.')],
    500 => [$text('error_500_title', 'Something went wrong on our side'), $text('error_500_text', 'Please try again in a few minutes.')],
    503 => [$text('maintenance_title', 'BodaERP is being updated'), $text('maintenance_text', 'We will be back shortly.')],
    default => [$text('error_404_title', 'We could not find that page'), $text('error_404_text', 'The link may be old or mistyped.')],
};
$home = (defined('BASE_URL') ? BASE_URL : '') . '/';
$loggedIn = function_exists('is_logged_in') && is_logged_in();
$seo = ['title' => $title . ' | ' . $text('site_name', 'BodaERP'), 'description' => $body, 'noindex' => true];
$canRenderChrome = function_exists('menu_items') && $status !== 500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php if ($canRenderChrome): require __DIR__ . '/../../partials/public-head.php'; else: ?>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($home) ?>assets/images/favicon.ico" sizes="any">
    <link rel="stylesheet" href="<?= htmlspecialchars($home) ?>assets/vendor/inter/inter.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($home) ?>assets/css/public.css">
<?php endif; ?>
</head>
<body class="public">
<?php if ($canRenderChrome) require __DIR__ . '/../../partials/public-header.php'; ?>
<main id="main" class="error-wrap">
    <div class="error-card">
        <div class="error-code" aria-hidden="true"><?= $status === 503 ? '<i class="fas fa-screwdriver-wrench"></i>' : $status ?></div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($body) ?></p>
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
            <?php if ($status === 419): ?>
                <?php $back = (string) ($_SERVER['HTTP_REFERER'] ?? ''); $sameSite = $back !== '' && parse_url($back, PHP_URL_HOST) === ($_SERVER['SERVER_NAME'] ?? ''); ?>
                <a class="btn-brand" href="<?= htmlspecialchars($sameSite ? $back : $home) ?>">Go back and reload the form</a>
            <?php elseif ($status === 403 && $loggedIn): ?>
                <a class="btn-brand" href="<?= htmlspecialchars(BASE_URL . dashboard_url_for($_SESSION['role'])) ?>">Go to my dashboard</a>
            <?php elseif ($status !== 503): ?>
                <a class="btn-brand" href="<?= htmlspecialchars($home) ?>">Go to the home page</a>
            <?php endif; ?>
            <?php if ($status !== 503): ?><a class="btn-ghost" href="<?= htmlspecialchars($home) ?>contact.php">Contact support</a><?php endif; ?>
        </div>
    </div>
</main>
<?php if ($canRenderChrome) require __DIR__ . '/../../partials/public-footer.php'; ?>
</body>
</html>
