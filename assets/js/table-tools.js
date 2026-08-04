// ============================================================
// BodaERP — Shared table export toolkit (CSV / Excel / PDF / Print)
// Any <table id="..."> can be exported. Mark cells that shouldn't
// appear in exports (e.g. an Actions column) with class="no-export".
// ============================================================

function getExportRows(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return [];
    const rows = [];
    table.querySelectorAll('tr').forEach(tr => {
        const cells = Array.from(tr.querySelectorAll('th,td')).filter(c => !c.classList.contains('no-export'));
        if (cells.length) rows.push(cells.map(c => c.textContent.trim().replace(/\s+/g, ' ')));
    });
    return rows;
}

function downloadBlob(content, filename, mime) {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function exportTableCSV(tableId, filename) {
    const rows = getExportRows(tableId);
    if (!rows.length) { alert('Nothing to export.'); return; }
    const csv = rows.map(r => r.map(v => '"' + v.replace(/"/g, '""') + '"').join(',')).join('\r\n');
    downloadBlob('﻿' + csv, filename.endsWith('.csv') ? filename : filename + '.csv', 'text/csv;charset=utf-8;');
}

function exportTableExcel(tableId, filename) {
    const rows = getExportRows(tableId);
    if (!rows.length) { alert('Nothing to export.'); return; }
    let html = '<table border="1">';
    rows.forEach((r, i) => {
        const tag = i === 0 ? 'th' : 'td';
        html += '<tr>' + r.map(v => `<${tag}>${v.replace(/&/g, '&amp;').replace(/</g, '&lt;')}</${tag}>`).join('') + '</tr>';
    });
    html += '</table>';
    const doc = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Sheet1</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>${html}</body></html>`;
    downloadBlob(doc, filename.endsWith('.xls') ? filename : filename + '.xls', 'application/vnd.ms-excel');
}

function exportTablePDF(tableId, filename, title) {
    const rows = getExportRows(tableId);
    if (!rows.length) { alert('Nothing to export.'); return; }
    if (!window.jspdf) { alert('PDF library failed to load.'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: rows[0].length > 6 ? 'landscape' : 'portrait' });
    doc.setFontSize(14);
    doc.text(title || 'Report', 14, 15);
    doc.setFontSize(8);
    doc.setTextColor(120);
    doc.text('Generated ' + new Date().toLocaleString(), 14, 21);
    doc.autoTable({
        head: [rows[0]],
        body: rows.slice(1),
        startY: 26,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [13, 110, 253] },
        margin: { left: 14, right: 14 },
    });
    doc.save(filename.endsWith('.pdf') ? filename : filename + '.pdf');
}
