<?php
require_once __DIR__ . '/../../includes/auth.php';
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
            $errors[] = 'Cannot delete this city — it still has stages, users, or riders attached to it.';
        }
    } else {
        $editId        = strtoupper(trim($_POST['edit_city_id'] ?? ''));
        $id            = strtoupper(trim($_POST['city_id'] ?? ''));
        $name          = trim($_POST['city_name'] ?? '');
        $country       = $_POST['country'] ?? 'Uganda';
        $currency      = $_POST['currency'] ?? 'UGX';
        $fee           = (float) ($_POST['annual_fee'] ?? 0);
        $logo          = trim($_POST['logo_path'] ?? '') ?: '/assets/images/logo.png';
        $email         = trim($_POST['contact_email'] ?? '');
        $phone         = trim($_POST['contact_phone'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $splitCity     = (int) ($_POST['split_city'] ?? 0);
        $splitAssoc    = (int) ($_POST['split_assoc'] ?? 0);
        $splitPlatform = (int) ($_POST['split_platform'] ?? 0);
        $gateway       = $_POST['payment_gateway'] ?? 'MTN MoMo';
        $fiscal        = trim($_POST['fiscal_year'] ?? '2026/2027');
        $target        = (int) ($_POST['compliance_target'] ?? 70);
        $status        = $_POST['status'] ?? 'pending';

        if (!preg_match('/^[A-Z]{3}$/', $id)) $errors[] = 'City code must be exactly 3 letters.';
        if ($name === '') $errors[] = 'City name is required.';
        if ($splitCity + $splitAssoc + $splitPlatform !== 100) $errors[] = 'Revenue splits must total 100%.';

        if (!$errors && $editId) {
            runQuery("UPDATE cities SET name=?,country=?,currency=?,annual_fee=?,logo_path=?,contact_email=?,contact_phone=?,address=?,
                      revenue_split_city=?,revenue_split_association=?,revenue_split_platform=?,payment_gateway=?,fiscal_year=?,compliance_target=?,status=?
                      WHERE id=?",
                [$name,$country,$currency,$fee,$logo,$email,$phone,$address,$splitCity,$splitAssoc,$splitPlatform,$gateway,$fiscal,$target,$status,$editId]);
            audit_log('UPDATE', 'city', $editId, 'City updated');
            redirect('/pages/superadmin/cities.php');
        } elseif (!$errors) {
            if (fetchValue("SELECT COUNT(*) FROM cities WHERE id = ?", [$id])) {
                $errors[] = 'That city code already exists.';
            } else {
                runQuery("INSERT INTO cities (id,name,country,currency,logo_path,annual_fee,fiscal_year,id_prefix,status,
                          contact_email,contact_phone,address,payment_gateway,sms_gateway,
                          revenue_split_city,revenue_split_association,revenue_split_platform,compliance_target,created_by)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,\"Africa's Talking\",?,?,?,?,?)",
                    [$id,$name,$country,$currency,$logo,$fee,$fiscal,'BODA-'.$id,$status,$email,$phone,$address,$gateway,
                     $splitCity,$splitAssoc,$splitPlatform,$target,$_SESSION['user_id']]);
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
    (SELECT COUNT(*) FROM riders r WHERE r.city_id = c.id) AS rider_count
    FROM cities c $where ORDER BY c.created_at", $qparams);
$citiesById = [];
foreach ($cities as $c) { $citiesById[$c['id']] = $c; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Manage Cities - BodaERP Platform'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .city-logo-preview { width:60px;height:60px;object-fit:contain;border-radius:8px;border:2px solid #e9ecef;background:#f8f9fa;padding:4px; }
        .split-total { font-weight:700; font-size:0.9rem; }
        .split-total.ok  { color:#198754; }
        .split-total.bad { color:#dc3545; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-superadmin.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Manage Cities</span></h5>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cityModal" onclick="openAddCity()">
                <i class="fas fa-plus me-2"></i>Add New City
            </button>
        </div>
    </header>

    <div class="content-area">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div>
        <?php endif; ?>
        <div class="card mb-3 no-print">
            <div class="card-body">
                <form class="row g-2 align-items-center" method="get">
                    <div class="col-md-4"><input type="text" class="form-control" name="q" value="<?= h($search) ?>" placeholder="Search by name or code..."></div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                            <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
                            <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
                    <div class="col-md-3 text-md-end">
                        <?php $exportTableId='citiesTable'; $exportFilename='cities'; $exportTitle='Cities Report'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="citiesTable">
                        <thead>
                            <tr><th>City</th><th>Annual Fee</th><th>Revenue Split (City / Assoc / Platform)</th><th>Stages</th><th>Riders</th><th>Status</th><th class="no-export">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cities as $t): $sc = ['active'=>'success','pending'=>'warning','inactive'=>'secondary']; ?>
                            <tr id="row-<?= h($t['id']) ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= h(BASE_URL . $t['logo_path']) ?>" width="32" height="32" class="rounded border" style="object-fit:contain;background:white;" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                                        <div><strong><?= h($t['name']) ?></strong><br><small class="text-muted"><?= h($t['id']) ?> · <?= h($t['country']) ?></small></div>
                                    </div>
                                </td>
                                <td><?= h($t['currency']) ?> <?= number_format($t['annual_fee']) ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= (int)$t['revenue_split_city'] ?>%</span>
                                    <span class="badge bg-success ms-1"><?= (int)$t['revenue_split_association'] ?>%</span>
                                    <span class="badge bg-info text-dark ms-1"><?= (int)$t['revenue_split_platform'] ?>%</span>
                                </td>
                                <td><?= (int) $t['stage_count'] ?></td>
                                <td><?= number_format($t['rider_count']) ?></td>
                                <td><span class="badge bg-<?= $sc[$t['status']] ?? 'secondary' ?>"><?= strtoupper($t['status']) ?></span></td>
                                <td class="no-export">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openEditCity('<?= h($t['id']) ?>')"><i class="fas fa-edit"></i></button>
                                    <a href="<?= BASE_URL ?>/pages/superadmin/users.php?city=<?= h($t['id']) ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-users"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this city? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= h($t['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cityModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <form method="post" id="cityForm">
                <?= csrf_field() ?>
                <input type="hidden" name="edit_city_id" id="editCityId">
                <div class="modal-header" style="background:linear-gradient(135deg,#0d6efd,#1a3a5c);color:white;">
                    <h5 class="modal-title fw-bold" id="cityModalTitle"><i class="fas fa-city me-2"></i>Add New City</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City Code <span class="text-danger">*</span> <small class="text-muted">(3 letters)</small></label>
                            <input type="text" class="form-control" name="city_id" id="cityId" placeholder="LIR" maxlength="3" style="text-transform:uppercase;" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">City Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city_name" id="cityName" placeholder="e.g. Lira City" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Country</label>
                            <select class="form-select" name="country" id="cityCountry">
                                <option value="Uganda">Uganda</option><option value="Kenya">Kenya</option><option value="Tanzania">Tanzania</option><option value="Rwanda">Rwanda</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency</label>
                            <select class="form-select" name="currency" id="cityCurrency">
                                <option value="UGX">UGX - Uganda Shilling</option><option value="KES">KES - Kenya Shilling</option><option value="TZS">TZS - Tanzania Shilling</option><option value="RWF">RWF - Rwanda Franc</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Annual Fee</label>
                            <input type="number" class="form-control" name="annual_fee" id="cityFee" value="50000">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Logo Path / URL</label>
                            <input type="text" class="form-control" name="logo_path" id="cityLogo" placeholder="/assets/images/lcc.png" oninput="document.getElementById('logoPreview').src=this.value">
                            <small class="text-muted">Upload to assets/images/ and enter the path here.</small>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <img id="logoPreview" src="<?= BASE_URL ?>/assets/images/logo.png" class="city-logo-preview" onerror="this.src='<?= BASE_URL ?>/assets/images/logo.png'">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Email</label>
                            <input type="email" class="form-control" name="contact_email" id="cityEmail" placeholder="council@city.go.ug">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Phone</label>
                            <input type="text" class="form-control" name="contact_phone" id="cityPhone" placeholder="+256 471 000 000">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control" name="address" id="cityAddress" placeholder="City Council Building, ...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Revenue Split <span class="text-danger">*</span> <small class="text-muted">— must total 100%</small></label>
                            <div class="row g-2 align-items-end">
                                <div class="col-4"><label class="small text-muted">City Council %</label><input type="number" class="form-control" name="split_city" id="splitCity" value="60" min="0" max="100" oninput="updateSplitTotal()"></div>
                                <div class="col-4"><label class="small text-muted">Boda Association %</label><input type="number" class="form-control" name="split_assoc" id="splitAssoc" value="26" min="0" max="100" oninput="updateSplitTotal()"></div>
                                <div class="col-4"><label class="small text-muted">Kakebe Tech (Platform) %</label><input type="number" class="form-control" name="split_platform" id="splitPlatform" value="14" min="0" max="100" oninput="updateSplitTotal()"></div>
                            </div>
                            <div class="mt-2">Total: <span id="splitTotal" class="split-total ok">100%</span></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Gateway</label>
                            <select class="form-select" name="payment_gateway" id="cityPayGW">
                                <option value="MTN MoMo">MTN MoMo</option><option value="Airtel Money">Airtel Money</option><option value="Both">Both</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fiscal Year</label>
                            <input type="text" class="form-control" name="fiscal_year" id="cityFiscal" value="2026/2027">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Compliance Target %</label>
                            <input type="number" class="form-control" name="compliance_target" id="cityCompliance" value="70" min="1" max="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" id="cityStatus">
                                <option value="active">Active</option><option value="pending">Pending</option><option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save City</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    const citiesData = <?= json_encode($citiesById) ?>;

    function updateSplitTotal() {
        const city  = parseFloat(document.getElementById('splitCity').value)||0;
        const assoc = parseFloat(document.getElementById('splitAssoc').value)||0;
        const plat  = parseFloat(document.getElementById('splitPlatform').value)||0;
        const total = city + assoc + plat;
        const el = document.getElementById('splitTotal');
        el.textContent = total + '%';
        el.className = 'split-total ' + (total === 100 ? 'ok' : 'bad');
    }

    document.getElementById('cityForm').addEventListener('submit', function(e) {
        const total = (parseFloat(document.getElementById('splitCity').value)||0) + (parseFloat(document.getElementById('splitAssoc').value)||0) + (parseFloat(document.getElementById('splitPlatform').value)||0);
        if (total !== 100) { e.preventDefault(); alert('Revenue splits must total 100%'); }
    });

    function openAddCity() {
        document.getElementById('cityModalTitle').innerHTML = '<i class="fas fa-city me-2"></i>Add New City';
        document.getElementById('editCityId').value = '';
        ['cityId','cityName','cityEmail','cityPhone','cityAddress'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('cityId').readOnly = false;
        document.getElementById('splitCity').value = 60;
        document.getElementById('splitAssoc').value = 26;
        document.getElementById('splitPlatform').value = 14;
        document.getElementById('cityFee').value = 50000;
        document.getElementById('cityCompliance').value = 70;
        document.getElementById('cityLogo').value = '';
        document.getElementById('logoPreview').src = '<?= BASE_URL ?>/assets/images/logo.png';
        updateSplitTotal();
    }

    function openEditCity(id) {
        const t = citiesData[id];
        if (!t) return;
        document.getElementById('cityModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit City: ' + t.name;
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
        document.getElementById('splitPlatform').value = t.revenue_split_platform;
        document.getElementById('cityPayGW').value = t.payment_gateway;
        document.getElementById('cityFiscal').value = t.fiscal_year;
        document.getElementById('cityCompliance').value = t.compliance_target;
        document.getElementById('cityStatus').value = t.status;
        updateSplitTotal();
        new bootstrap.Modal(document.getElementById('cityModal')).show();
    }

    <?php if ($errors): ?>new bootstrap.Modal(document.getElementById('cityModal')).show();<?php endif; ?>

    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
