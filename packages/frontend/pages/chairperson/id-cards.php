<?php
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/id_cards.php';
require_role(['chairperson']);
$stageId = (int) $_SESSION['stage_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ids = posted_ids();
    if (!$ids) flash('error', 'Tick at least one rider in the list first.');
    else flash('success', id_card_bulk_printed($ids, 'r.stage_id = ?', [$stageId]) . ' cards marked as printed.');
    redirect('/pages/chairperson/id-cards.php' . query_with([]));
}
foreach (fetchAll("SELECT id FROM riders r WHERE r.stage_id = ? AND r.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM id_cards c WHERE c.rider_id = r.id) LIMIT 200", [$stageId]) as $fresh) id_card_ensure((int) $fresh['id']);

portal_start('chairperson', 'id-cards', 'ID cards', [
    'subtitle' => 'Print cards, hand them over and replace lost ones for the riders at your stage.',
]);
$listWhere = 'r.stage_id = ?'; $listParams = [$stageId];
$listCardUrl = '/pages/chairperson/id-preview.php?id=';
$listSheetUrl = '/pages/chairperson/id-card-sheet.php';
$listStages = [];
require __DIR__ . '/../../partials/id-card-list.php';
portal_end();
