<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role(['city_admin']);
$active = 'payments_management';
$cityId = $_SESSION['city_id'];

$stageFilter = (int) ($_GET['stage'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;

$where = "WHERE p.city_id = :city"; $params = ['city' => $cityId];
if ($stageFilter) { $where .= " AND r.stage_id = :stage"; $params['stage'] = $stageFilter; }
if ($statusFilter) { $where .= " AND p.status = :status"; $params['status'] = $statusFilter; }
if ($dateFrom) { $where .= " AND p.paid_at >= :from"; $params['from'] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $where .= " AND p.paid_at <= :to"; $params['to'] = $dateTo . ' 23:59:59'; }

if (isset($_GET['export'])) {
    $exportRows = fetchAll("SELECT p.*, r.full_name, s.name AS stage_name FROM payments p
        JOIN riders r ON r.id = p.rider_id LEFT JOIN stages s ON s.id = r.stage_id $where ORDER BY p.paid_at DESC", $params);
    $filename = 'payment_transactions_' . date('Ymd_His');
    if ($_GET['export'] === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename.xls\"");
        echo "<table border='1'><tr><th>Receipt #</th><th>Rider</th><th>Stage</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr>";
        foreach ($exportRows as $t) {
            echo '<tr><td>' . h($t['receipt_number']) . '</td><td>' . h($t['full_name']) . '</td><td>' . h($t['stage_name']) . '</td><td>' . number_format($t['amount']) . '</td><td>' . formatDate($t['paid_at']) . '</td><td>' . h($t['payment_method']) . '</td><td>' . h($t['status']) . "</td></tr>\n";
        }
        echo '</table>';
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Receipt #', 'Rider', 'Stage', 'Amount', 'Date', 'Method', 'Status']);
    foreach ($exportRows as $t) {
        fputcsv($out, [$t['receipt_number'], $t['full_name'], $t['stage_name'], $t['amount'], formatDate($t['paid_at']), $t['payment_method'], $t['status']]);
    }
    fclose($out);
    exit;
}

$total = (int) fetchValue("SELECT COUNT(*) FROM payments p JOIN riders r ON r.id=p.rider_id $where", $params);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$transactions = fetchAll("SELECT p.*, r.full_name, s.name AS stage_name FROM payments p
    JOIN riders r ON r.id = p.rider_id LEFT JOIN stages s ON s.id = r.stage_id
    $where ORDER BY p.paid_at DESC LIMIT $perPage OFFSET $offset", $params);
$stages = fetchAll("SELECT id, name FROM stages WHERE city_id = ? ORDER BY name", [$cityId]);

function qsMerge2(array $overrides): string { return '?' . http_build_query(array_merge($_GET, $overrides)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $pageTitle = 'Payment Transactions - BodaERP'; require __DIR__ . '/../../includes/partials/head-assets.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../includes/partials/sidebar-citycouncil.php'; ?>

<div class="main-content" id="mainContent">
    <header class="top-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light d-lg-none" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h5 class="mb-0 fw-bold"><span class="text-gradient-blue">Payment Transactions</span></h5>
            </div>
        </div>
    </header>

    <div class="content-area">
        <div class="card mb-4">
            <div class="card-body">
                <form class="row g-3" method="get">
                    <div class="col-md-3"><label class="form-label fw-semibold">Date From</label><input type="date" class="form-control" name="from" value="<?= h($dateFrom) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Date To</label><input type="date" class="form-control" name="to" value="<?= h($dateTo) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Stage</label>
                        <select class="form-select" name="stage">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $stageFilter===(int)$s['id']?'selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="Confirmed" <?= $statusFilter==='Confirmed'?'selected':'' ?>>Confirmed</option>
                            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending</option>
                            <option value="Failed" <?= $statusFilter==='Failed'?'selected':'' ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply Filters</button></div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-2 no-print">
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-file-export me-1"></i>Export All Matching (<?= number_format($total) ?>)
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= qsMerge2(['export'=>'csv']) ?>"><i class="fas fa-file-csv me-2 text-primary"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="<?= qsMerge2(['export'=>'excel']) ?>"><i class="fas fa-file-excel me-2 text-success"></i>Excel</a></li>
                </ul>
            </div>
            <?php $exportTableId='transactionsTable'; $exportFilename='payment_transactions_page'.$page; $exportTitle='Payment Transactions (this page)'; require __DIR__ . '/../../includes/partials/export-toolbar.php'; ?>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="transactionsTable">
                        <thead><tr><th>Receipt #</th><th>Rider</th><th>Stage</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$transactions): ?><tr><td colspan="7" class="text-center py-4 text-muted">No transactions found</td></tr><?php endif; ?>
                        <?php foreach ($transactions as $t): $sc = ['Confirmed'=>'bg-success','Pending'=>'bg-warning text-dark','Failed'=>'bg-danger']; ?>
                            <tr>
                                <td><code><?= h($t['receipt_number']) ?></code></td>
                                <td><?= h($t['full_name']) ?></td>
                                <td><?= h($t['stage_name']) ?></td>
                                <td><?= h(formatCurrency($t['amount'])) ?></td>
                                <td><?= formatDate($t['paid_at']) ?></td>
                                <td><?= h($t['payment_method']) ?></td>
                                <td><span class="badge <?= $sc[$t['status']] ?>"><?= h($t['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Showing <?= $total ? $offset+1 : 0 ?>-<?= min($offset+$perPage,$total) ?> of <?= number_format($total) ?> transactions</small>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge2(['page'=>$page-1]) ?>">Prev</a></li>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>"><a class="page-link" href="<?= qsMerge2(['page'=>$page+1]) ?>">Next</a></li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/partials/scripts-footer.php'; ?>
<script>
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('active');
    });
    document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    });
</script>
</body>
</html>
