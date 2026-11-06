<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['chairperson']);
$riderId = (int) ($_GET['id'] ?? 0);
$cardRider = id_card_rider($riderId);
if (!$cardRider || (int) $cardRider['stage_id'] !== (int) $_SESSION['stage_id']) redirect('/pages/chairperson/id-cards.php');
$cardSelf = '/pages/chairperson/id-preview.php?id=' . $riderId;
if ($_SERVER['REQUEST_METHOD'] === 'POST') id_card_handle_post($cardRider, $cardSelf);

$cardRow = id_card_ensure($riderId);
$cardHistory = id_card_history($riderId);
$cardCity = fetchOne("SELECT * FROM cities WHERE id = ?", [$cardRider['city_id']]);
$cardCfg = id_card_settings();
$cardBack = ['/pages/chairperson/rider-profile.php?id=' . $riderId => 'Rider profile', '/pages/chairperson/id-cards.php' => 'All ID cards'];
$cardCanCancel = false;
$cardEditUrl = '/pages/chairperson/edit-rider.php?id=' . $riderId;

portal_start('chairperson', 'id-cards', 'ID card: ' . $cardRider['full_name'], [
    'subtitle' => $cardRider['id_number'] . ($cardRow ? ' · card ' . $cardRow['serial'] : ' · no valid card'),
    'head' => '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . @filemtime(__DIR__ . '/../../assets/css/id-card.css') . '">',
]);
require __DIR__ . '/../../partials/id-card-workspace.php';
?>
<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/id-card.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/id-card.js') ?>"></script>
<?php portal_end();
