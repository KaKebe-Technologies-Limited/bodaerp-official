<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['chairperson']);
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['ids'] ?? [])), fn($i) => $i > 0))), 0, 50);
$sheetRiders = [];
if ($ids) {
    $place = implode(',', array_fill(0, count($ids), '?'));
    $sheetRiders = fetchAll("SELECT r.*, s.name AS stage_name, u.name AS registrar_name FROM riders r
        LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN users u ON u.id = r.created_by
        WHERE r.id IN ($place) AND r.deleted_at IS NULL AND r.stage_id = ?
          AND EXISTS (SELECT 1 FROM id_cards c WHERE c.rider_id = r.id AND c.status IN ('issued','printed','collected'))
        ORDER BY r.full_name", array_merge($ids, [(int) $_SESSION['stage_id']]));
}
$sheetCities = [];
foreach (fetchAll("SELECT * FROM cities") as $c) $sheetCities[$c['id']] = $c;
$sheetBackUrl = '/pages/chairperson/id-cards.php';
$sheetPostUrl = '/pages/chairperson/id-cards.php';

portal_start('chairperson', 'id-cards', 'Print ID cards', [
    'subtitle' => count($sheetRiders) . ' cards on A4, 10 to a sheet',
    'head' => '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/id-card.css?v=' . @filemtime(__DIR__ . '/../../assets/css/id-card.css') . '">',
]);
require __DIR__ . '/../../partials/id-card-sheet.php';
?>
<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/id-card.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/id-card.js') ?>"></script>
<?php portal_end();
