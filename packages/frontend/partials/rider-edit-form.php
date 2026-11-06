<?php
// Full rider record form (chairperson, city admin, Super Admin). Expects $editRider (riders row), $editPlace (bool:
// stage, status and expiry may be changed), $editStages (id => name, when $editPlace), $editErrors, $editBack (url).
$ev = fn(string $f) => h((string) ($_POST[$f] ?? $editRider[$f] ?? ''));
$local = fn(?string $p) => h(preg_replace('/^\+?256/', '', (string) $p));
$sel = fn(string $f, string $v) => (string) ($_POST[$f] ?? $editRider[$f] ?? '') === $v ? ' selected' : '';
?>
<?php if ($editErrors): ?><div class="alert alert-danger" role="alert"><?php foreach ($editErrors as $e) echo h($e) . '<br>'; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="layout-thirds">
    <?= csrf_field() ?>
    <div>
        <section class="card mb-4"><div class="card-body">
            <h2 class="section-title">Personal details</h2>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label" for="e-name">Full name</label><input class="form-control" id="e-name" name="full_name" value="<?= $ev('full_name') ?>" maxlength="100" required></div>
                <div class="col-md-4"><label class="form-label" for="e-nin">NIN</label><input class="form-control" id="e-nin" name="nin" value="<?= $ev('nin') ?>" maxlength="14" pattern="[A-Za-z0-9]{14}"></div>
                <div class="col-md-4"><label class="form-label" for="e-dob">Date of birth</label><input class="form-control" type="date" id="e-dob" name="date_of_birth" value="<?= $ev('date_of_birth') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="e-gender">Gender</label><select class="form-select" id="e-gender" name="gender"><option value="">Not given</option><?php foreach (['Male', 'Female', 'Other'] as $g): ?><option<?= $sel('gender', $g) ?>><?= $g ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="e-marital">Marital status</label><select class="form-select" id="e-marital" name="marital_status"><option value="">Not given</option><?php foreach (['Single', 'Married', 'Divorced', 'Widowed'] as $m): ?><option<?= $sel('marital_status', $m) ?>><?= $m ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="e-phone">Mobile number</label><div class="input-group"><span class="input-group-text">+256</span><input class="form-control" type="tel" id="e-phone" name="phone" value="<?= isset($_POST['phone']) ? h($_POST['phone']) : $local($editRider['phone']) ?>"></div></div>
                <div class="col-md-6"><label class="form-label" for="e-email">Email</label><input class="form-control" type="email" id="e-email" name="email" value="<?= $ev('email') ?>"></div>
                <div class="col-12"><label class="form-label" for="e-address">Home address</label><input class="form-control" id="e-address" name="physical_address" value="<?= $ev('physical_address') ?>" maxlength="255"></div>
                <div class="col-md-6"><label class="form-label" for="e-nok">Next of kin</label><input class="form-control" id="e-nok" name="next_of_kin_name" value="<?= $ev('next_of_kin_name') ?>" maxlength="100"></div>
                <div class="col-md-6"><label class="form-label" for="e-nokc">Next of kin number</label><div class="input-group"><span class="input-group-text">+256</span><input class="form-control" type="tel" id="e-nokc" name="next_of_kin_contact" value="<?= isset($_POST['next_of_kin_contact']) ? h($_POST['next_of_kin_contact']) : $local($editRider['next_of_kin_contact']) ?>"></div></div>
            </div>
        </div></section>
        <section class="card"><div class="card-body">
            <h2 class="section-title">Motorcycle and permit</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="e-plate">Plate</label><input class="form-control" id="e-plate" name="bike_plate" value="<?= $ev('bike_plate') ?>" maxlength="20" required></div>
                <div class="col-md-4"><label class="form-label" for="e-model">Model</label><input class="form-control" id="e-model" name="bike_model" value="<?= $ev('bike_model') ?>" maxlength="60"></div>
                <div class="col-md-4"><label class="form-label" for="e-route">Route</label><input class="form-control" id="e-route" name="route" value="<?= $ev('route') ?>" maxlength="100"></div>
                <?php if ($editPlace): ?>
                <div class="col-md-4"><label class="form-label" for="e-stage">Stage</label><select class="form-select" id="e-stage" name="stage_id" required><?php foreach ($editStages as $sid => $sname): ?><option value="<?= (int) $sid ?>"<?= (int) ($_POST['stage_id'] ?? $editRider['stage_id']) === (int) $sid ? ' selected' : '' ?>><?= h($sname) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="e-status">Permit status</label><select class="form-select" id="e-status" name="status"><?php foreach (['active' => 'Active', 'pending' => 'Not paid yet', 'expired' => 'Expired'] as $k => $l): ?><option value="<?= $k ?>"<?= $sel('status', $k) ?>><?= $l ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="e-exp">Valid until</label><input class="form-control" type="date" id="e-exp" name="expiry_date" value="<?= $ev('expiry_date') ?>"></div>
                <?php else: ?>
                <div class="col-12"><p class="small text-muted mb-0">Stage, permit status and expiry follow payments and the city office. Ask the city office to move a rider to another stage.</p></div>
                <?php endif; ?>
            </div>
        </div></section>
    </div>
    <aside>
        <section class="card mb-4"><div class="card-body">
            <h2 class="section-title">Photo</h2>
            <div class="d-flex align-items-center gap-3 mb-3"><?= person_avatar($editRider['photo_path'] ?? null, (string) $editRider['full_name'], 'is-lg') ?>
                <p class="small text-muted mb-0">A clear face photo on a plain background. It is printed on the ID card and shown on a QR check.</p></div>
            <label class="form-label" for="e-photo">Replace the photo</label>
            <input type="file" id="e-photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-mb="2">
        </div></section>
        <section class="card"><div class="card-body">
            <p class="small text-muted">Every change is recorded in the audit log with the old and new value, and the rider is told what changed. Changes to the name, NIN, birth date, plate, stage or photo need a new ID card.</p>
            <div class="d-grid gap-2">
                <button class="btn btn-primary" data-busy-label="Saving">Save changes</button>
                <a class="btn btn-outline-secondary" href="<?= h(BASE_URL . $editBack) ?>">Cancel</a>
            </div>
        </div></section>
    </aside>
</form>
