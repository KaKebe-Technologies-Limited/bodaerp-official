<?php
// National rider registry for government viewers. Read only, and limited to what enforcement needs to identify a
// rider and their permit: name, permit number, plate, city/stage, status and dates. NIN, phone, email, date of
// birth, address, next of kin, photo and the QR verification token are deliberately never selected here.
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['government', 'super_admin']);
$active = 'registry';

$search = trim((string) ($_GET['q'] ?? ''));
$filterCity = (string) ($_GET['city'] ?? '');
$filterStatus = (string) ($_GET['status'] ?? '');

$sql = "SELECT r.id, r.id_number, r.full_name, r.bike_plate, r.status, r.member_since, r.expiry_date,
        c.name AS city_name, s.name AS stage_name,
        (SELECT COUNT(*) FROM payments p WHERE p.rider_id = r.id AND p.status = 'Confirmed') AS payment_count,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.rider_id = r.id AND p.status = 'Confirmed') AS payment_total,
        (SELECT COUNT(*) FROM enforcement_actions e WHERE e.rider_id = r.id) AS enforcement_count
        FROM riders r
        LEFT JOIN cities c ON c.id = r.city_id
        LEFT JOIN stages s ON s.id = r.stage_id
        WHERE r.deleted_at IS NULL";
$params = [];
if ($search !== '') {
    $sql .= " AND (r.full_name LIKE ? OR r.bike_plate LIKE ? OR r.id_number LIKE ?)";
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($filterCity !== '' && ctype_digit($filterCity)) { $sql .= " AND r.city_id = ?"; $params[] = (int) $filterCity; }
if (in_array($filterStatus, ['active', 'expired', 'pending'], true)) { $sql .= " AND r.status = ?"; $params[] = $filterStatus; }
$sql .= " ORDER BY r.id DESC LIMIT 300";
$riders = fetchAll($sql, $params);

$cities = fetchAll("SELECT id, name FROM cities ORDER BY name");
$totalRidersAll   = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
$activeRidersAll  = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL AND status = 'active'");
$expiredRidersAll = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL AND status = 'expired'");
$flaggedRidersAll = (int) fetchValue("SELECT COUNT(DISTINCT rider_id) FROM enforcement_actions WHERE rider_id IS NOT NULL");
$statusTone = ['active' => 'success', 'expired' => 'danger', 'pending' => 'warning'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Rider registry | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-government.php'; ?>

<div class="main-content" id="mainContent">
    <?php
    $pageTitle = 'Rider registry';
    $pageSubtitle = 'Look up a rider by name, plate or permit number';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-users" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= number_format($totalRidersAll) ?></div><div class="stat-hero-label">Registered riders</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-user-check" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= number_format($activeRidersAll) ?></div><div class="stat-hero-label">Valid permit</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-red"><div class="stat-hero-icon"><i class="fas fa-user-clock" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= number_format($expiredRidersAll) ?></div><div class="stat-hero-label">Expired permit</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-flag" aria-hidden="true"></i></div><div><div class="stat-hero-value"><?= number_format($flaggedRidersAll) ?></div><div class="stat-hero-label">With enforcement records</div></div></div></div>
        </div>

        <form class="filter-bar mb-3 no-print" method="get" role="search" aria-label="Search the registry">
            <input type="search" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Name, plate or permit number" aria-label="Search riders">
            <select class="form-select" name="city" aria-label="City">
                <option value="">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= $filterCity === (string) $c['id'] ? ' selected' : '' ?>><?= h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status" aria-label="Permit status">
                <option value="">Any status</option>
                <option value="active"<?= $filterStatus === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="expired"<?= $filterStatus === 'expired' ? ' selected' : '' ?>>Expired</option>
                <option value="pending"<?= $filterStatus === 'pending' ? ' selected' : '' ?>>Pending</option>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php $exportTableId = 'registryTable'; $exportFilename = 'national_rider_registry'; $exportTitle = 'National rider registry'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
        </form>

        <div class="card">
            <div class="card-body">
                <p class="small text-muted mb-3"><i class="fas fa-lock me-1" aria-hidden="true"></i>Contact details, NIN and other personal data are held by the rider's city council and are not shown in the national registry.</p>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="registryTable">
                        <thead><tr><th>Rider</th><th>Permit number</th><th>Plate</th><th>City / stage</th><th>Status</th><th>Expiry</th><th class="no-export"><span class="visually-hidden">Details</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($riders as $r): ?>
                            <tr>
                                <td><?= person_cell(null, $r['full_name']) ?></td>
                                <td><code><?= h($r['id_number']) ?></code></td>
                                <td><?= h($r['bike_plate']) ?></td>
                                <td class="small"><?= h($r['city_name'] ?? '') ?><br><span class="text-muted"><?= h($r['stage_name'] ?? '') ?></span></td>
                                <td><span class="badge bg-<?= $statusTone[$r['status']] ?? 'secondary' ?>"><?= h(ucfirst((string) $r['status'])) ?></span></td>
                                <td class="small"><?= $r['expiry_date'] ? h(formatDate($r['expiry_date'])) : 'Not set' ?></td>
                                <td class="no-export">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-registry-rider="<?= (int) $r['id'] ?>" aria-label="Details for <?= h($r['full_name']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$riders): ?>
                        <div class="empty-state">
                            <i class="fas fa-id-card" aria-hidden="true"></i>
                            <h5>No riders match</h5>
                            <p>Check the spelling, or search by plate or permit number instead.</p>
                        </div>
                    <?php elseif (count($riders) === 300): ?>
                        <p class="small text-muted mb-0">Showing the 300 most recent matches. Narrow the search to see others.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="riderModal" tabindex="-1" aria-labelledby="riderModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-bold" id="riderModalTitle">Rider</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <dl class="row g-3 mb-0" id="riderModalFields"></dl>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script type="application/json" id="registryData"><?= js_json(array_column(array_map(fn($r) => [
    'id' => (int) $r['id'],
    'name' => $r['full_name'],
    'Permit number' => $r['id_number'],
    'Plate' => $r['bike_plate'],
    'City / stage' => trim(($r['city_name'] ?? '') . ' · ' . ($r['stage_name'] ?? ''), ' ·'),
    'Status' => ucfirst((string) $r['status']),
    'Registered' => $r['member_since'] ? formatDate($r['member_since']) : '',
    'Permit expires' => $r['expiry_date'] ? formatDate($r['expiry_date']) : '',
    'Confirmed payments' => (int) $r['payment_count'] . ' (UGX ' . number_format((float) $r['payment_total']) . ')',
    'Enforcement records' => (string) (int) $r['enforcement_count'],
], $riders), null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= BASE_URL ?>/assets/js/registry.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/registry.js') ?: 1 ?>"></script>
</body>
</html>
