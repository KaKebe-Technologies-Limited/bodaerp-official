<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['chairperson']);
$active = 'my-riders';
$stageId = $_SESSION['stage_id'];
$riderId = (int) ($_GET['id'] ?? 0);

$rider = fetchOne("SELECT r.*, s.name AS stage_name, u.name AS registrar_name
                    FROM riders r
                    LEFT JOIN stages s ON s.id = r.stage_id
                    LEFT JOIN users u ON u.id = r.created_by
                    WHERE r.id = ? AND r.stage_id = ?", [$riderId, $stageId]);
if (!$rider) redirect('/pages/chairperson/my-riders.php');
$city = fetchOne("SELECT * FROM cities WHERE id = ?", [$_SESSION['city_id']]);
$statusColors = ['active' => '#198754', 'expired' => '#dc3545', 'pending' => '#f59e0b'];
$statusColor = $statusColors[$rider['status']] ?? '#6c757d';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'ID Card Preview - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
    <style>
        .id-card { width: 3.375in; height: 2.125in; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 12px 34px rgba(0,0,0,0.18); margin: 0 auto 20px; display: flex; flex-direction: column; border: 1px solid rgba(0,0,0,0.08); position: relative; }

        .id-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 0; overflow: hidden; }
        .id-watermark span { font-size: 1.5rem; font-weight: 900; color: rgba(160,23,41,0.06); letter-spacing: 3px; white-space: nowrap; transform: rotate(-18deg); text-transform: uppercase; }

        .id-header { position: relative; z-index: 1; background: linear-gradient(135deg, #dc3545, #8f1424); color: #fff; padding: 5px 8px; display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
        .id-crest { width: 22px; height: 22px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; border: 1.5px solid rgba(255,255,255,0.65); }
        .id-crest img { width: 100%; height: 100%; object-fit: contain; }
        .id-header-text { line-height: 1.15; min-width: 0; }
        .id-header-text .entity-name { font-weight: 900; font-size: 0.6rem; letter-spacing: 0.3px; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .id-header-text .doc-type { font-size: 0.34rem; opacity: 0.92; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .id-header-right { margin-left: auto; text-align: right; line-height: 1.15; flex-shrink: 0; }
        .id-header-right .doc-code { font-size: 0.32rem; background: rgba(255,255,255,0.22); padding: 1px 5px; border-radius: 5px; display: inline-block; margin-bottom: 1px; letter-spacing: 0.4px; font-weight: 700; }
        .id-header-right .id-number-big { font-family: 'Courier New', monospace; font-weight: 800; font-size: 0.46rem; letter-spacing: 0.3px; }

        .id-main { position: relative; z-index: 1; display: flex; flex: 1; min-height: 0; }

        .id-photo-col { width: 27%; flex-shrink: 0; display: flex; flex-direction: column; background: #eef1f4; }
        .id-photo-col .photo-frame { position: relative; flex: 1; min-height: 0; overflow: hidden; }
        .id-photo-col .photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .id-photo-col .photo-flag { position: absolute; top: 7px; left: -18px; width: 66px; text-align: center; background: #8f1424; color: #fff; font-size: 0.26rem; font-weight: 800; letter-spacing: 0.6px; transform: rotate(-45deg); padding: 1px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.35); }
        .id-photo-col .plate-badge { background: #1a1a2e; color: #fff; font-family: 'Courier New', monospace; font-size: 0.3rem; font-weight: 700; text-align: center; padding: 1.5px 2px; letter-spacing: 0.5px; }
        .id-photo-col .status-ribbon { padding: 1.5px 2px; font-size: 0.3rem; font-weight: 800; text-align: center; color: #fff; letter-spacing: 0.3px; }

        .id-fields-col { flex: 1; min-width: 0; padding: 4px 6px 2px; display: flex; flex-direction: column; justify-content: center; }
        .id-fields { list-style: none; counter-reset: idfield; margin: 0; padding: 0; }
        .id-fields li { counter-increment: idfield; display: flex; gap: 3px; font-size: 0.32rem; padding: 0.8px 0; align-items: baseline; }
        .id-fields li::before { content: counter(idfield) "."; font-weight: 800; color: #8f1424; flex-shrink: 0; width: 7px; }
        .id-fields li .field-label { color: #9aa2ab; font-weight: 700; width: 34px; flex-shrink: 0; text-transform: uppercase; }
        .id-fields li .field-value { color: #1a1a2e; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .id-qr-col { width: 24%; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; padding: 4px; position: relative; }
        .id-holo { position: absolute; width: 30px; height: 30px; border-radius: 50%; background: conic-gradient(from 90deg, #ff9a8b, #a18cd1, #84fab0, #8fd3f4, #ff9a8b); opacity: 0.4; filter: blur(1.5px); top: 3px; }
        .qr-wrap { width: 34px; height: 34px; background: #fff; padding: 2px; border-radius: 3px; position: relative; z-index: 1; border: 1px solid #ddd; }
        .qr-wrap img, .qr-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
        .qr-caption { font-size: 0.24rem; color: #6c757d; letter-spacing: 0.3px; text-transform: uppercase; font-weight: 700; position: relative; z-index: 1; }

        .id-footer { position: relative; z-index: 1; display: flex; align-items: flex-end; justify-content: space-between; gap: 4px; padding: 3px 8px; background: #f8f9fa; border-top: 1px solid #eee; flex-shrink: 0; }
        .id-sig { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; }
        .id-sig .sig-line { font-family: 'Brush Script MT', cursive; font-size: 0.5rem; color: #1a3a5c; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
        .id-sig .sig-label { font-size: 0.24rem; color: #9aa2ab; text-transform: uppercase; letter-spacing: 0.3px; border-top: 1px solid #ccc; padding-top: 1px; margin-top: 1px; }
        .id-expiry { text-align: right; flex-shrink: 0; }
        .id-expiry .expiry-label { font-size: 0.24rem; color: #9aa2ab; text-transform: uppercase; letter-spacing: 0.3px; }
        .id-expiry .expiry-date { font-size: 0.38rem; font-weight: 800; color: #8f1424; }

        .id-terms { font-size: 0.4rem; line-height: 1.35; color: #6c757d; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'ID Card Preview'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area text-center">
        <div class="id-card">
            <div class="id-watermark"><span><?= h(strtoupper($city['name'])) ?></span></div>
            <div class="id-header">
                <div class="id-crest"><img src="<?= h(BASE_URL . $city['logo_path']) ?>" onerror="this.style.display='none'"></div>
                <div class="id-header-text">
                    <div class="entity-name"><?= h(strtoupper($city['name'])) ?></div>
                    <div class="doc-type">Boda Boda Operator License</div>
                </div>
                <div class="id-header-right">
                    <div class="doc-code"><?= h($city['id_prefix']) ?></div>
                    <div class="id-number-big"><?= h($rider['id_number']) ?></div>
                </div>
            </div>
            <div class="id-main">
                <div class="id-photo-col">
                    <div class="photo-frame">
                        <img src="<?= $rider['photo_path'] ? h(BASE_URL.$rider['photo_path']) : BASE_URL.'/assets/images/avatar-placeholder.png' ?>">
                        <div class="photo-flag"><?= h($city['id']) ?></div>
                    </div>
                    <div class="plate-badge"><?= h($rider['bike_plate']) ?></div>
                    <div class="status-ribbon" style="background: <?= $statusColor ?>;"><?= strtoupper($rider['status']) ?></div>
                </div>
                <div class="id-fields-col">
                    <ol class="id-fields">
                        <li><span class="field-label">Name</span><span class="field-value"><?= h($rider['full_name']) ?></span></li>
                        <li><span class="field-label">DOB</span><span class="field-value"><?= formatDate($rider['date_of_birth']) ?></span></li>
                        <li><span class="field-label">Gender</span><span class="field-value"><?= h($rider['gender'] ?: '—') ?></span></li>
                        <li><span class="field-label">Stage</span><span class="field-value"><?= h($rider['stage_name']) ?></span></li>
                        <li><span class="field-label">Addr</span><span class="field-value"><?= h($rider['physical_address'] ?: '—') ?></span></li>
                        <li><span class="field-label">NIN</span><span class="field-value"><?= h($rider['nin'] ?: '—') ?></span></li>
                    </ol>
                </div>
                <div class="id-qr-col">
                    <div class="id-holo"></div>
                    <div class="qr-wrap" id="qrFront"></div>
                    <div class="qr-caption">Scan to verify</div>
                </div>
            </div>
            <div class="id-footer">
                <div class="id-sig">
                    <div class="sig-line"><?= h($rider['registrar_name'] ?: 'BodaERP') ?></div>
                    <div class="sig-label">Issued By</div>
                </div>
                <div class="id-expiry">
                    <div class="expiry-label">Valid Until</div>
                    <div class="expiry-date"><?= formatDate($rider['expiry_date']) ?></div>
                </div>
            </div>
        </div>

        <div class="id-card">
            <div class="id-header"><div class="id-header-text"><div class="entity-name">Back · Terms &amp; Conditions</div></div></div>
            <div class="id-main" style="padding: 8px 12px; flex-direction: column;">
                <div class="id-fields-col p-0">
                    <ol class="id-fields">
                        <li><span class="field-label">Phone</span><span class="field-value"><?= h($rider['phone']) ?></span></li>
                        <li><span class="field-label">Vehicle</span><span class="field-value"><?= h($rider['bike_plate']) ?> · <?= h($rider['bike_model'] ?: '—') ?></span></li>
                        <li><span class="field-label">Since</span><span class="field-value"><?= formatDate($rider['member_since'], 'M Y') ?></span></li>
                        <li><span class="field-label">Next of Kin</span><span class="field-value"><?= h($rider['next_of_kin_name'] ?: '—') ?></span></li>
                        <li><span class="field-label">Chair</span><span class="field-value"><?= h($_SESSION['name']) ?></span></li>
                    </ol>
                </div>
                <div class="id-terms mt-1 text-start">Non-transferable · Renew annually · Report if lost to your stage chairperson.</div>
            </div>
        </div>

        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print ID Card</button>
        <a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= $riderId ?>" class="btn btn-outline-secondary">Back to Profile</a>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrFront'), {
        text: <?= json_encode(verifyUrl($rider['id_number'])) ?>,
        width: 120, height: 120,
        colorDark: '#1a1a2e', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });

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
