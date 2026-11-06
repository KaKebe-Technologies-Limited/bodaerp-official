<?php
// Shared controller for the three rider edit pages. $scopeSql / $scopeParam keep each role to its own riders.
require_once __DIR__ . '/admin_ui.php';
require_once __DIR__ . '/rider_records.php';

function rider_edit_page(string $sidebar, string $active, string $scopeSql, $scopeParam, bool $canPlace, string $listUrl, string $cardUrl): void {
    $riderId = (int) ($_GET['id'] ?? 0);
    $editRider = fetchOne("SELECT r.*, s.name AS stage_name, c.name AS city_name FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id
        WHERE r.id = ? AND r.deleted_at IS NULL AND $scopeSql", [$riderId, $scopeParam]);
    if (!$editRider) redirect($listUrl);
    $editErrors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $result = rider_record_save($editRider, $_POST, $_FILES, $canPlace);
        $editErrors = $result['errors'];
        if (!$editErrors) {
            $card = array_intersect($result['changed'], RIDER_CARD_FIELDS);
            flash('success', $result['changed'] ? 'Saved: ' . implode(', ', array_map(fn($f) => RIDER_FIELD_LABELS[$f], $result['changed'])) . '.' . ($card ? ' The ID card shows these details: make a new card with the reason "Details changed".' : '') : 'Nothing was changed.');
            redirect($card ? $cardUrl . $riderId : $_SERVER['REQUEST_URI']);
        }
    }
    $editPlace = $canPlace;
    $editStages = $canPlace ? array_column(fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$editRider['city_id']]), 'name', 'id') : [];
    $editBack = $listUrl;
    portal_start($sidebar, $active, 'Edit rider: ' . $editRider['full_name'], [
        'subtitle' => $editRider['id_number'] . ' · ' . ($editRider['stage_name'] ?: 'no stage') . ', ' . $editRider['city_name'],
    ]);
    require __DIR__ . '/../frontend/partials/rider-edit-form.php';
    portal_end();
}
