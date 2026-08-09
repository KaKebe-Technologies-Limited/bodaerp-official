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

        .id-photo-col { width: 35%; flex-shrink: 0; display: flex; flex-direction: column; background: #eef1f4; }
        .id-photo-col .photo-frame { position: relative; flex: 1; min-height: 0; overflow: hidden; }
        .id-photo-col .photo-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .id-photo-col .photo-flag { position: absolute; top: 8px; left: -20px; width: 74px; text-align: center; background: #8f1424; color: #fff; font-size: 0.26rem; font-weight: 800; letter-spacing: 0.6px; transform: rotate(-45deg); padding: 1.5px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.35); }
        .id-photo-col .plate-badge { background: #1a1a2e; color: #fff; font-family: 'Courier New', monospace; font-size: 0.32rem; font-weight: 700; text-align: center; padding: 2px 2px; letter-spacing: 0.5px; }
        .id-photo-col .status-ribbon { padding: 1.5px 2px; font-size: 0.3rem; font-weight: 800; text-align: center; color: #fff; letter-spacing: 0.3px; }

        .id-fields-col { flex: 1; min-width: 0; padding: 5px 7px; display: flex; flex-direction: column; justify-content: center; gap: 3.5px; position: relative; background-image: repeating-linear-gradient(135deg, rgba(143,20,36,0.05) 0px, rgba(143,20,36,0.05) 1px, transparent 1px, transparent 7px); }
        .id-field { line-height: 1.15; position: relative; z-index: 1; }
        .id-field .field-label { font-size: 0.22rem; color: #9aa2ab; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        .id-field .field-value { font-size: 0.36rem; color: #1a1a2e; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .id-qr-col { width: 26%; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; padding: 4px; position: relative; }
        .id-holo { position: absolute; width: 36px; height: 36px; border-radius: 50%; background: conic-gradient(from 90deg, #ff9a8b, #a18cd1, #84fab0, #8fd3f4, #ff9a8b); opacity: 0.35; filter: blur(1.8px); top: 2px; }
        .qr-title { font-size: 0.24rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #8f1424; position: relative; z-index: 1; }
        .qr-wrap { width: 54px; height: 54px; background: #fff; padding: 3px; border-radius: 4px; position: relative; z-index: 1; border: 1px solid #ddd; }
        .qr-wrap img, .qr-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
        .qr-center-badge { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 16px; height: 16px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 3; box-shadow: 0 0 0 2px #fff, 0 0 0 3px rgba(143,20,36,0.5); }
        .qr-center-badge img { width: 100%; height: 100%; object-fit: contain; }
        .qr-caption { font-size: 0.21rem; color: #6c757d; letter-spacing: 0.3px; text-transform: uppercase; font-weight: 700; position: relative; z-index: 1; }

        .id-footer { position: relative; z-index: 1; display: flex; align-items: flex-end; justify-content: space-between; gap: 4px; padding: 3px 8px; background: #f8f9fa; border-top: 1px solid #eee; flex-shrink: 0; }
        .id-sig { display: flex; flex-direction: column; align-items: flex-start; min-width: 0; }
        .id-sig .sig-line { font-family: 'Brush Script MT', cursive; font-size: 0.5rem; color: #1a3a5c; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
        .id-sig .sig-label { font-size: 0.24rem; color: #9aa2ab; text-transform: uppercase; letter-spacing: 0.3px; border-top: 1px solid #ccc; padding-top: 1px; margin-top: 1px; }
        .id-expiry { text-align: right; flex-shrink: 0; }
        .id-expiry .expiry-label { font-size: 0.24rem; color: #9aa2ab; text-transform: uppercase; letter-spacing: 0.3px; }
        .id-expiry .expiry-date { font-size: 0.38rem; font-weight: 800; color: #8f1424; }

        /* back of card — centered return-to-authority notice */
        .id-back-body { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 6px 16px; gap: 2.5px; }
        .id-back-body .back-crest { width: 28px; height: 28px; border-radius: 50%; background: #fff; border: 1.5px solid #eee; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 1px; }
        .id-back-body .back-crest img { width: 100%; height: 100%; object-fit: contain; }
        .id-back-body .back-statement { font-size: 0.32rem; font-weight: 700; color: #1a1a2e; line-height: 1.3; max-width: 92%; }
        .id-back-body .back-return-label { font-size: 0.25rem; color: #8f1424; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 2px; }
        .id-back-body .back-contact { font-size: 0.29rem; color: #333; line-height: 1.45; }
        .id-back-body .back-terms { font-size: 0.23rem; color: #9aa2ab; margin-top: 3px; }

        /* enlarge / preview */
        .id-stage { position: relative; display: inline-block; }
        .id-zoom-btn { position: absolute; top: 8px; right: 8px; background: rgba(26,26,46,0.55); color: #fff; border: none; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; cursor: pointer; z-index: 5; transition: background 0.15s; }
        .id-zoom-btn:hover { background: rgba(143,20,36,0.9); }
        .id-zoom-wrap { width: 7.425in; height: 4.675in; margin: 20px auto; position: relative; }
        .id-zoom-clone { transform: scale(2.2); transform-origin: top left; position: absolute; top: 0; left: 0; box-shadow: 0 30px 90px rgba(0,0,0,0.55); }
        #idPreviewModal.modal { background: rgba(12,12,18,0.94); }
        #idPreviewModal .modal-content { background: transparent; border: none; box-shadow: none; }
        #idPreviewBody { max-height: 92vh; overflow-y: auto; overflow-x: hidden; padding: 20px 0; }
    </style>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-chairperson.php'; ?>

<div class="main-content" id="mainContent">
    <?php $pageTitle = 'ID Card Preview'; require __DIR__ . '/../../includes/partials/topheader.php'; ?>

    <div class="content-area text-center">
        <div class="id-stage">
            <button type="button" class="id-zoom-btn" title="Enlarge"><i class="fas fa-magnifying-glass-plus"></i></button>
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
                        <div class="id-field"><div class="field-label">Name</div><div class="field-value"><?= h($rider['full_name']) ?></div></div>
                        <div class="id-field"><div class="field-label">Date of Birth</div><div class="field-value"><?= formatDate($rider['date_of_birth']) ?></div></div>
                        <div class="id-field"><div class="field-label">Gender</div><div class="field-value"><?= h($rider['gender'] ?: '—') ?></div></div>
                        <div class="id-field"><div class="field-label">Stage</div><div class="field-value"><?= h($rider['stage_name']) ?></div></div>
                        <div class="id-field"><div class="field-label">Address</div><div class="field-value"><?= h($rider['physical_address'] ?: '—') ?></div></div>
                        <div class="id-field"><div class="field-label">NIN</div><div class="field-value"><?= h($rider['nin'] ?: '—') ?></div></div>
                    </div>
                    <div class="id-qr-col">
                        <div class="id-holo"></div>
                        <div class="qr-title">Verify</div>
                        <div class="qr-wrap" id="qrFront">
                            <div class="qr-center-badge"><img src="<?= h(BASE_URL . $city['logo_path']) ?>" onerror="this.style.display='none'"></div>
                        </div>
                        <div class="qr-caption">Scan to confirm</div>
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
        </div>

        <div class="id-stage">
            <button type="button" class="id-zoom-btn" title="Enlarge"><i class="fas fa-magnifying-glass-plus"></i></button>
            <div class="id-card">
                <div class="id-watermark"><span><?= h(strtoupper($city['name'])) ?></span></div>
                <div class="id-header">
                    <div class="id-header-text"><div class="entity-name">If Found, Please Return</div></div>
                </div>
                <div class="id-back-body">
                    <div class="back-crest"><img src="<?= h(BASE_URL . $city['logo_path']) ?>" onerror="this.style.display='none'"></div>
                    <div class="back-statement">This card is the property of <?= h($city['name']) ?> and must be surrendered on request.</div>
                    <div class="back-return-label">Return To The Issuing Authority</div>
                    <div class="back-contact">
                        <?= h($city['name']) ?> Council<br>
                        <?= h($city['address'] ?: 'Uganda') ?><br>
                        <?= h($city['contact_phone'] ?: '—') ?> · <?= h($city['contact_email'] ?: '—') ?>
                    </div>
                    <div class="back-terms">Non-transferable · Renew annually · Ref <?= h($rider['id_number']) ?></div>
                </div>
            </div>
        </div>

        <div class="mt-2">
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print ID Card</button>
            <button class="btn btn-outline-dark" id="previewAllBtn"><i class="fas fa-magnifying-glass-plus me-1"></i>Preview / Enlarge</button>
            <a href="<?= BASE_URL ?>/pages/chairperson/rider-profile.php?id=<?= $riderId ?>" class="btn btn-outline-secondary">Back to Profile</a>
        </div>
    </div>
</div>

<div class="modal fade" id="idPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width: 96vw; margin: 3vh auto;">
        <div class="modal-content">
            <button type="button" class="btn-close btn-close-white position-fixed top-0 end-0 m-4" data-bs-dismiss="modal" style="z-index: 10;"></button>
            <div class="modal-body d-flex flex-column justify-content-start align-items-center" id="idPreviewBody"></div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    new QRCode(document.getElementById('qrFront'), {
        text: <?= json_encode(verifyUrl($rider['id_number'])) ?>,
        width: 160, height: 160,
        colorDark: '#1a1a2e', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    function openIdPreview(single) {
        const body = document.getElementById('idPreviewBody');
        body.innerHTML = '';
        const cards = single ? [single] : document.querySelectorAll('.id-stage > .id-card');
        cards.forEach(card => {
            const clone = card.cloneNode(true);
            clone.removeAttribute('id');
            clone.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
            clone.classList.add('id-zoom-clone');
            const wrap = document.createElement('div');
            wrap.className = 'id-zoom-wrap';
            wrap.appendChild(clone);
            body.appendChild(wrap);
        });
        new bootstrap.Modal(document.getElementById('idPreviewModal')).show();
    }
    document.getElementById('previewAllBtn')?.addEventListener('click', () => openIdPreview(null));
    document.querySelectorAll('.id-zoom-btn').forEach(btn => {
        btn.addEventListener('click', () => openIdPreview(btn.nextElementSibling));
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
