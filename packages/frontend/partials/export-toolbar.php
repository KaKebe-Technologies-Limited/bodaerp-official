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
        <li><a class="dropdown-item" href="#" onclick="exportTablePDF('<?= h($exportTableId) ?>','<?= h($exportFilename) ?>','<?= h($exportTitle) ?>');return false;"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
        <li><a class="dropdown-item" href="#" onclick="exportTableExcel('<?= h($exportTableId) ?>','<?= h($exportFilename) ?>');return false;"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
        <li><a class="dropdown-item" href="#" onclick="exportTableCSV('<?= h($exportTableId) ?>','<?= h($exportFilename) ?>');return false;"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="window.print();return false;"><i class="fas fa-print me-2 text-secondary"></i>Print</a></li>
    </ul>
</div>
