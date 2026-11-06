<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        try {
            runQuery("DELETE FROM cities WHERE id = ?", [$id]);
            audit_log('DELETE', 'city', $id, 'City deleted');
            redirect('/pages/superadmin/cities.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Cannot delete this city: it still has stages, users, or riders attached to it.';
        }
    } else {
        $editId        = strtoupper(trim($_POST['edit_city_id'] ?? ''));
        $id            = strtoupper(trim($_POST['city_id'] ?? ''));
        $name          = trim($_POST['city_name'] ?? '');
        $country       = $_POST['country'] ?? 'Uganda';
        $currency      = $_POST['currency'] ?? 'UGX';
        $fee           = (float) ($_POST['annual_fee'] ?? 0);
        $logo          = trim($_POST['logo_path'] ?? '') ?: '/assets/images/logo.png';
        if (($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $up = store_upload($_FILES['logo_file'], 'cities', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 2 * 1024 * 1024);
            if ($up['ok']) $logo = $up['path'];
            else $errors[] = 'Logo: ' . $up['error'];
        }
        $email         = trim($_POST['contact_email'] ?? '');
        $phone         = trim($_POST['contact_phone'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $splitCity     = (int) ($_POST['split_city'] ?? 0);
        $splitAssoc    = (int) ($_POST['split_assoc'] ?? 0);
        $splitGov      = (int) ($_POST['split_government'] ?? 0);
        $splitPlatform = (int) ($_POST['split_platform'] ?? 0);
        $gateway       = in_array($_POST['payment_gateway'] ?? '', ['MTN MoMo', 'Airtel Money', 'Both'], true) ? $_POST['payment_gateway'] : 'Both';
        $fiscal        = CURRENT_FISCAL_YEAR;
        $target        = (int) ($_POST['compliance_target'] ?? 70);
        $status        = $_POST['status'] ?? 'pending';

        if (!preg_match('/^[A-Z]{3}$/', $id)) $errors[] = 'City code must be exactly 3 letters.';
        if ($name === '') $errors[] = 'City name is required.';
        if ($splitCity + $splitAssoc + $splitGov + $splitPlatform !== 100) $errors[] = 'Revenue splits must total 100%.';

        if (!$errors && $editId) {
            runQuery("UPDATE cities SET name=?,country=?,currency=?,annual_fee=?,logo_path=?,contact_email=?,contact_phone=?,address=?,
                      revenue_split_city=?,revenue_split_association=?,revenue_split_government=?,revenue_split_platform=?,payment_gateway=?,fiscal_year=?,compliance_target=?,status=?
                      WHERE id=?",
                [$name,$country,$currency,$fee,$logo,$email,$phone,$address,$splitCity,$splitAssoc,$splitGov,$splitPlatform,$gateway,$fiscal,$target,$status,$editId]);
            audit_log('UPDATE', 'city', $editId, 'City updated');
            redirect('/pages/superadmin/cities.php');
        } elseif (!$errors) {
            if (fetchValue("SELECT COUNT(*) FROM cities WHERE id = ?", [$id])) {
                $errors[] = 'That city code already exists.';
            } else {
                runQuery("INSERT INTO cities (id,name,country,currency,logo_path,annual_fee,fiscal_year,id_prefix,status,
                          contact_email,contact_phone,address,payment_gateway,sms_gateway,
                          revenue_split_city,revenue_split_association,revenue_split_government,revenue_split_platform,compliance_target,created_by)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\"Africa's Talking\",?,?,?,?,?,?)",
                    [$id,$name,$country,$currency,$logo,$fee,$fiscal,'BODA-'.$id,$status,$email,$phone,$address,$gateway,
                     $splitCity,$splitAssoc,$splitGov,$splitPlatform,$target,$_SESSION['user_id']]);
                audit_log('CREATE', 'city', $id, 'City created');
                redirect('/pages/superadmin/cities.php');
            }
        }
    }
}

$active = 'cities';
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1"; $qparams = [];
if ($search !== '') { $where .= " AND (c.name LIKE :q1 OR c.id LIKE :q2)"; $qparams['q1'] = $qparams['q2'] = "%$search%"; }
if ($statusFilter !== '') { $where .= " AND c.status = :status"; $qparams['status'] = $statusFilter; }

$cities = fetchAll("SELECT c.*,
    (SELECT COUNT(*) FROM stages s WHERE s.city_id = c.id) AS stage_count,
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id AND r.deleted_at IS NULL) AS rider_count
    FROM cities c $where ORDER BY c.created_at", $qparams);
$citiesById = [];
foreach ($cities as $c) { $citiesById[$c['id']] = $c; }

$totalCitiesAll  = (int) fetchValue("SELECT COUNT(*) FROM cities");
$activeCitiesAll = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'active'");
$pendingCitiesAll = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'pending'");
$totalRidersAll  = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
?>
<?php $splitDefaults = fetchOne("SELECT default_split_city, default_split_association, default_split_government, default_split_platform FROM platform_settings WHERE id = 1") ?? []; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Manage cities | ' . get_setting('site_name', 'BodaERP'); require __DIR__ . '/../../partials/head-assets.php'; ?>
    <style>
        .city-logo-preview { width:60px;height:60px;object-fit:contain;border-radius:12px;border:2px solid var(--light-gray);background:var(--off-white);padding:4px; }
        .split-total { font-weight:700; font-size:0.9rem; }
        .split-total.ok  { color:#198754; }
        .split-total.bad { color:#dc3545; }
        .city-cell-logo { width:38px;height:38px;object-fit:contain;background:var(--pure-white);border-radius:10px;padding:3px; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <?php ob_start(); ?>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cityModal" data-call="openAddCity">Add a city</button>
    <?php $headerActions = ob_get_clean();
    $pageTitle = 'Cities';
    $pageSubtitle = 'Add cities and set their fees, ID prefix and branding';
    require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div>
        <?php endif; ?>

        <div class="row g-3 row-cols-2 row-cols-xl-4 mb-4">
            <div class="col"><div class="stat-hero-card tone-blue"><div class="stat-hero-icon"><i class="fas fa-city"></i></div><div><div class="stat-hero-value"><?= $totalCitiesAll ?></div><div class="stat-hero-label">Total cities</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-green"><div class="stat-hero-icon"><i class="fas fa-check-circle"></i></div><div><div class="stat-hero-value"><?= $activeCitiesAll ?></div><div class="stat-hero-label">Active</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-gold"><div class="stat-hero-icon"><i class="fas fa-hourglass-half"></i></div><div><div class="stat-hero-value"><?= $pendingCitiesAll ?></div><div class="stat-hero-label">Pending</div></div></div></div>
            <div class="col"><div class="stat-hero-card tone-dark"><div class="stat-hero-icon"><i class="fas fa-users"></i></div><div><div class="stat-hero-value"><?= number_format($totalRidersAll) ?></div><div class="stat-hero-label">Platform riders</div></div></div></div>
        </div>

        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center" method="get">
                    <div class="col-md-4"><input aria-label="Search" type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by name or code..."></div>
                    <div class="col-md-3">
                        <select aria-label="Filter by status" class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-3 text-md-end">
                        <?php $exportTableId='citiesTable'; $exportFilename='cities'; $exportTitle='Cities Report'; require __DIR__ . '/../../partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="citiesTable">
                        <thead>
                            <tr><th>City</th><th>Annual fee</th><th>Revenue split (city / assoc / gov / platform)</th><th>Stages</th><th>Riders</th><th>Status</th><th class="no-export">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cities as $t): $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <tr id="row-<?= h($t['id']) ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?= person_avatar($t['logo_path'] ?: null, $t['name'], 'is-square') ?>
                                        <div><strong><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?> · <?= h($t['country']) ?></small></div>
                                    </div>
                                </td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= (int)$t['revenue_split_city'] ?>%</span>
                                    <span class="badge bg-success ms-1"><?= (int)$t['revenue_split_association'] ?>%</span>
                                    <span class="badge ms-1" style="background:#b45309;"><?= (int)$t['revenue_split_government'] ?>%</span>
                                    <span class="badge bg-info text-dark ms-1"><?= (int)$t['revenue_split_platform'] ?>%</span>
                                </td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= h(ucfirst(str_replace('_', ' ', $t['status']))) ?></span></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-primary" data-call="openEditCity" data-call-args="<?= h(js_json([(string) $t['id']])) ?>" aria-label="Edit <?= h($t['name']) ?>" title="Edit"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                    <a href="<?= BASE_URL ?>/pages/superadmin/users.php?city=<?= h($t['id']) ?>" class="btn btn-sm btn-outline-success" aria-label="Users in <?= h($t['name']) ?>" title="Users"><i class="fas fa-users" aria-hidden="true"></i></a>
                                    <form method="post" class="d-inline" data-confirm="Delete <?= h($t['name']) ?> with its <?= (int) $t['stage_count'] ?> stages and <?= number_format($t['rider_count']) ?> riders? This cannot be undone." data-confirm-title="Delete a city" data-confirm-ok="Delete city" data-confirm-typed="<?= h($t['name']) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete <?= h($t['name']) ?>" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$cities): ?>
                    <div class="empty-state">
                        <i class="fas fa-city"></i>
                        <h5>No cities found</h5>
                        <p>No city matches. Clear the search, or add a city.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cityModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <form method="post" id="cityForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_city_id" id="editCityId">
                <div class="modal-header" style="background:var(--primary-blue);color:white;border-bottom:none;">
                    <h5 class="modal-title fw-bold" id="cityModalTitle">Add new city</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="cityId" class="form-label fw-semibold">City code <span class="text-danger">*</span> <small class="text-muted">(3 letters)</small></label>
                            <input type="text" class="form-control" name="city_id" id="cityId" placeholder="LIR" maxlength="3" style="text-transform:uppercase;" required>
                        </div>
                        <div class="col-md-8">
                            <label for="cityName" class="form-label fw-semibold">City name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city_name" id="cityName" placeholder="e.g. Lira City" required>
                        </div>
                        <div class="col-md-4">
                            <label for="cityCountry" class="form-label fw-semibold">Country</label>
                            <select class="form-select" name="country" id="cityCountry">
                                <option value="Uganda">Uganda</option><option value="Kenya">Kenya</option><option value="Tanzania">Tanzania</option><option value="Rwanda">Rwanda</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="cityCurrency" class="form-label fw-semibold">Currency</label>
                            <select class="form-select" name="currency" id="cityCurrency">
                                <option value="UGX">UGX - Uganda shilling</option><option value="KES">KES - Kenya shilling</option><option value="TZS">TZS - Tanzania shilling</option><option value="RWF">RWF - Rwanda franc</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="cityFee" class="form-label fw-semibold">Annual fee</label>
                            <input type="number" class="form-control" name="annual_fee" id="cityFee" value="50000">
                        </div>
                        <div class="col-md-8">
                            <label for="cityLogoFile" class="form-label fw-semibold">City logo</label>
                            <input type="file" name="logo_file" id="cityLogoFile" accept="image/png,image/jpeg,image/webp" data-max-mb="2">
                            <input type="hidden" name="logo_path" id="cityLogo">
                            <small class="text-muted">PNG, JPG or WebP up to 2 MB. Shown beside the city in lists and on its riders' ID cards. Leave empty to keep the current logo.</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <img id="logoPreview" alt="Logo preview" src="<?= BASE_URL ?>/assets/images/logo.png" class="city-logo-preview" data-fallback-src="<?= BASE_URL ?>/assets/images/logo-mark.png">
                        </div>
                        <div class="col-md-6">
                            <label for="cityEmail" class="form-label fw-semibold">Contact email</label>
                            <input type="email" class="form-control" name="contact_email" id="cityEmail" placeholder="city@bodaerp.site">
                        </div>
                        <div class="col-md-6">
                            <label for="cityPhone" class="form-label fw-semibold">Contact phone</label>
                            <input type="text" class="form-control" name="contact_phone" id="cityPhone" placeholder="+256 471 000 000">
                        </div>
                        <div class="col-12">
                            <label for="cityAddress" class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address" id="cityAddress" placeholder="City Council Building, ...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Revenue split <span class="text-danger">*</span> <small class="text-muted">– must total 100%</small></label>
                            <div class="row g-2 align-items-end">
                                <div class="col-3"><label for="splitCity" class="small text-muted">City council %</label><input type="number" class="form-control" name="split_city" id="splitCity" value="55" min="0" max="100" data-call-input="updateSplitTotal"></div>
                                <div class="col-3"><label for="splitAssoc" class="small text-muted">Boda association %</label><input type="number" class="form-control" name="split_assoc" id="splitAssoc" value="26" min="0" max="100" data-call-input="updateSplitTotal"></div>
                                <div class="col-3"><label for="splitGovernment" class="small text-muted">Government %</label><input type="number" class="form-control" name="split_government" id="splitGovernment" value="10" min="0" max="100" data-call-input="updateSplitTotal"></div>
                                <div class="col-3"><label for="splitPlatform" class="small text-muted">Platform %</label><input type="number" class="form-control" name="split_platform" id="splitPlatform" value="9" min="0" max="100" data-call-input="updateSplitTotal"></div>
                            </div>
                            <div class="mt-2">Total: <span id="splitTotal" class="split-total ok">100%</span></div>
                        </div>
                        <div class="col-md-6">
                            <label for="cityPayGW" class="form-label fw-semibold">Mobile money accepted</label>
                            <select class="form-select" name="payment_gateway" id="cityPayGW">
                                <option value="Both">MTN and Airtel</option><option value="MTN MoMo">MTN Mobile Money only</option><option value="Airtel Money">Airtel Money only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <span class="form-label fw-semibold d-block">Permit year</span>
                            <p class="mb-0"><?= h(CURRENT_FISCAL_YEAR) ?> <span class="text-muted small">(July to June, set automatically)</span></p>
                        </div>
                        <div class="col-md-6">
                            <label for="cityCompliance" class="form-label fw-semibold">Compliance target %</label>
                            <input type="number" class="form-control" name="compliance_target" id="cityCompliance" value="70" min="1" max="100">
                        </div>
                        <div class="col-md-6">
                            <label for="cityStatus" class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="cityStatus">
                                <option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save city</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
<script nonce="<?= csp_nonce() ?>">
    const citiesData = <?= js_json($citiesById) ?>;

    function updateSplitTotal() {
        const city  = parseFloat(document.getElementById('splitCity').value)||0;
        const assoc = parseFloat(document.getElementById('splitAssoc').value)||0;
        const gov   = parseFloat(document.getElementById('splitGovernment').value)||0;
        const plat  = parseFloat(document.getElementById('splitPlatform').value)||0;
        const total = city + assoc + gov + plat;
        const el = document.getElementById('splitTotal');
        el.textContent = total + '%';
        el.className = 'split-total ' + (total === 100 ? 'ok' : 'bad');
    }

    document.getElementById('cityForm').addEventListener('submit', function(e) {
        const total = (parseFloat(document.getElementById('splitCity').value)||0) + (parseFloat(document.getElementById('splitAssoc').value)||0) + (parseFloat(document.getElementById('splitGovernment').value)||0) + (parseFloat(document.getElementById('splitPlatform').value)||0);
        if (total !== 100) { e.preventDefault(); showToast('Revenue splits must total 100%', 'error'); }
    });

    function openAddCity() {
        document.getElementById('cityModalTitle').innerHTML = '<i class="fas fa-city me-2"></i>Add New City';
        document.getElementById('editCityId').value = '';
        ['cityId','cityName','cityEmail','cityPhone','cityAddress'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cityId').readOnly = false;
        document.getElementById('splitCity').value = <?= (int) ($splitDefaults['default_split_city'] ?? 0) ?>;
        document.getElementById('splitAssoc').value = <?= (int) ($splitDefaults['default_split_association'] ?? 0) ?>;
        document.getElementById('splitGovernment').value = <?= (int) ($splitDefaults['default_split_government'] ?? 0) ?>;
        document.getElementById('splitPlatform').value = <?= (int) ($splitDefaults['default_split_platform'] ?? 0) ?>;
        document.getElementById('cityFee').value = 50000;
        document.getElementById('cityCompliance').value = 70;
        document.getElementById('cityLogo').value = '';
        document.getElementById('logoPreview').src = '<?= BASE_URL ?>/assets/images/logo.png';
        updateSplitTotal();
    }

    function openEditCity(id) {
        const t = citiesData[id];
        if (!t) return;
        const cityTitle = document.getElementById('cityModalTitle');
        cityTitle.innerHTML = '<i class="fas fa-edit me-2"></i>';
        cityTitle.append('Edit City: ' + t.name);
        document.getElementById('editCityId').value = t.id;
        document.getElementById('cityId').value = t.id;
        document.getElementById('cityId').readOnly = true;
        document.getElementById('cityName').value = t.name;
        document.getElementById('cityCountry').value = t.country;
        document.getElementById('cityCurrency').value = t.currency;
        document.getElementById('cityFee').value = t.annual_fee;
        document.getElementById('cityLogo').value = t.logo_path;
        document.getElementById('logoPreview').src = '<?= BASE_URL ?>' + t.logo_path;
        document.getElementById('cityEmail').value = t.contact_email || '';
        document.getElementById('cityPhone').value = t.contact_phone || '';
        document.getElementById('cityAddress').value = t.address || '';
        document.getElementById('splitCity').value = t.revenue_split_city;
        document.getElementById('splitAssoc').value = t.revenue_split_association;
        document.getElementById('splitGovernment').value = t.revenue_split_government;
        document.getElementById('splitPlatform').value = t.revenue_split_platform;
        document.getElementById('cityPayGW').value = t.payment_gateway;
        document.getElementById('cityCompliance').value = t.compliance_target;
        document.getElementById('cityStatus').value = t.status;
        updateSplitTotal();
        new bootstrap.Modal(document.getElementById('cityModal')).show();
    }

    <?php if ($errors): ?>new bootstrap.Modal(document.getElementById('cityModal')).show();<?php endif; ?>

</script>
</body>
</html>
