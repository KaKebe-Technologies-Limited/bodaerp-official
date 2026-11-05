<?php
// Import an existing rider register into this city. Step 1 checks the file and shows every problem by row;
// step 2 saves the rows that passed. Nothing is saved until the city admin confirms.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/rider_import.php';
$me = require_role(['city_admin']);
$riders = fn(int $n): string => $n . ($n === 1 ? ' rider' : ' riders');
$cityId = (string) $_SESSION['city_id'];

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rider-import-template.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map(fn($c) => $c[0], IMPORT_COLUMNS), ',', '"', '');
    fputcsv($out, array_map(fn($c) => $c[2], IMPORT_COLUMNS), ',', '"', '');
    fclose($out);
    exit;
}

$preview = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'check') {
        $file = $_FILES['register'] ?? null;
        if (!$file || (int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) flash('error', 'Choose the register file to upload.');
        elseif ((int) $file['size'] > IMPORT_MAX_BYTES) flash('error', 'The file is larger than 3 MB. Split it into smaller files.');
        else {
            try {
                $checked = import_check(import_read_cells($file['tmp_name'], (string) $file['name']), $cityId);
                $token = bin2hex(random_bytes(16));
                if (!ensure_dir(storage_path('imports'))) throw new RuntimeException('The server cannot store the checked file.');
                file_put_contents(import_stash_path($token), json_encode(['city' => $cityId, 'user' => (int) $me['user_id'], 'file' => basename((string) $file['name']),
                    'total' => $checked['total'], 'ready' => $checked['ready'], 'created' => time()]));
                $preview = $checked + ['token' => $token, 'file' => basename((string) $file['name'])];
            } catch (Throwable $e) {
                app_log('import', 'check failed: ' . $e->getMessage());
                flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The file could not be read. Save it as CSV and try again.');
            }
        }
        if (!$preview) redirect('/pages/citycouncil/import-riders.php');
    } elseif ($action === 'commit') {
        $path = import_stash_path((string) ($_POST['token'] ?? ''));
        $stash = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!$stash || $stash['city'] !== $cityId || (int) $stash['user'] !== (int) $me['user_id'] || $stash['created'] < time() - 3600) {
            flash('error', 'That check has expired. Upload the file again.');
        } elseif (!$stash['ready']) {
            flash('error', 'No rows passed the check, so nothing was imported.');
        } else {
            try {
                $importId = import_commit($stash['ready'], $cityId, (int) $me['user_id'], $stash['file'], (int) $stash['total']);
                $count = count($stash['ready']);
                audit_log('CREATE', 'rider_import', (string) $importId, "Imported $count riders from {$stash['file']}");
                flash('success', $riders($count) . ' imported. Each one has a permit number; print ID cards from the stage pages.');
            } catch (Throwable $e) {
                app_log('import', 'commit failed: ' . $e->getMessage());
                flash('error', 'The import stopped and nothing was saved: a rider in the file was registered by someone else meanwhile. Upload the file again to see which.');
            }
        }
        @unlink($path);
        redirect('/pages/citycouncil/import-riders.php');
    }
}

foreach (glob(storage_path('imports') . '/*.json') ?: [] as $old) if (filemtime($old) < time() - 3600) @unlink($old);
$history = fetchAll("SELECT i.*, u.name AS by_name FROM rider_imports i LEFT JOIN users u ON u.id = i.imported_by WHERE i.city_id = ? ORDER BY i.created_at DESC LIMIT 10", [$cityId]);

portal_start('citycouncil', 'import-riders', 'Import riders', [
    'subtitle' => 'Bring an existing rider register into ' . ($_SESSION['city_name'] ?? 'your city'),
]);
?>
<?php if ($preview): ?>
<section class="card mb-4">
    <div class="card-body">
        <h2 class="section-title">Check of <?= h($preview['file']) ?></h2>
        <?php if ($preview['missing_columns']): ?>
            <div class="alert alert-danger" role="alert">The file is missing these columns: <strong><?= h(implode(', ', $preview['missing_columns'])) ?></strong>. Download the template to see the expected headings.</div>
        <?php else: ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-4"><div class="stat-hero-card tone-blue"><div><div class="stat-hero-value"><?= (int) $preview['total'] ?></div><div class="stat-hero-label">Rows in the file</div></div></div></div>
                <div class="col-sm-4"><div class="stat-hero-card tone-green"><div><div class="stat-hero-value"><?= count($preview['ready']) ?></div><div class="stat-hero-label">Ready to import</div></div></div></div>
                <div class="col-sm-4"><div class="stat-hero-card tone-red"><div><div class="stat-hero-value"><?= count($preview['problems']) ?></div><div class="stat-hero-label">Rows with problems</div></div></div></div>
            </div>
            <?php if ($preview['problems']): ?>
                <h3 class="h6 mt-4">Rows that will be skipped</h3>
                <p class="text-muted small">Fix these in your file and import it again later; rows already imported are then reported as already registered.</p>
                <div class="table-responsive mb-4 scroll-panel">
                    <table class="table table-sm align-middle">
                        <thead><tr><th scope="col">Row</th><th scope="col">Problem</th></tr></thead>
                        <tbody>
                        <?php foreach ($preview['problems'] as $rowNo => $problems): ?>
                            <tr><td><?= (int) $rowNo ?></td><td><?= h(implode(' ', $problems)) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if ($preview['ready']): ?>
                <form method="post" data-confirm="Import <?= h($riders(count($preview['ready']))) ?> into <?= h($_SESSION['city_name'] ?? 'this city') ?>?" data-confirm-ok="Import riders">
                    <?= csrf_field() ?><input type="hidden" name="action" value="commit"><input type="hidden" name="token" value="<?= h($preview['token']) ?>">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" data-busy-label="Importing">Import <?= h($riders(count($preview['ready']))) ?></button>
                        <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/pages/citycouncil/import-riders.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<div class="layout-thirds">
    <section class="card">
        <div class="card-body">
            <h2 class="section-title">Upload a register</h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?><input type="hidden" name="action" value="check">
                <div class="mb-3">
                    <label class="form-label" for="register">CSV or Excel file (.csv, .xlsx), up to <?= IMPORT_MAX_ROWS ?> riders</label>
                    <input class="form-control" type="file" id="register" name="register" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                </div>
                <button class="btn btn-primary" data-busy-label="Checking">Check the file</button>
            </form>
            <p class="text-muted small mt-3 mb-0">Nothing is saved at this step. You see every problem first, then choose to import the rows that passed.</p>
        </div>
    </section>

    <aside class="card">
        <div class="card-body">
            <h2 class="section-title">How the file should look</h2>
            <p class="small">One rider per row, with these headings in the first row. Required: <strong><?= h(implode(', ', array_map(fn($c) => $c[0], array_filter(IMPORT_COLUMNS, fn($c) => $c[1])))) ?></strong>.</p>
            <p class="small">The stage must already exist in Manage stages. Riders with a "Permit valid until" date in the future become active; the rest wait for their first payment.</p>
            <a class="btn btn-outline-primary btn-sm" href="?template=1"><i class="fas fa-download me-1" aria-hidden="true"></i>Download the template</a>
        </div>
    </aside>
</div>

<?php if ($history): ?>
<section class="card mt-4">
    <div class="card-body">
        <h2 class="section-title">Earlier imports</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th scope="col">Date</th><th scope="col">File</th><th scope="col">By</th><th scope="col" class="text-end">Imported</th><th scope="col" class="text-end">Skipped</th></tr></thead>
                <tbody>
                <?php foreach ($history as $imp): ?>
                    <tr><td><?= h(formatDate($imp['created_at'])) ?></td><td><?= h($imp['file_name']) ?></td><td><?= h($imp['by_name']) ?></td><td class="text-end"><?= (int) $imp['rows_created'] ?></td><td class="text-end"><?= (int) $imp['rows_skipped'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>
<?php portal_end(); ?>
