<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['city_admin']);
$cityId = (string) $_SESSION['city_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ids = posted_ids();
    if (!$ids) flash('error', 'Tick at least one rider in the list first.');
    else flash('success', id_card_bulk_printed($ids, 'r.city_id = ?', [$cityId]) . ' cards marked as printed.');
    redirect('/pages/citycouncil/id-cards.php' . query_with([]));
}
foreach (fetchAll("SELECT id FROM riders r WHERE r.city_id = ? AND r.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM id_cards c WHERE c.rider_id = r.id) LIMIT 200", [$cityId]) as $fresh) id_card_ensure((int) $fresh['id']);

portal_start('citycouncil', 'id-cards', 'ID cards', [
    'subtitle' => 'Every rider card in the city: what is waiting to be printed or handed over, and cards that no longer verify.',
]);
$listWhere = 'r.city_id = ?'; $listParams = [$cityId];
$listCardUrl = '/pages/citycouncil/id-card.php?id=';
$listSheetUrl = '/pages/citycouncil/id-card-sheet.php';
$listStages = array_column(fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]), 'name', 'id');
require __DIR__ . '/../../partials/id-card-list.php';
portal_end();
