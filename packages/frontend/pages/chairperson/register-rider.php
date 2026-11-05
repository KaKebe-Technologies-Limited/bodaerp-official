<?php
require_once __DIR__ . '/../../../backend/permits.php';
require_once __DIR__ . '/../../../backend/accounts.php';
require_once __DIR__ . '/../../../backend/uploads.php';
require_role(['chairperson']);
$active = 'register-rider';
$stageId = $_SESSION['stage_id'];
$cityId = $_SESSION['city_id'];

$stage = fetchOne("SELECT * FROM stages WHERE id = ?", [$stageId]);
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$cityId]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fullName = strtoupper(trim($_POST['full_name'] ?? ''));
    $nin = trim($_POST['nin'] ?? '');
    $dob = $_POST['dob'] ?: null;
    $gender = $_POST['gender'] ?: null;
    $marital = $_POST['marital_status'] ?: null;
    $ugPhone = fn(string $raw): string => '+256' . preg_replace('/^(256|0)/', '', preg_replace('/\D/', '', $raw));
    $phone = $ugPhone((string) ($_POST['phone'] ?? ''));
    $email = mb_strtolower(trim($_POST['email'] ?? '')) ?: null;
    $address = trim($_POST['address'] ?? '');
    $nokName = trim($_POST['nok_name'] ?? '');
    $nokPhone = trim((string) ($_POST['nok_phone'] ?? '')) !== '' ? $ugPhone((string) $_POST['nok_phone']) : null;
    $plate = strtoupper(trim($_POST['bike_plate'] ?? ''));
    $model = trim($_POST['bike_model'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $paymentStatus = $_POST['payment_status'] ?? 'Unpaid';

    if ($phone !== '+256' && !preg_match('/^\+2567\d{8}$/', $phone)) $errors[] = 'Enter a Ugandan mobile number, e.g. 772 123456.';
    if ($nin !== '' && !preg_match('/^[A-Z0-9]{14}$/i', $nin)) $errors[] = 'A National ID Number (NIN) has 14 letters and digits.';
    if ($fullName === '' || $nin === '' || $phone === '+256' || $plate === '') {
        $errors[] = 'Full name, NIN, phone, and bike plate are required.';
    }
    if (!$errors && fetchValue("SELECT COUNT(*) FROM riders WHERE nin = ?", [$nin])) {
        $errors[] = 'A rider with this NIN is already registered.';
    }

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'The email address is not valid.';
    if (!in_array($paymentStatus, ['Paid', 'Unpaid'], true)) $paymentStatus = 'Unpaid';
    if ($dob !== null && (!strtotime($dob) || $dob > date('Y-m-d', strtotime('-16 years')))) $errors[] = 'Riders must be at least 16 years old; check the date of birth.';
    if ($plate !== '' && fetchValue("SELECT COUNT(*) FROM riders WHERE bike_plate = ? AND deleted_at IS NULL", [$plate])) $errors[] = 'A rider with plate ' . $plate . ' is already registered.';

    $photoPath = null;
    if (!$errors && !empty($_FILES['photo']['name'])) {
        $up = store_upload($_FILES['photo'], 'riders', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 2 * 1024 * 1024);
        if ($up['ok']) $photoPath = $up['path'];
        else $errors[] = 'Photo: ' . $up['error'];
    }

    if (!$errors) {
        runQuery("INSERT INTO riders (city_id,stage_id,id_number,verify_token,full_name,nin,date_of_birth,gender,marital_status,phone,email,
                  physical_address,next_of_kin_name,next_of_kin_contact,bike_plate,bike_model,route,photo_path,status,member_since,
                  expiry_date,annual_tax,created_by)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',CURDATE(),NULL,?,?)",
            [$cityId,$stageId,'TMP-'.bin2hex(random_bytes(6)),bin2hex(random_bytes(10)),$fullName,$nin,$dob,$gender,$marital,$phone,$email,$address,$nokName,$nokPhone,
             $plate,$model,$route,$photoPath,$city['annual_fee'],$_SESSION['user_id']]);
        $riderId = (int) db()->insert_id;
        $idNumber = nextIdNumber($riderId, $cityId);
        runQuery("UPDATE riders SET id_number = ? WHERE id = ?", [$idNumber, $riderId]);
        audit_log('CREATE', 'rider', $idNumber, "Registered rider: $fullName");

        $rider = fetchOne("SELECT * FROM riders WHERE id = ?", [$riderId]);
        if ($paymentStatus === 'Paid' && (float) $rider['annual_tax'] > 0) {
            $receipt = record_manual_payment($rider, 'Cash', (int) $_SESSION['user_id']);
            flash('success', "First permit fee recorded, receipt $receipt.");
        }
        if ($email !== null && !empty($_POST['send_invite'])) flash('info', rider_portal_invite($riderId));
        redirect('/pages/chairperson/rider-profile.php?id=' . $riderId . '&new=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Register Rider | BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Register New Rider'; $pageSubtitle = 'Onboard a new boda rider to ' . ($stage['name'] ?? 'your stage'); require __DIR__ . '/../../partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="icon-badge blue"><i class="fas fa-user-plus"></i></div>
                    <div><h4 class="fw-bold mb-0">Register New Rider</h4><small class="text-muted"><?= h($stage['name']) ?></small></div>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-12 mb-3 d-flex align-items-center gap-2"><div class="icon-badge blue" style="width:32px;height:32px;font-size:0.85rem;"><i class="fas fa-user"></i></div><h6 class="fw-bold text-primary mb-0">Personal Information</h6><hr class="flex-grow-1"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input value="<?= h($_POST['full_name'] ?? '') ?>" type="text" name="full_name" class="form-control form-control-lg" placeholder="e.g., AKELLO JAMES" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">NIN <span class="text-danger">*</span></label><input value="<?= h($_POST['nin'] ?? '') ?>" type="text" name="nin" class="form-control form-control-lg" placeholder="e.g., CM1234567890ABC" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Date of Birth</label><input value="<?= h($_POST['dob'] ?? '') ?>" type="date" name="dob" class="form-control form-control-lg"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select form-select-lg"><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Marital Status</label>
                            <select name="marital_status" class="form-select form-select-lg"><option value="">Select Status</option><option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option></select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">+256</span><input value="<?= h(preg_replace('/^\+?256/', '', (string) ($_POST['phone'] ?? ''))) ?>" type="tel" name="phone" class="form-control form-control-lg" placeholder="e.g., 772123456" required></div>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Email Address</label><input value="<?= h($_POST['email'] ?? '') ?>" type="email" name="email" class="form-control form-control-lg" placeholder="e.g., rider@email.com">
                            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="send_invite" value="1" id="sendInvite" checked><label class="form-check-label small" for="sendInvite">Email the rider a link to set up their portal password</label></div></div>
                        <div class="col-md-12 mb-3"><label class="form-label fw-semibold">Physical Address</label><input value="<?= h($_POST['address'] ?? '') ?>" type="text" name="address" class="form-control form-control-lg" placeholder="e.g., Bar Village, Lira District"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Next of Kin Name</label><input value="<?= h($_POST['nok_name'] ?? '') ?>" type="text" name="nok_name" class="form-control form-control-lg" placeholder="Full name"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Next of Kin Contact</label>
                            <div class="input-group"><span class="input-group-text">+256</span><input value="<?= h(preg_replace('/^\+?256/', '', (string) ($_POST['nok_phone'] ?? ''))) ?>" type="tel" name="nok_phone" class="form-control form-control-lg" placeholder="e.g., 772123456"></div>
                        </div>

                        <div class="col-12 mb-3 mt-3 d-flex align-items-center gap-2"><div class="icon-badge gold" style="width:32px;height:32px;font-size:0.85rem;"><i class="fas fa-motorcycle"></i></div><h6 class="fw-bold text-primary mb-0">Bike &amp; Stage Information</h6><hr class="flex-grow-1"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Bike Plate <span class="text-danger">*</span></label><input value="<?= h($_POST['bike_plate'] ?? '') ?>" type="text" name="bike_plate" class="form-control form-control-lg" placeholder="e.g., UAS 123K" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Bike Model</label><input value="<?= h($_POST['bike_model'] ?? '') ?>" type="text" name="bike_model" class="form-control form-control-lg" placeholder="e.g., Honda CG 125"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Stage</label><input type="text" class="form-control form-control-lg" value="<?= h($stage['name']) ?>" disabled></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Route</label><input type="text" name="route" class="form-control form-control-lg" placeholder="e.g., Railway - Town" value="<?= h($stage['route'] ?? '') ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Stage Chairperson</label><input type="text" class="form-control form-control-lg" value="<?= h($_SESSION['name']) ?>" disabled></div>

                        <div class="col-12 mb-3 mt-3 d-flex align-items-center gap-2"><div class="icon-badge dark" style="width:32px;height:32px;font-size:0.85rem;"><i class="fas fa-camera"></i></div><h6 class="fw-bold text-primary mb-0">Upload Photo</h6><hr class="flex-grow-1"></div>
                        <div class="col-12 mb-3">
                            <div class="upload-zone border-2 rounded-4 p-5 text-center" style="border: 2px dashed #dee2e6; cursor: pointer;" data-click-target="photoInput" role="button" tabindex="0" aria-label="Choose a rider photo">
                                <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                                <p class="text-muted">Click to upload passport photo</p>
                                <small class="text-muted">Max 2MB (JPG, PNG)</small>
                                <input type="file" id="photoInput" name="photo" class="d-none" accept="image/jpeg,image/png,image/webp" data-file-name-target="photoName">
                                <div id="photoName" class="text-success fw-bold mt-2"></div>
                            </div>
                        </div>

                        <div class="col-12 mb-3 mt-3 d-flex align-items-center gap-2"><div class="icon-badge green" style="width:32px;height:32px;font-size:0.85rem;"><i class="fas fa-money-bill-wave"></i></div><h6 class="fw-bold text-primary mb-0">Payment</h6><hr class="flex-grow-1"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Annual Tax</label><div class="input-group"><span class="input-group-text">UGX</span><input type="text" class="form-control form-control-lg" value="<?= number_format($city['annual_fee']) ?>" readonly></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Payment Status</label>
                            <select name="payment_status" class="form-select form-select-lg"><option value="Unpaid"<?= ($_POST['payment_status'] ?? '') !== 'Paid' ? ' selected' : '' ?>>Not paid yet: register only</option><option value="Paid"<?= ($_POST['payment_status'] ?? '') === 'Paid' ? ' selected' : '' ?>>Paid in cash now: record the fee and activate</option></select>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>Register & Generate ID</button>
                            <a href="<?= BASE_URL ?>/pages/chairperson/dashboard.php" class="btn btn-outline-secondary btn-lg px-5"><i class="fas fa-times me-2"></i>Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
