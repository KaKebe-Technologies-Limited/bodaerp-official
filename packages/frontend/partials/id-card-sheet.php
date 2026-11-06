<?php
// A4 print sheet: up to 10 cards a page, all fronts first, then the backs with the two columns swapped so the
// sheet can go back through the printer for the other side (long-edge flip). The page has called portal_start().
// Expects: $sheetRiders (id_card_rider() rows in scope), $sheetCities (city id => row), $sheetBackUrl, $sheetPostUrl.
$cardCfg = id_card_settings();
$cardZoom = false;
$sheetPages = array_chunk($sheetRiders, 10);
$sheetCards = [];
foreach ($sheetRiders as $sr) $sheetCards[(int) $sr['id']] = id_card_current((int) $sr['id']);
?>
<?php if ($sheetRiders): ?>
<form method="post" action="<?= h(BASE_URL . $sheetPostUrl) ?>" id="sheetMarkPrinted" hidden><?= csrf_field() ?>
    <?php foreach ($sheetRiders as $sr): ?><input type="hidden" name="ids[]" value="<?= (int) $sr['id'] ?>"><?php endforeach; ?>
</form>
<?php endif; ?>
<div class="doc-layout is-two">
<aside class="doc-rail">
    <?= action_rail([
        'Print' => $sheetRiders ? [
            ['label' => 'Print ' . count($sheetRiders) . ' cards', 'icon' => 'print', 'tone' => 'primary', 'attrs' => ['data-card-sheet-print' => true]],
            ['label' => 'Mark these as printed', 'icon' => 'check', 'form' => 'sheetMarkPrinted'],
        ] : [],
        'Go to' => [['label' => 'Back to ID cards', 'icon' => 'arrow-left', 'href' => BASE_URL . $sheetBackUrl]],
    ], 'Sheet actions') ?>
</aside>
<div>
<div class="alert alert-info small no-print">Print at 100% scale (not "fit to page") with headers and footers off. Print the front pages, put the sheets back in the tray face down with the top edge first, then print the back pages. Cut along the dashed lines.</div>

<?php if (!$sheetRiders): ?>
    <div class="card"><div class="card-body"><?= empty_state('fa-print', 'No cards selected', 'Tick riders in the ID cards list, then choose Print selected on A4.') ?></div></div>
<?php endif; ?>

<?php foreach (['front' => 'Fronts', 'back' => 'Backs'] as $cardSide => $sideTitle): ?>
    <?php foreach ($sheetPages as $pageNo => $pageRiders):
        $slots = $pageRiders;
        if ($cardSide === 'back') {
            $slots = [];
            foreach (array_chunk($pageRiders, 2) as $pair) { $slots[] = $pair[1] ?? null; $slots[] = $pair[0]; }
        } ?>
    <div class="id-sheet-heading"><span><?= $sideTitle ?>, sheet <?= $pageNo + 1 ?> of <?= count($sheetPages) ?></span><span><?= count($pageRiders) ?> cards</span></div>
    <div class="id-sheet-page" style="--card-colour: <?= h($cardCfg['colour']) ?>;">
        <?php foreach ($slots as $cardRider):
            if ($cardRider === null) { echo '<div aria-hidden="true"></div>'; continue; }
            $cardCity = $sheetCities[$cardRider['city_id']] ?? [];
            $cardRow = $sheetCards[(int) $cardRider['id']];
            require __DIR__ . '/id-card.php';
        endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endforeach; ?>
</div>
</div>
