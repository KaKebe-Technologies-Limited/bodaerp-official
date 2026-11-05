<?php
// Public permit check. ?t=<token> (what the ID-card QR code opens) shows the photo and full name so an
// officer can match the card to the rider. Typing a permit number shows only the status and a masked name,
// because permit numbers are sequential and must not let anyone browse the rider register.
require_once __DIR__ . '/packages/backend/site.php';

$token = trim((string) ($_GET['t'] ?? ''));
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$rider = null; $full = false; $limited = false; $searched = $token !== '' || $code !== '';

if ($searched && !rate_limit('verify:' . client_ip(), 60, 600)) {
    $limited = true;
} elseif ($token !== '' && preg_match('/^[a-f0-9]{20}$/', $token)) {
    $rider = fetchOne("SELECT r.id_number, r.full_name, r.photo_path, r.status, r.expiry_date, r.member_since, r.bike_plate,
        s.name AS stage_name, c.name AS city_name
        FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id
        WHERE r.verify_token = ? AND r.deleted_at IS NULL", [$token]);
    $full = (bool) $rider;
} elseif ($code !== '' && preg_match('/^[A-Z0-9-]{4,40}$/', $code)) {
    $rider = fetchOne("SELECT r.id_number, r.full_name, r.status, r.expiry_date, s.name AS stage_name, c.name AS city_name
        FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id
        WHERE r.id_number = ? AND r.deleted_at IS NULL", [$code]);
}

$status = $rider['status'] ?? null;
if ($rider && $status === 'active' && $rider['expiry_date'] && $rider['expiry_date'] < date('Y-m-d')) $status = 'expired';
$statusMeta = [
    'active'  => ['Permit valid', '#15803d', 'fa-circle-check'],
    'expired' => ['Permit expired: not compliant', '#b91c1c', 'fa-circle-xmark'],
    'pending' => ['Registered, permit not yet paid', '#b45309', 'fa-clock'],
];
$maskName = function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return implode(' ', array_map(fn($p, $i) => $i === 0 ? $p : mb_substr($p, 0, 1) . '.', $parts, array_keys($parts)));
};
$seo = ['title' => 'Verify a boda-boda rider permit | ' . get_setting('site_name', 'BodaERP'),
    'description' => 'Check whether a boda-boda rider\'s BodaERP permit is valid by scanning the QR code on their ID card or entering the permit number.',
    'noindex' => $searched];
$currentPath = '/verify.php';
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?></head>
<body class="public">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>
<main id="main">
    <section class="page-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= BASE_URL ?>/">Home</a> / Verify a rider</nav>
            <h1>Verify a rider's permit</h1>
            <p>Scan the QR code on the rider's ID card with your phone camera, or type the permit number printed on the card.</p>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-5 order-lg-2">
                    <?php if ($limited): ?>
                        <div class="notice notice-error" role="alert">Too many checks from this connection. Wait ten minutes and try again.</div>
                    <?php elseif ($searched && !$rider): ?>
                        <div class="verify-card">
                            <div class="top">
                                <i class="fas fa-triangle-exclamation fa-3x text-warning mb-2" aria-hidden="true"></i>
                                <h2 class="h5 fw-bold">No permit found</h2>
                                <p class="text-muted mb-0">No rider matches this code. The number may be mistyped, or the card may be fake or withdrawn.</p>
                            </div>
                        </div>
                    <?php elseif ($rider): [$label, $color, $icon] = $statusMeta[$status] ?? $statusMeta['pending']; ?>
                        <div class="verify-card" aria-live="polite">
                            <div class="top">
                                <?php if ($full): ?><img class="photo mb-2" src="<?= h(media_url($rider['photo_path'])) ?>" alt="Photo of <?= h($rider['full_name']) ?>"><?php endif; ?>
                                <h2 class="h5 fw-bold mb-0"><?= h($full ? $rider['full_name'] : $maskName($rider['full_name'])) ?></h2>
                                <small class="text-muted"><?= h($rider['id_number']) ?></small>
                            </div>
                            <div class="state" style="background: <?= h($color) ?>"><i class="fas <?= h($icon) ?> me-1" aria-hidden="true"></i> <?= h($label) ?></div>
                            <dl class="rows mb-0">
                                <div><dt>City</dt><dd><?= h($rider['city_name'] ?? '') ?></dd></div>
                                <div><dt>Stage</dt><dd><?= h($rider['stage_name'] ?: 'Not assigned') ?></dd></div>
                                <?php if ($full): ?>
                                    <div><dt>Motorcycle plate</dt><dd><?= h($rider['bike_plate']) ?></dd></div>
                                    <div><dt>Member since</dt><dd><?= formatDate($rider['member_since'], 'M Y') ?></dd></div>
                                <?php endif; ?>
                                <div><dt>Valid until</dt><dd><?= formatDate($rider['expiry_date']) ?></dd></div>
                            </dl>
                            <div class="foot"><i class="fas fa-shield-halved me-1" aria-hidden="true"></i>Checked <?= date('j M Y, H:i') ?><?= $full ? '' : ' · scan the QR code to see the photo' ?></div>
                        </div>
                    <?php else: ?>
                        <div class="panel text-center">
                            <i class="fas fa-qrcode fa-3x mb-3" style="color: var(--brand)" aria-hidden="true"></i>
                            <p class="mb-0 text-muted">The result appears here. Scanning the card's QR code also shows the rider's photo, so you can check the card belongs to the person in front of you.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-5 order-lg-1">
                    <form class="panel" method="get" action="<?= BASE_URL ?>/verify.php">
                        <h2 class="mb-3">Enter a permit number</h2>
                        <div class="field">
                            <label for="code">Permit number</label>
                            <input id="code" name="code" required maxlength="40" pattern="[A-Za-z0-9-]{4,40}" placeholder="For example BODA-LIR-000123" value="<?= h($code) ?>" autocapitalize="characters">
                            <div class="hint">Printed under the rider's name on the ID card.</div>
                        </div>
                        <button class="btn-brand" type="submit"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Check permit</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
