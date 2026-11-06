<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['city_admin']);
$riderId = (int) ($_GET['id'] ?? 0);
$cardRider = id_card_rider($riderId);
if (!$cardRider || (string) $cardRider['city_id'] !== (string) $_SESSION['city_id']) redirect('/pages/citycouncil/id-cards.php');
$cardSelf = '/pages/citycouncil/id-card.php?id=' . $riderId;
if ($_SERVER['REQUEST_METHOD'] === 'POST') id_card_handle_post($cardRider, $cardSelf);

$cardRow = id_card_ensure($riderId);
$cardHistory = id_card_history($riderId);
$cardCity = fetchOne("SELECT * FROM cities WHERE id = ?", [$cardRider['city_id']]);
$cardCfg = id_card_settings();
$cardBack = ['/pages/citycouncil/id-cards.php' => 'All ID cards', '/pages/citycouncil/city-riders.php' => 'Riders'];
$cardCanCancel = true;
$cardEditUrl = null;

portal_start('citycouncil', 'id-cards', 'ID card: ' . $cardRider['full_name'], [
    'subtitle' => $cardRider['id_number'] . ' · ' . ($cardRider['stage_name'] ?: 'no stage') . ($cardRow ? ' · card ' . $cardRow['serial'] : ' · no valid card'),
    'head' => '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . @filemtime(__DIR__ . '/../../assets/css/id-card.css') . '">',
]);
require __DIR__ . '/../../partials/id-card-workspace.php';
?>
<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/id-card.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/id-card.js') ?>"></script>
<?php portal_end();
