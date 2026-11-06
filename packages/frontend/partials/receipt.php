<?php
// Official receipt for one confirmed permit payment, on A5 so it prints on a home printer or saves as PDF.
// Expects $receipt: payment row joined with rider, stage, city and collector (receipt_load()).
$rcCity = (string) $receipt['city_name'];
$rcIssuer = preg_match('/council$/i', $rcCity) ? $rcCity : $rcCity . ' Council';
$rcLogo = !empty($receipt['city_logo']) ? media_url($receipt['city_logo']) : brand_logo_url();
$rcFee = (float) $receipt['amount'] - (float) $receipt['late_fee'];
?>
<article class="receipt-sheet" aria-label="Receipt <?= h($receipt['receipt_number']) ?>">
    <header class="receipt-head">
        <img src="<?= h($rcLogo) ?>" alt="<?= h($rcCity) ?> emblem" data-hide-broken>
        <div>
            <div class="receipt-country"><?= h(get_setting('id_card_country_line', 'The Republic of Uganda')) ?></div>
            <div class="receipt-issuer"><?= h($rcIssuer) ?></div>
            <div class="receipt-title">Official receipt: boda boda permit fee</div>
        </div>
        <div class="receipt-no"><span>Receipt no.</span><strong><?= h($receipt['receipt_number']) ?></strong></div>
    </header>
    <dl class="receipt-grid">
        <div><dt>Received from</dt><dd><?= h($receipt['full_name']) ?></dd></div>
        <div><dt>Permit number</dt><dd><code><?= h($receipt['id_number']) ?></code></dd></div>
        <div><dt>Stage</dt><dd><?= h($receipt['stage_name'] ?: '–') ?></dd></div>
        <div><dt>Motorcycle</dt><dd><?= h($receipt['bike_plate'] ?: '–') ?></dd></div>
        <div><dt>Date paid</dt><dd><?= h(date('d M Y, H:i', strtotime((string) $receipt['paid_at']))) ?></dd></div>
        <div><dt>Permit year</dt><dd><?= h($receipt['fiscal_year']) ?></dd></div>
        <div><dt>Paid by</dt><dd><?= h($receipt['payment_method']) ?><?= $receipt['payer_phone'] ? ' (phone ending ' . h(substr((string) $receipt['payer_phone'], -3)) . ')' : '' ?></dd></div>
        <div><dt>Reference</dt><dd><?= h($receipt['transaction_reference'] ?: ($receipt['iotec_transaction_id'] ?: '–')) ?></dd></div>
    </dl>
    <table class="receipt-lines">
        <thead><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
            <tr><td>Annual permit fee, <?= h($receipt['fiscal_year']) ?></td><td class="text-end"><?= h(formatCurrency($rcFee)) ?></td></tr>
            <?php if ((float) $receipt['late_fee'] > 0): ?><tr><td>Late fee</td><td class="text-end"><?= h(formatCurrency((float) $receipt['late_fee'])) ?></td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><th>Total paid</th><th class="text-end"><?= h(formatCurrency((float) $receipt['amount'])) ?></th></tr></tfoot>
    </table>
    <footer class="receipt-foot">
        <div><?= $receipt['collector_name'] ? 'Collected by ' . h($receipt['collector_name']) : 'Paid by mobile money' ?>. Permit valid until <?= formatDate($receipt['expiry_date']) ?> at the time of printing.</div>
        <div>Check the permit at any time: <?= h(preg_replace('#^https?://#', '', app_url('verify.php'))) ?> with permit number <?= h($receipt['id_number']) ?>.</div>
        <div class="receipt-stamp">Printed <?= date('d M Y, H:i') ?> · <?= h(get_setting('site_name', 'BodaERP')) ?></div>
    </footer>
</article>
