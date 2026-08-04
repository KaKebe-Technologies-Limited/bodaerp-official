<?php
require_once __DIR__ . '/../../includes/auth.php';
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
    $phone = '+256' . preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '');
    $nokName = trim($_POST['nok_name'] ?? '');
    $nokPhone = $_POST['nok_phone'] !== '' ? '+256' . preg_replace('/\D/', '', $_POST['nok_phone']) : null;
    $plate = strtoupper(trim($_POST['bike_plate'] ?? ''));
    $model = trim($_POST['bike_model'] ?? '');
    $route = trim($_POST['route'] ?? '');
    $paymentStatus = $_POST['payment_status'] ?? 'Unpaid';

    if ($fullName === '' || $nin === '' || $phone === '+256' || $plate === '') {
        $errors[] = 'Full name, NIN, phone, and bike plate are required.';
    }
    if (!$errors && fetchValue("SELECT COUNT(*) FROM riders WHERE nin = ?", [$nin])) {
        $errors[] = 'A rider with this NIN is already registered.';
    }

    $photoPath = null;
    if (!$errors && !empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($file['error'] === UPLOAD_ERR_OK && isset($allowed[$file['type']]) && $file['size'] <= 2 * 1024 * 1024) {
            $ext = $allowed[$file['type']];
            $filename = uniqid('rider_', true) . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                $photoPath = UPLOAD_URL . $filename;
            }
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            // ignore, photo optional
        } else {
            $errors[] = 'Photo must be JPG or PNG, max 2MB.';
        }
    }

    if (!$errors) {
        $status = $paymentStatus === 'Paid' ? 'active' : 'pending';
        $memberSince = date('Y-m-d');
        $expiry = $paymentStatus === 'Paid' ? date('Y-m-d', strtotime('+365 days')) : null;

        runQuery("INSERT INTO riders (city_id,stage_id,id_number,full_name,nin,date_of_birth,gender,marital_status,phone,email,
                  physical_address,next_of_kin_name,next_of_kin_contact,bike_plate,bike_model,route,photo_path,status,member_since,
                  expiry_date,annual_tax,created_by)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$cityId,$stageId,'TMP-'.uniqid(),$fullName,$nin,$dob,$gender,$marital,$phone,$email,$address,$nokName,$nokPhone,
             $plate,$model,$route,$photoPath,$status,$memberSince,$expiry,$city['annual_fee'],$_SESSION['user_id']]);
        $riderId = (int) db()->insert_id;
        $idNumber = nextIdNumber($riderId, $cityId);
        runQuery("UPDATE riders SET id_number = ? WHERE id = ?", [$idNumber, $riderId]);

        if ($paymentStatus === 'Paid') {
            runQuery("INSERT INTO payments (rider_id,city_id,amount,payment_method,receipt_number,status,fiscal_year,paid_at,collected_by)
                      VALUES (?,?,?,?,?,'Confirmed',?,NOW(),?)",
                [$riderId,$cityId,$city['annual_fee'],'Cash','TMP-'.uniqid(),CURRENT_FISCAL_YEAR,$_SESSION['user_id']]);
            $paymentId = (int) db()->insert_id;
            runQuery("UPDATE payments SET receipt_number = ? WHERE id = ?", [nextReceiptNumber($paymentId), $paymentId]);
        }

        if ($_SESSION['role'] ?? null) {
            audit_log('CREATE', 'rider', $idNumber, "Registered rider: $fullName");
        }
        redirect('/pages/chairperson/rider-profile.php?id=' . $riderId . '&new=1');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Register Rider - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'Register New Rider'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area">
        <div class="card">
            <div class="card-body p-5">
                <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>
                <h4 class="fw-bold mb-4"><i class="fas fa-user-plus text-primary me-2"></i>Register New Rider <span class="badge bg-primary ms-2"><?= h($stage['name']) ?></span></h4>

                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-12 mb-3"><h6 class="fw-bold text-primary">👤 Personal Information</h6><hr></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label><input type="text" name="full_name" class="form-control form-control-lg" placeholder="e.g., AKELLO JAMES" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">NIN <span class="text-danger">*</span></label><input type="text" name="nin" class="form-control form-control-lg" placeholder="e.g., CM1234567890ABC" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Date of Birth</label><input type="date" name="dob" class="form-control form-control-lg"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select form-select-lg"><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Marital Status</label>
                            <select name="marital_status" class="form-select form-select-lg"><option value="">Select Status</option><option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widowed">Widowed</option></select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <div class="input-group"><span class="input-group-text">+256</span><input type="tel" name="phone" class="form-control form-control-lg" placeholder="e.g., 772123456" required></div>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Email Address</label><input type="email" name="email" class="form-control form-control-lg" placeholder="e.g., rider@email.com"></div>
                        <div class="col-md-12 mb-3"><label class="form-label fw-semibold">Physical Address</label><input type="text" name="address" class="form-control form-control-lg" placeholder="e.g., Bar Village, Lira District"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Next of Kin Name</label><input type="text" name="nok_name" class="form-control form-control-lg" placeholder="Full name"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Next of Kin Contact</label>
                            <div class="input-group"><span class="input-group-text">+256</span><input type="tel" name="nok_phone" class="form-control form-control-lg" placeholder="e.g., 772123456"></div>
                        </div>

                        <div class="col-12 mb-3 mt-3"><h6 class="fw-bold text-primary">🏍️ Bike & Stage Information</h6><hr></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Bike Plate <span class="text-danger">*</span></label><input type="text" name="bike_plate" class="form-control form-control-lg" placeholder="e.g., UAS 123K" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Bike Model</label><input type="text" name="bike_model" class="form-control form-control-lg" placeholder="e.g., Honda CG 125"></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-semibold">Stage</label><input type="text" class="form-control form-control-lg" value="<?= h($stage['name']) ?>" disabled></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Route</label><input type="text" name="route" class="form-control form-control-lg" placeholder="e.g., Railway - Town" value="<?= h($stage['route'] ?? '') ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Stage Chairperson</label><input type="text" class="form-control form-control-lg" value="<?= h($_SESSION['name']) ?>" disabled></div>

                        <div class="col-12 mb-3 mt-3"><h6 class="fw-bold text-primary">📸 Upload Photo</h6><hr></div>
                        <div class="col-12 mb-3">
                            <div class="upload-zone border-2 rounded-4 p-5 text-center" style="border: 2px dashed #dee2e6; cursor: pointer;" onclick="document.getElementById('photoInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                                <p class="text-muted">Click to upload passport photo</p>
                                <small class="text-muted">Max 2MB (JPG, PNG)</small>
                                <input type="file" id="photoInput" name="photo" class="d-none" accept="image/jpeg,image/png" onchange="document.getElementById('photoName').textContent = this.files[0]?.name || ''">
                                <div id="photoName" class="text-success fw-bold mt-2"></div>
                            </div>
                        </div>

                        <div class="col-12 mb-3 mt-3"><h6 class="fw-bold text-primary">💰 Payment</h6><hr></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Annual Tax</label><div class="input-group"><span class="input-group-text">UGX</span><input type="text" class="form-control form-control-lg" value="<?= number_format($city['annual_fee']) ?>" readonly></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label fw-semibold">Payment Status</label>
                            <select name="payment_status" class="form-select form-select-lg"><option value="Paid">✅ Paid - Activate Now</option><option value="Unpaid">⏳ Unpaid - Activate Later</option></select>
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

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
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
