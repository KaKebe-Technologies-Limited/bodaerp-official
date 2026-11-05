<?php
// Super Admin → Pages & Content → create / edit a public page (CKEditor 5 body, SEO fields, draft/published).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_role(['super_admin']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$page = $id ? fetchOne("SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL", [$id]) : null;
if ($id && !$page) app_error_page(404);

$v = $page ?? ['slug' => '', 'title' => '', 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'og_image' => '', 'status' => 'draft', 'is_system' => 0];
$errors = [];
$reserved = ['admin', 'api', 'login', 'pages', 'assets', 'uploads', 'contact', 'faq', 'verify'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (['title', 'summary', 'body', 'meta_title', 'meta_description', 'og_image'] as $f) $v[$f] = trim((string) ($_POST[$f] ?? ''));
    $v['status'] = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug !== '' ? $slug : $v['title']), '-');
    if (!$page || !(int) $page['is_system']) $v['slug'] = $slug;

    if ($v['title'] === '' || mb_strlen($v['title']) > 200) $errors[] = 'Enter a title of up to 200 characters.';
    if ($v['slug'] === '' || mb_strlen($v['slug']) > 120 || in_array($v['slug'], $reserved, true)) $errors[] = 'Choose a different web address (letters, numbers and dashes).';
    elseif (fetchValue("SELECT COUNT(*) FROM pages WHERE slug = ? AND id <> ?", [$v['slug'], $id])) $errors[] = 'Another page already uses /p/' . $v['slug'] . '.';
    if (trim(strip_tags($v['body'], '<img><iframe><oembed>')) === '') $errors[] = 'Write the page content.';
    if (mb_strlen($v['summary']) > 500) $errors[] = 'Keep the summary under 500 characters.';
    if (mb_strlen($v['meta_title']) > 200 || mb_strlen($v['meta_description']) > 300) $errors[] = 'The search title is limited to 200 characters and the description to 300.';

    if (!$errors) {
        $body = safe_html($v['body']);
        $params = [$v['slug'], $v['title'], $v['summary'] ?: null, $body, $v['meta_title'] ?: null, $v['meta_description'] ?: null, $v['og_image'] ?: null, $v['status'], $_SESSION['user_id']];
        if ($page) runQuery("UPDATE pages SET slug=?, title=?, summary=?, body=?, meta_title=?, meta_description=?, og_image=?, status=?, updated_by=? WHERE id=?", [...$params, $id]);
        else { runQuery("INSERT INTO pages (slug, title, summary, body, meta_title, meta_description, og_image, status, updated_by) VALUES (?,?,?,?,?,?,?,?,?)", $params); $id = (int) db()->insert_id; }
        audit_log($page ? 'UPDATE' : 'CREATE', 'page', $v['slug'], ($page ? 'Edited' : 'Created') . ' page ' . $v['title'] . ' (' . $v['status'] . ')');
        flash('success', $v['status'] === 'published' ? 'Page saved and published.' : 'Draft saved. Visitors cannot see it until you publish it.');
        redirect('/pages/superadmin/content-edit.php?id=' . $id);
    }
}

$images = fetchAll("SELECT path, original_name FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT 100");
portal_start('superadmin', 'content', $page ? 'Edit page' : 'New page', [
    'subtitle' => $page ? $page['title'] : 'Add a page to the public website',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Pages & Content' => '/pages/superadmin/content.php', ($page ? $page['title'] : 'New page') => null],
    'actions' => $page ? '<a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="' . BASE_URL . '/p/' . h($page['slug']) . ($page['status'] !== 'published' ? '?preview=1' : '') . '"><i class="fas fa-eye me-1"></i>' . ($page['status'] === 'published' ? 'View page' : 'Preview draft') . '</a>' : '',
]);
?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<form method="post" class="row g-3">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <div class="mb-3"><label class="form-label fw-semibold" for="title">Title</label><input class="form-control form-control-lg" id="title" name="title" maxlength="200" required value="<?= h($v['title']) ?>"></div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="slug">Web address</label>
                <div class="input-group"><span class="input-group-text"><?= h(app_url('p/')) ?></span><input class="form-control" id="slug" name="slug" maxlength="120" value="<?= h($v['slug']) ?>" <?= (int) $v['is_system'] ? 'readonly' : '' ?> pattern="[a-z0-9-]*"></div>
                <div class="form-text"><?= (int) $v['is_system'] ? 'This page is linked from the footer and emails, so its address is fixed.' : 'Leave empty to build it from the title.' ?></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold" for="summary">Summary</label><input class="form-control" id="summary" name="summary" maxlength="500" value="<?= h($v['summary']) ?>"><div class="form-text">One sentence shown under the title.</div></div>
            <label class="form-label fw-semibold" for="body">Content</label>
            <textarea id="body" name="body" data-rich-text required aria-label="Page content"><?= h($v['body']) ?></textarea>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 fw-bold">Publishing</h2>
            <div class="form-check"><input class="form-check-input" type="radio" name="status" id="st_pub" value="published"<?= $v['status'] === 'published' ? ' checked' : '' ?>><label class="form-check-label" for="st_pub">Published: anyone can see it</label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="radio" name="status" id="st_draft" value="draft"<?= $v['status'] !== 'published' ? ' checked' : '' ?>><label class="form-check-label" for="st_draft">Draft: only administrators can preview it</label></div>
            <button class="btn btn-primary w-100" data-busy-label="Saving"><i class="fas fa-floppy-disk me-1"></i>Save page</button>
            <?php if ($page): ?><p class="small text-muted mt-2 mb-0">Last saved <?= h(formatDate($page['updated_at'], 'j M Y, H:i')) ?></p><?php endif; ?>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6 fw-bold">Search engines and sharing</h2>
            <div class="mb-2"><label class="form-label small fw-semibold" for="meta_title">Search title</label><input class="form-control" id="meta_title" name="meta_title" maxlength="200" value="<?= h($v['meta_title']) ?>" placeholder="<?= h($v['title'] ?: 'Page title') ?> | BodaERP"></div>
            <div class="mb-2"><label class="form-label small fw-semibold" for="meta_description">Search description</label><textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="300" data-maxlength="300"><?= h($v['meta_description']) ?></textarea></div>
            <div><label class="form-label small fw-semibold" for="og_image">Sharing image</label>
                <select class="form-select" id="og_image" name="og_image"><option value="">Site default</option>
                    <?php foreach ($images as $img): ?><option value="<?= h($img['path']) ?>"<?= $v['og_image'] === $img['path'] ? ' selected' : '' ?>><?= h($img['original_name']) ?></option><?php endforeach; ?>
                </select><div class="form-text">Upload images in the <a href="<?= BASE_URL ?>/pages/superadmin/media.php">Media library</a>.</div></div>
        </div></div>
    </div>
</form>
<?php portal_end(true); ?>
