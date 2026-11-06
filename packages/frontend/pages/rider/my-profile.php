<?php
// The rider's registered details, read only. Changes go through the stage chairperson (or a correction request
// in My Account), so the register stays the single record a council can rely on.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_role(['rider']);
$riderId = (int) ($_SESSION['rider_id'] ?? 0);
if (!$riderId) redirect('/login.php');

$rider = fetchOne("SELECT r.*, s.name AS stage_name, s.route AS stage_route, s.chairperson_name, s.chairperson_phone
    FROM riders r LEFT JOIN stages s ON s.id = r.stage_id WHERE r.id = ?", [$riderId]);
$dash = '–';
$personal = [
    t('rider.profile.name', 'Full name') => $rider['full_name'],
    t('rider.profile.nin', 'National ID number (NIN)') => $rider['nin'],
    t('rider.profile.dob', 'Date of birth') => $rider['date_of_birth'] ? formatDate($rider['date_of_birth']) : $dash,
    t('rider.profile.gender', 'Gender') => $rider['gender'] ?: $dash,
    t('rider.profile.marital', 'Marital status') => $rider['marital_status'] ?: $dash,
    t('rider.profile.phone', 'Mobile number') => $rider['phone'],
    t('rider.profile.email', 'Email') => $rider['email'] ?: $dash,
    t('rider.profile.address', 'Where you live') => $rider['physical_address'] ?: $dash,
    t('rider.profile.kin', 'Next of kin') => trim(($rider['next_of_kin_name'] ?: $dash) . ($rider['next_of_kin_contact'] ? ', ' . $rider['next_of_kin_contact'] : '')),
];
$work = [
    t('rider.profile.plate', 'Motorcycle plate') => $rider['bike_plate'],
    t('rider.profile.model', 'Motorcycle model') => $rider['bike_model'] ?: $dash,
    t('rider.profile.stage', 'Stage') => $rider['stage_name'] ?: $dash,
    t('rider.profile.route', 'Route') => $rider['stage_route'] ?: $dash,
    t('rider.profile.member', 'Registered since') => formatDate($rider['member_since']),
];

portal_start('rider', 'my-profile', t('rider.profile.title', 'My details'), [
    'subtitle' => t('rider.profile.subtitle', 'What the council register holds about you'),
]);
?>
<div class="layout-thirds">
    <div>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="section-title"><?= h(t('rider.profile.personal', 'About you')) ?></h2>
                <dl class="row rider-facts mb-0">
                    <?php foreach ($personal as $label => $value): ?><dt class="col-sm-4"><?= h($label) ?></dt><dd class="col-sm-8"><?= h((string) $value) ?></dd><?php endforeach; ?>
                </dl>
            </div>
        </section>
        <section class="card">
            <div class="card-body">
                <h2 class="section-title"><?= h(t('rider.profile.work', 'Your motorcycle and stage')) ?></h2>
                <dl class="row rider-facts mb-0">
                    <?php foreach ($work as $label => $value): ?><dt class="col-sm-4"><?= h($label) ?></dt><dd class="col-sm-8"><?= h((string) $value) ?></dd><?php endforeach; ?>
                </dl>
            </div>
        </section>
    </div>
    <aside class="card">
        <div class="card-body">
            <h2 class="section-title"><?= h(t('rider.profile.change', 'Something wrong?')) ?></h2>
            <p class="small"><?= h(t('rider.profile.change_text', 'Your stage chairperson can correct your details. You can also send a correction request, and the BodaERP team will answer in your messages.')) ?></p>
            <?php if ($rider['chairperson_name']): ?>
                <p class="small mb-3"><strong><?= h($rider['chairperson_name']) ?></strong><?php if ($rider['chairperson_phone']): ?><br><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $rider['chairperson_phone'])) ?>"><?= h($rider['chairperson_phone']) ?></a><?php endif; ?></p>
            <?php endif; ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/pages/account/settings.php#privacy"><?= h(t('rider.profile.request', 'Send a correction request')) ?></a>
        </div>
    </aside>
</div>
<?php portal_end(); ?>
