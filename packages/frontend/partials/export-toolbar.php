<?php
// Expects (before include): $exportTableId, $exportFilename, optionally $exportTitle, $exportBtnClass
$exportBtnClass = $exportBtnClass ?? 'btn-outline-success';
$exportTitle = $exportTitle ?? '';
?>
<div class="dropdown d-inline-block no-print">
    <button class="btn <?= $exportBtnClass ?> dropdown-toggle" type="button" data-bs-toggle="dropdown">Export
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button type="button" class="dropdown-item" data-call="exportTablePDF" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename, (string) $exportTitle])) ?>">PDF</button></li>
        <li><button type="button" class="dropdown-item" data-call="exportTableExcel" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename])) ?>">Excel</button></li>
        <li><button type="button" class="dropdown-item" data-call="exportTableCSV" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename])) ?>">CSV</button></li>
        <li><hr class="dropdown-divider"></li>
        <li><button type="button" class="dropdown-item" data-print>Print</button></li>
    </ul>
</div>
