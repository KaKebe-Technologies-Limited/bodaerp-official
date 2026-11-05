<?php
// Expects (before include): $exportTableId, $exportFilename, optionally $exportTitle, $exportBtnClass
$exportBtnClass = $exportBtnClass ?? 'btn-outline-success';
$exportTitle = $exportTitle ?? '';
?>
<div class="dropdown d-inline-block no-print">
    <button class="btn <?= $exportBtnClass ?> dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-file-export me-1"></i>Export
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><button type="button" class="dropdown-item" data-call="exportTablePDF" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename, (string) $exportTitle])) ?>"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</button></li>
        <li><button type="button" class="dropdown-item" data-call="exportTableExcel" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename])) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel</button></li>
        <li><button type="button" class="dropdown-item" data-call="exportTableCSV" data-call-args="<?= h(js_json([(string) $exportTableId, (string) $exportFilename])) ?>"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</button></li>
        <li><hr class="dropdown-divider"></li>
        <li><button type="button" class="dropdown-item" data-print><i class="fas fa-print me-2 text-secondary"></i>Print</button></li>
    </ul>
</div>
