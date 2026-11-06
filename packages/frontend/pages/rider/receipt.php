<?php
// Printable receipt for one confirmed payment in this user's scope (see receipt_load()).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/permits.php';
require_role(['rider']);
$receipt = receipt_load((int) ($_GET['id'] ?? 0));
if (!$receipt) redirect('/pages/rider/payment-history.php');
portal_start('rider', 'payment-history', 'Receipt ' . $receipt['receipt_number'], [
    'head' => '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/receipt.css?v=' . @filemtime(__DIR__ . '/../../assets/css/receipt.css') . '">',
]);
?>
<div class="doc-layout is-two">
    <aside class="doc-rail">
        <?= action_rail([
            'Receipt' => [['label' => 'Print or save as PDF', 'icon' => 'print', 'tone' => 'primary', 'attrs' => ['data-print' => true]]],
            'Go to' => [['label' => 'Payments and receipts', 'icon' => 'arrow-left', 'href' => BASE_URL . '/pages/rider/payment-history.php']],
        ], 'Receipt actions') ?>
    </aside>
    <div><?php require __DIR__ . '/../../partials/receipt.php'; ?></div>
</div>
<?php portal_end();
