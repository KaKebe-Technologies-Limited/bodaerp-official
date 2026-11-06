<?php
// Super Admin → Media Library: images and PDFs used on public pages, in emails and for branding.
// Files are stored under UPLOAD_PATH (outside git) and served through serve_upload.php.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_once __DIR__ . '/../../../backend/uploads.php';
require_role(['super_admin']);

/** Where a file is still referenced, so it is not deleted while in use. */
$usedIn = function (string $path): array {
    $like = '%' . $path . '%';
    $where = [];
    if (fetchValue("SELECT COUNT(*) FROM pages WHERE deleted_at IS NULL AND (body LIKE ? OR og_image = ?)", [$like, $path])) $where[] = 'a page';
    if (fetchValue("SELECT COUNT(*) FROM settings WHERE value = ?", [$path])) $where[] = 'site settings (logo or sharing image)';
    if (fetchValue("SELECT COUNT(*) FROM email_templates WHERE body_html LIKE ?", [$like])) $where[] = 'an email template';
    return $where;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'upload') {
        $files = $_FILES['files'] ?? null;
        $ok = 0; $errs = [];
        foreach ((array) ($files['name'] ?? []) as $i => $name) {
            if ($name === '') continue;
            $one = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
            $r = store_upload($one, 'media', UPLOAD_IMAGE_TYPES + UPLOAD_DOCUMENT_TYPES, 5 * 1024 * 1024);
            if (!$r['ok']) { $errs[] = $name . ': ' . $r['error']; continue; }
            runQuery("INSERT INTO media (path, original_name, mime, size_bytes, width, height, alt_text, uploaded_by) VALUES (?,?,?,?,?,?,?,?)",
                [$r['path'], mb_substr($name, 0, 255), $r['mime'], $r['size'], $r['width'], $r['height'], null, $_SESSION['user_id']]);
            $ok++;
        }
        if ($ok) { audit_log('UPLOAD', 'media', null, "$ok file(s) uploaded"); flash('success', "$ok file" . ($ok === 1 ? '' : 's') . ' uploaded.'); }
        if ($errs) flash('error', implode(' ', $errs));
        if (!$ok && !$errs) flash('error', 'Choose at least one file.');
    } elseif ($action === 'alt') {
        runQuery("UPDATE media SET alt_text = ? WHERE id = ?", [mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 255) ?: null, (int) $_POST['id']]);
        flash('success', 'Description saved.');
    } elseif ($action === 'delete' || $action === 'bulk_delete') {
        $ids = $action === 'delete' ? [(int) ($_POST['id'] ?? 0)] : posted_ids();
        $deleted = 0; $kept = [];
        foreach ($ids as $mid) {
            $m = fetchOne("SELECT * FROM media WHERE id = ?", [$mid]);
            if (!$m) continue;
            if ($where = $usedIn($m['path'])) { $kept[] = $m['original_name'] . ' (used in ' . implode(', ', $where) . ')'; continue; }
            delete_upload($m['path']);
            runQuery("DELETE FROM media WHERE id = ?", [$mid]);
            $deleted++;
        }
        if ($deleted) { audit_log('DELETE', 'media', implode(',', $ids), "$deleted file(s) deleted"); flash('success', "$deleted file" . ($deleted === 1 ? '' : 's') . ' deleted.'); }
        if ($kept) flash('error', 'Not deleted because still in use: ' . implode('; ', $kept) . '.');
    }
    redirect('/pages/superadmin/media.php' . query_with([]));
}

$type = in_array($_GET['type'] ?? '', ['image', 'pdf'], true) ? $_GET['type'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = 'WHERE 1=1'; $params = [];
if ($type === 'image') $where .= " AND m.mime LIKE 'image/%'";
if ($type === 'pdf') $where .= " AND m.mime = 'application/pdf'";
if ($q !== '') { $where .= ' AND (m.original_name LIKE ? OR m.alt_text LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$pg = paginate((int) fetchValue("SELECT COUNT(*) FROM media m $where", $params), 24);
$mediaFiles = fetchAll("SELECT m.*, u.name AS uploader FROM media m LEFT JOIN users u ON u.id = m.uploaded_by $where ORDER BY m.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$fmtSize = fn(int $b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';

portal_start('superadmin', 'media', 'Media library', [
    'subtitle' => 'Images and PDFs for pages, emails and branding',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Media Library' => null],
]);
?>
<div class="card mb-3"><div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="upload">
        <div class="col-md-8"><label class="form-label fw-semibold" for="files">Upload files</label><input class="form-control" type="file" id="files" name="files[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" data-max-mb="5"><div class="form-text">JPG, PNG, WebP, GIF or PDF, up to 5 MB each. You can also drag images straight into any page editor.</div></div>
        <div class="col-md-4"><button class="btn btn-primary w-100" data-busy-label="Uploading">Upload</button></div>
    </form>
</div></div>

<div class="card"><div class="card-body">
    <form class="row g-2 mb-3" method="get">
        <div class="col-md-5"><input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search by file name or description" aria-label="Search"></div>
        <div class="col-md-3"><select class="form-select" name="type" aria-label="File type" data-autosubmit><option value="">All files</option><option value="image"<?= $type === 'image' ? ' selected' : '' ?>>Images</option><option value="pdf"<?= $type === 'pdf' ? ' selected' : '' ?>>PDF documents</option></select></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div>
    </form>
    <?php if (!$mediaFiles): echo empty_state('fa-photo-film', 'No files yet', 'Upload an image or PDF above.'); else: ?>
    <form method="post" id="bulkMedia" data-confirm="Delete the selected files? Files still used on a page, in an email or in settings are kept.">
        <?= csrf_field() ?><input type="hidden" name="action" value="bulk_delete">
        <button class="btn btn-sm btn-outline-danger mb-3">Delete selected</button>
    </form>
    <div class="row g-3">
        <?php foreach ($mediaFiles as $m): $url = media_url($m['path']); $isImg = str_starts_with($m['mime'], 'image/'); ?>
        <div class="col-6 col-md-4 col-xl-3">
            <div class="media-tile">
                <div class="thumb"><?= $isImg ? '<img src="' . h($url) . '" alt="' . h($m['alt_text'] ?? '') . '" loading="lazy">' : '<i class="fas fa-file-pdf fa-3x text-danger" aria-hidden="true"></i>' ?></div>
                <div class="meta">
                    <div class="form-check mb-1"><input class="form-check-input" type="checkbox" form="bulkMedia" name="ids[]" value="<?= (int) $m['id'] ?>" id="sel<?= (int) $m['id'] ?>"><label class="form-check-label text-truncate d-block fw-semibold" for="sel<?= (int) $m['id'] ?>" title="<?= h($m['original_name']) ?>"><?= h($m['original_name']) ?></label></div>
                    <div class="text-muted"><?= h($fmtSize((int) $m['size_bytes'])) ?><?= $m['width'] ? ' · ' . (int) $m['width'] . '×' . (int) $m['height'] : '' ?> · <?= h(formatDate($m['created_at'])) ?></div>
                    <?php if ($isImg): ?>
                    <form method="post" class="input-group input-group-sm mt-2">
                        <?= csrf_field() ?><input type="hidden" name="action" value="alt"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <input class="form-control" name="alt_text" value="<?= h($m['alt_text'] ?? '') ?>" placeholder="Describe the image" aria-label="Image description for screen readers">
                        <button class="btn btn-outline-secondary" aria-label="Save description"><i class="fas fa-check"></i></button>
                    </form>
                    <?php endif; ?>
                    <div class="d-flex gap-1 mt-2">
                        <button type="button" class="btn btn-sm btn-light flex-grow-1" data-copy="<?= h(absolute_url($m['path'])) ?>">Copy link</button>
                        <form method="post" data-confirm="Delete <?= h($m['original_name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-danger" aria-label="Delete <?= h($m['original_name']) ?>"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-3"><?= render_pagination($pg) ?></div>
    <?php endif; ?>
</div></div>
<?php portal_end(); ?>
