<?php
// Card register for a stage (chairperson) or a city (city admin): every rider with the state of their card, filters
// for what needs doing, and bulk printing on A4 or marking printed. The page has already called portal_start().
// Expects: $listWhere (SQL on riders r), $listParams, $listCardUrl (prefix, the rider id is appended),
// $listSheetUrl, $listStages (id => name, empty for a chairperson).
$listFilters = [
    'all'      => ['All riders', '1=1'],
    'to_print' => ['To print', "c.status = 'issued'"],
    'to_hand'  => ['Printed, not handed over', "c.status = 'printed'"],
    'with'     => ['With the rider', "c.status = 'collected'"],
    'no_card'  => ['No valid card', 'c.id IS NULL'],
    'stale'    => ['Wrong expiry date on the card', "c.id IS NOT NULL AND r.expiry_date IS NOT NULL AND (c.valid_until IS NULL OR c.valid_until <> r.expiry_date)"],
    'no_photo' => ['No photo', "(r.photo_path IS NULL OR r.photo_path = '')"],
];
$listFilter = isset($listFilters[$_GET['show'] ?? '']) ? $_GET['show'] : 'all';
$listJoin = "LEFT JOIN stages s ON s.id = r.stage_id
    LEFT JOIN id_cards c ON c.id = (SELECT MAX(x.id) FROM id_cards x WHERE x.rider_id = r.id AND x.status IN ('issued','printed','collected'))";
$listBase = "FROM riders r $listJoin WHERE r.deleted_at IS NULL AND ($listWhere)";
$listQ = trim((string) ($_GET['q'] ?? ''));
$where = ''; $params = $listParams;
if ($listQ !== '') {
    $where .= " AND (r.full_name LIKE ? OR r.id_number LIKE ? OR r.bike_plate LIKE ? OR c.serial LIKE ?)";
    array_push($params, "%$listQ%", "%$listQ%", "%$listQ%", "%$listQ%");
}
$listStage = (int) ($_GET['stage'] ?? 0);
if ($listStages && isset($listStages[$listStage])) { $where .= " AND r.stage_id = ?"; $params[] = $listStage; }

$counts = fetchOne("SELECT COUNT(*) AS `all`, " . implode(', ', array_map(fn($k, $f) => "SUM(CASE WHEN {$f[1]} THEN 1 ELSE 0 END) AS `$k`", array_keys(array_slice($listFilters, 1)), array_slice($listFilters, 1))) . " $listBase$where", $params);
$total = (int) $counts[$listFilter];
$pg = paginate($total, 50);
$order = sort_sql(['name' => 'r.full_name', 'permit' => 'r.id_number', 'issued' => 'c.issued_at', 'expiry' => 'r.expiry_date'], 'name', 'asc');
$rows = fetchAll("SELECT r.id, r.full_name, r.id_number, r.bike_plate, r.photo_path, r.status, r.expiry_date, s.name AS stage_name,
    c.id AS card_id, c.serial, c.status AS card_status, c.valid_until, c.issued_at, c.printed_at, c.collected_at, c.print_count
    $listBase$where AND {$listFilters[$listFilter][1]} ORDER BY $order LIMIT " . (int) $pg['per_page'] . " OFFSET " . (int) $pg['offset'], $params);
?>
<div class="row g-3 mb-4">
    <?php foreach (['to_print' => ['fa-print', 'warning'], 'to_hand' => ['fa-hand-holding', 'info'], 'no_card' => ['fa-id-card', 'danger'], 'stale' => ['fa-calendar-xmark', 'secondary']] as $key => [$icon, $tone]): ?>
    <div class="col-6 col-lg-3">
        <a class="card h-100 text-decoration-none" href="<?= h(query_with(['show' => $key, 'page' => null])) ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon bg-<?= $tone ?>-subtle text-<?= $tone ?>-emphasis rounded-3 d-inline-grid" style="width:44px;height:44px;place-items:center;"><i class="fas <?= $icon ?>" aria-hidden="true"></i></span>
                <div><div class="h4 fw-bold mb-0 text-dark"><?= number_format((int) $counts[$key]) ?></div><div class="small text-muted"><?= h($listFilters[$key][0]) ?></div></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card"><div class="card-body">
    <form method="get" class="row g-2 align-items-end mb-3" role="search">
        <input type="hidden" name="show" value="<?= h($listFilter) ?>">
        <div class="col-md-5"><label class="form-label small" for="cardSearch">Search</label>
            <input type="search" class="form-control" id="cardSearch" name="q" value="<?= h($listQ) ?>" placeholder="Name, permit number, plate or card serial"></div>
        <?php if ($listStages): ?>
        <div class="col-md-4"><label class="form-label small" for="cardStage">Stage</label>
            <select class="form-select" id="cardStage" name="stage" data-autosubmit>
                <option value="">All stages</option>
                <?php foreach ($listStages as $sid => $sname): ?><option value="<?= (int) $sid ?>"<?= $listStage === (int) $sid ? ' selected' : '' ?>><?= h($sname) ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">Search</button></div>
    </form>

    <div class="d-flex flex-wrap gap-2 mb-3" role="tablist" aria-label="Which cards to show">
        <?php foreach ($listFilters as $key => [$label]): ?>
        <a class="btn btn-sm <?= $key === $listFilter ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= h(query_with(['show' => $key === 'all' ? null : $key, 'page' => null])) ?>"<?= $key === $listFilter ? ' aria-current="true"' : '' ?>><?= h($label) ?> <span class="badge bg-light text-dark ms-1"><?= number_format((int) $counts[$key]) ?></span></a>
        <?php endforeach; ?>
    </div>

    <form method="post" id="cardBulk">
        <?= csrf_field() ?><input type="hidden" name="bulk_action" value="printed">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="submit" class="btn btn-sm btn-primary" formaction="<?= h(BASE_URL . $listSheetUrl) ?>" formmethod="get" formnovalidate><i class="fas fa-print me-1" aria-hidden="true"></i>Print selected on A4</button>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-check me-1" aria-hidden="true"></i>Mark selected as printed</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input" data-check-all="ids[]" aria-label="Select every rider on this page"></th>
                    <th><?= sort_link('name', 'Rider', 'name', 'asc') ?></th>
                    <th><?= sort_link('permit', 'Permit number', 'name', 'asc') ?></th>
                    <?php if ($listStages): ?><th>Stage</th><?php endif; ?>
                    <th>Card</th>
                    <th>Card status</th>
                    <th><?= sort_link('expiry', 'Permit valid until', 'name', 'asc') ?></th>
                    <th class="text-end">Open</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= (int) $row['id'] ?>" aria-label="Select <?= h($row['full_name']) ?>"<?= $row['card_id'] ? '' : ' disabled' ?>></td>
                        <td><?= person_cell($row['photo_path'], $row['full_name'], h($row['bike_plate'] ?: 'No plate')) ?></td>
                        <td><code><?= h($row['id_number']) ?></code></td>
                        <?php if ($listStages): ?><td><?= h($row['stage_name'] ?: '–') ?></td><?php endif; ?>
                        <td><?= $row['serial'] ? '<code>' . h($row['serial']) . '</code>' : '–' ?></td>
                        <td><?= id_card_status_badge($row['card_status']) ?>
                            <?php if ($row['card_id'] && $row['expiry_date'] && $row['valid_until'] !== $row['expiry_date']): ?><br><small class="text-warning-emphasis">Expiry date on card is out of date</small><?php endif; ?>
                            <?php if (empty($row['photo_path'])): ?><br><small class="text-danger">No photo</small><?php endif; ?></td>
                        <td><?= formatDate($row['expiry_date'], 'd M Y') ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= h(BASE_URL . $listCardUrl . (int) $row['id']) ?>" aria-label="Open the ID card of <?= h($row['full_name']) ?>"><i class="fas fa-id-card" aria-hidden="true"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="<?= $listStages ? 8 : 7 ?>"><?= empty_state('fa-id-card', 'No riders match', $listQ !== '' ? 'Try another name, number or plate.' : 'Nothing is waiting in this list.') ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?= render_pagination($pg) ?>
</div></div>
