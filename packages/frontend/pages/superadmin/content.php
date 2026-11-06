<?php
// Super Admin → Pages & Content: public pages, menus, home-page sections (features, steps, roles) and FAQ.
// These are the site's seed records (database/seed_data/site_content.php); everything here can be
// edited or deleted, and "Restore missing defaults" puts back any default that was deleted.
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_role(['super_admin']);

$tabs = ['pages' => 'Pages', 'menus' => 'Menus', 'home_slide' => 'Home: hero slides', 'home_why' => 'Home: why it matters', 'home_feature' => 'Home: features', 'home_step' => 'Home: how it works',
    'home_role' => 'Home: who uses it', 'faq' => 'FAQ'];
// Singular name of one item in each content group, for buttons and messages.
$itemNoun = ['home_slide' => 'slide', 'home_why' => 'reason', 'home_feature' => 'feature card', 'home_step' => 'step', 'home_role' => 'role card', 'faq' => 'question'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'pages';
$menus = ['header' => 'Top menu', 'footer_product' => 'Footer: Product', 'footer_company' => 'Footer: Company', 'footer_legal' => 'Footer: Legal'];
$back = fn(string $t) => '/pages/superadmin/content.php?tab=' . $t;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'restore_defaults') {
        $c = require __DIR__ . '/../../../../database/seed_data/site_content.php';
        $n = 0;
        foreach ($c['pages'] as [$slug, $title, $summary, $body, $mt, $md, $sys]) {
            $n += runQuery("INSERT IGNORE INTO pages (slug,title,summary,body,meta_title,meta_description,is_system) VALUES (?,?,?,?,?,?,?)", [$slug, $title, $summary, $body, $mt, $md, $sys])->affected_rows;
        }
        foreach ($c['menus'] as [$menu, $label, $url, $sort]) $n += runQuery("INSERT IGNORE INTO menu_items (menu,label,url,sort_order) VALUES (?,?,?,?)", [$menu, $label, $url, $sort])->affected_rows;
        foreach ($c['items'] as $item) {
            [$g, $icon, $title, $body, $sort] = $item;
            $n += runQuery("INSERT IGNORE INTO content_items (group_code,icon,title,body,sort_order,image,link_label,link_url) VALUES (?,?,?,?,?,?,?,?)",
                [$g, $icon, $title, $body, $sort, $item[5] ?? null, $item[6] ?? null, $item[7] ?? null])->affected_rows;
        }
        foreach ($c['emails'] as [$code, $name, $desc, $subj, $body, $vars]) $n += runQuery("INSERT IGNORE INTO email_templates (code,name,description,subject,body_html,variables) VALUES (?,?,?,?,?,?)", [$code, $name, $desc, $subj, $body, $vars])->affected_rows;
        foreach ($c['settings'] as [$k, $v, $type, $cat, $desc, $flag, $sort]) $n += runQuery("INSERT IGNORE INTO settings (`key`,`value`,value_type,category,description,is_feature_flag,sort_order) VALUES (?,?,?,?,?,?,?)", [$k, $v, $type, $cat, $desc, $flag, $sort])->affected_rows;
        runQuery("UPDATE pages SET deleted_at = NULL WHERE is_system = 1 AND deleted_at IS NOT NULL");
        audit_log('RESTORE', 'content', null, "Restored $n missing default records");
        flash('success', $n ? "$n missing default records were restored. Your edits were not changed." : 'Nothing was missing: every default record is present.');
        redirect($back($tab));
    }

    if ($action === 'delete_page') {
        $page = fetchOne("SELECT slug, title, is_system FROM pages WHERE id = ?", [$id]);
        if ($page && (int) $page['is_system']) flash('error', '"' . $page['title'] . '" is linked from the footer and sign-up flows, so it cannot be deleted. Edit it or set it to Draft instead.');
        elseif ($page) {
            runQuery("UPDATE pages SET deleted_at = NOW(), slug = CONCAT(slug, '-deleted-', id) WHERE id = ?", [$id]);
            audit_log('DELETE', 'page', $page['slug'], 'Deleted page ' . $page['title']);
            flash('success', 'Page deleted.');
        }
        redirect($back('pages'));
    }

    if ($action === 'save_menu') {
        $menu = isset($menus[$_POST['menu'] ?? '']) ? $_POST['menu'] : 'header';
        $label = trim((string) ($_POST['label'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($label === '' || mb_strlen($label) > 80 || $url === '' || mb_strlen($url) > 255 || preg_match('#^\s*javascript:#i', $url)) {
            flash('error', 'Enter a label (up to 80 characters) and a link such as /p/about or https://example.com.');
        } else {
            $params = [$menu, $label, $url, (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, !empty($_POST['new_tab']) ? 1 : 0];
            try {
                if ($id) runQuery("UPDATE menu_items SET menu=?, label=?, url=?, sort_order=?, is_active=?, new_tab=? WHERE id=?", [...$params, $id]);
                else runQuery("INSERT INTO menu_items (menu, label, url, sort_order, is_active, new_tab) VALUES (?,?,?,?,?,?)", $params);
                audit_log($id ? 'UPDATE' : 'CREATE', 'menu_item', (string) ($id ?: db()->insert_id), "$menu: $label → $url");
                flash('success', 'Menu link saved.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'That menu already has a link called "' . $label . '".');
            }
        }
        redirect($back('menus'));
    }

    if ($action === 'save_item') {
        $group = isset($itemNoun[$_POST['group_code'] ?? '']) ? $_POST['group_code'] : 'faq';
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $icon = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_POST['icon'] ?? ''))) ?: null;
        // Slides carry a photo and a button; other groups leave these empty.
        $image = $linkLabel = $linkUrl = null;
        $itemProblem = '';
        if ($group === 'home_slide') {
            $image = trim((string) ($_POST['image'] ?? '')) ?: null;
            if (!empty($_FILES['image_file']['name'])) {
                $up = store_upload($_FILES['image_file'], 'media', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 5 * 1024 * 1024);
                if ($up['ok']) $image = $up['path']; else $itemProblem = $up['error'];
            }
            if ($image !== null && !preg_match('#^(/[A-Za-z0-9/_.-]+|https://\S+)$#', $image)) $itemProblem = 'The photo must be an uploaded file or a path such as /assets/images/home/stage-riders.webp.';
            if ($image === null) $itemProblem = 'Choose a photo for the slide.';
            $linkLabel = mb_substr(trim((string) ($_POST['link_label'] ?? '')), 0, 80) ?: null;
            $linkUrl = trim((string) ($_POST['link_url'] ?? '')) ?: null;
            if ($linkUrl !== null && !preg_match('#^(/[^\s]*|https://\S+)$#', $linkUrl)) $itemProblem = 'The button link must start with / (a page on this site) or https://.';
            if (($linkLabel === null) !== ($linkUrl === null)) $itemProblem = 'Give the button both a text and a link, or leave both empty.';
        }
        if ($title === '' || mb_strlen($title) > 200 || $body === '') {
            flash('error', 'Enter a ' . ($group === 'faq' ? 'question and an answer' : 'title and a description') . '.');
        } elseif ($itemProblem !== '') {
            flash('error', $itemProblem);
        } else {
            $params = [$group, $icon, $title, $body, (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $image, $linkLabel, $linkUrl];
            try {
                if ($id) runQuery("UPDATE content_items SET group_code=?, icon=?, title=?, body=?, sort_order=?, is_active=?, image=?, link_label=?, link_url=? WHERE id=?", [...$params, $id]);
                else runQuery("INSERT INTO content_items (group_code, icon, title, body, sort_order, is_active, image, link_label, link_url) VALUES (?,?,?,?,?,?,?,?,?)", $params);
                audit_log($id ? 'UPDATE' : 'CREATE', 'content_item', (string) ($id ?: db()->insert_id), "$group: $title");
                flash('success', 'Saved.');
            } catch (mysqli_sql_exception $e) {
                flash('error', 'An item with that title already exists.');
            }
        }
        redirect($back($group));
    }

    if (in_array($action, ['delete_menu', 'delete_item', 'bulk_menu', 'bulk_item'], true)) {
        $table = str_contains($action, 'menu') ? 'menu_items' : 'content_items';
        $ids = str_starts_with($action, 'bulk') ? posted_ids() : ($id ? [$id] : []);
        $op = str_starts_with($action, 'bulk') ? (string) ($_POST['bulk'] ?? '') : 'delete';
        if ($ids && in_array($op, ['delete', 'hide', 'show'], true)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ($op === 'delete') runQuery("DELETE FROM $table WHERE id IN ($in)", $ids);
            else runQuery("UPDATE $table SET is_active = ? WHERE id IN ($in)", [$op === 'show' ? 1 : 0, ...$ids]);
            audit_log(strtoupper($op), $table, implode(',', $ids), count($ids) . ' record(s)');
            flash('success', count($ids) . ' ' . (count($ids) === 1 ? 'record' : 'records') . ($op === 'delete' ? ' deleted.' : ($op === 'show' ? ' shown.' : ' hidden.')));
        }
        redirect($back($tab));
    }
    redirect($back($tab));
}

$q = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $q . '%';
if ($tab === 'pages') {
    $rows = fetchAll("SELECT p.*, u.name AS editor FROM pages p LEFT JOIN users u ON u.id = p.updated_by WHERE p.deleted_at IS NULL"
        . ($q !== '' ? " AND (p.title LIKE ? OR p.slug LIKE ?)" : '') . " ORDER BY " . sort_sql(['title' => 'p.title', 'updated' => 'p.updated_at', 'status' => 'p.status'], 'title', 'asc'),
        $q !== '' ? [$like, $like] : []);
} elseif ($tab === 'menus') {
    $rows = fetchAll("SELECT * FROM menu_items" . ($q !== '' ? " WHERE label LIKE ? OR url LIKE ?" : '') . " ORDER BY FIELD(menu,'header','footer_product','footer_company','footer_legal'), sort_order, id", $q !== '' ? [$like, $like] : []);
} else {
    $rows = fetchAll("SELECT * FROM content_items WHERE group_code = ?" . ($q !== '' ? " AND (title LIKE ? OR body LIKE ?)" : '') . " ORDER BY sort_order, id", $q !== '' ? [$tab, $like, $like] : [$tab]);
}

portal_start('superadmin', 'content', 'Pages & content', [
    'subtitle' => 'Public website pages, menus, home page and FAQ',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Pages & Content' => null],
    'actions' => '<form method="post" data-confirm="Put back any default page, menu link, FAQ, email template or setting that was deleted? Records you edited stay as they are." data-confirm-ok="Restore">'
        . csrf_field() . '<input type="hidden" name="action" value="restore_defaults"><button class="btn btn-outline-secondary btn-sm">Restore missing defaults</button></form>'
        . ($tab === 'pages' ? '<a class="btn btn-primary btn-sm" href="' . BASE_URL . '/pages/superadmin/content-edit.php">New page</a>' : ''),
]);
?>
<ul class="nav nav-tabs mb-3" role="tablist">
    <?php foreach ($tabs as $k => $label): ?>
        <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="?tab=<?= $k ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <div class="col-sm-8 col-md-6"><label class="visually-hidden" for="q">Search</label><input class="form-control" id="q" name="q" value="<?= h($q) ?>" placeholder="Search <?= h(strtolower($tabs[$tab])) ?>"></div>
            <div class="col-auto"><button class="btn btn-outline-primary">Search</button><?php if ($q !== ''): ?> <a class="btn btn-link" href="?tab=<?= h($tab) ?>">Clear</a><?php endif; ?></div>
        </form>

<?php if ($tab === 'pages'): ?>
        <?php if (!$rows): echo empty_state('fa-file-lines', $q !== '' ? 'No page matches that search' : 'No pages yet', '', '<a class="btn btn-primary btn-sm" href="' . BASE_URL . '/pages/superadmin/content-edit.php">Create a page</a>'); else: ?>
        <div class="table-responsive">
            <table class="table align-middle" id="pagesTable">
                <thead><tr><th><?= sort_link('title', 'Title', 'title', 'asc') ?></th><th>Address</th><th><?= sort_link('status', 'Status', 'title', 'asc') ?></th><th><?= sort_link('updated', 'Last edited', 'title', 'asc') ?></th><th class="text-end no-export">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $p): ?>
                    <tr>
                        <td><strong><?= h($p['title']) ?></strong><?php if ((int) $p['is_system']): ?> <span class="badge bg-light text-dark border">Required</span><?php endif; ?><div class="small text-muted"><?= h($p['summary']) ?></div></td>
                        <td><a href="<?= BASE_URL ?>/p/<?= h($p['slug']) ?>" target="_blank" rel="noopener">/p/<?= h($p['slug']) ?></a></td>
                        <td><span class="badge <?= $p['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?>"><?= h(ucfirst($p['status'])) ?></span></td>
                        <td class="small"><?= h(timeAgo($p['updated_at'])) ?><?= $p['editor'] ? ' by ' . h($p['editor']) : '' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/pages/superadmin/content-edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
                            <?php if (!(int) $p['is_system']): ?>
                            <form method="post" class="d-inline" data-confirm="Delete the page &quot;<?= h($p['title']) ?>&quot;? Links to it will show the 404 page.">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

<?php elseif ($tab === 'menus'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <?php if (!$rows): echo empty_state('fa-bars', 'No menu links'); else: ?>
                <form method="post" id="bulkMenus" data-confirm="Apply &quot;{action}&quot; to {count} selected menu links?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="bulk_menu">
                    <div class="d-flex gap-2 mb-2"><select name="bulk" class="form-select form-select-sm w-auto" aria-label="Bulk action"><option value="show">Show selected</option><option value="hide">Hide selected</option><option value="delete">Delete selected</option></select><button class="btn btn-sm btn-outline-secondary">Apply</button></div>
                    <div class="table-responsive"><table class="table align-middle">
                        <thead><tr><th class="no-export"><span class="visually-hidden">Select</span></th><th>Menu</th><th>Label</th><th>Link</th><th>Order</th><th>Shown</th><th class="text-end">Edit</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $m): ?>
                            <tr>
                                <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="Select <?= h($m['label']) ?>"></td>
                                <td class="small"><?= h($menus[$m['menu']] ?? $m['menu']) ?></td>
                                <td><?= h($m['label']) ?></td>
                                <td class="small"><code><?= h($m['url']) ?></code><?= $m['new_tab'] ? ' <i class="fas fa-up-right-from-square small text-muted" title="Opens in a new tab"></i>' : '' ?></td>
                                <td><?= (int) $m['sort_order'] ?></td>
                                <td><?= $m['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?tab=menus&edit=<?= (int) $m['id'] ?>#menuForm">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </form>
                <?php endif; ?>
            </div>
            <div class="col-lg-4">
                <?php $edit = isset($_GET['edit']) ? fetchOne("SELECT * FROM menu_items WHERE id = ?", [(int) $_GET['edit']]) : null; ?>
                <form method="post" class="border rounded p-3" id="menuForm">
                    <h2 class="h6 fw-bold"><?= $edit ? 'Edit link' : 'Add a link' ?></h2>
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_menu"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <div class="mb-2"><label class="form-label small fw-semibold" for="m_menu">Menu</label><select class="form-select" id="m_menu" name="menu"><?php foreach ($menus as $k => $l): ?><option value="<?= $k ?>"<?= ($edit['menu'] ?? '') === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-2"><label class="form-label small fw-semibold" for="m_label">Label</label><input class="form-control" id="m_label" name="label" maxlength="80" required value="<?= h($edit['label'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold" for="m_url">Link</label><input class="form-control" id="m_url" name="url" maxlength="255" required value="<?= h($edit['url'] ?? '') ?>" placeholder="/p/about or https://..."><div class="form-text">Site links start with /, for example /contact.php or /p/about.</div></div>
                    <div class="mb-2"><label class="form-label small fw-semibold" for="m_sort">Order</label><input class="form-control" type="number" id="m_sort" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 10) ?>"></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="m_active" name="is_active" value="1"<?= !$edit || $edit['is_active'] ? ' checked' : '' ?>><label class="form-check-label" for="m_active">Show this link</label></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="m_tab" name="new_tab" value="1"<?= !empty($edit['new_tab']) ? ' checked' : '' ?>><label class="form-check-label" for="m_tab">Open in a new tab</label></div>
                    <button class="btn btn-primary">Save link</button>
                    <?php if ($edit): ?><a class="btn btn-link" href="?tab=menus">Cancel</a><?php endif; ?>
                </form>
                <?php if ($edit): ?>
                <form method="post" class="mt-2" data-confirm="Delete the link &quot;<?= h($edit['label']) ?>&quot;?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_menu"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete this link</button>
                </form>
                <?php endif; ?>
            </div>
        </div>

<?php else: $isFaq = $tab === 'faq'; $isSlide = $tab === 'home_slide'; ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <?php if (!$rows): echo empty_state($isFaq ? 'fa-circle-question' : 'fa-table-cells', 'Nothing here yet', 'Add the first ' . ($itemNoun[$tab] ?? 'item') . ' with the form.'); else: ?>
                <form method="post" data-confirm="Apply &quot;{action}&quot; to {count} selected items?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="bulk_item">
                    <div class="d-flex gap-2 mb-2"><select name="bulk" class="form-select form-select-sm w-auto" aria-label="Bulk action"><option value="show">Show selected</option><option value="hide">Hide selected</option><option value="delete">Delete selected</option></select><button class="btn btn-sm btn-outline-secondary">Apply</button></div>
                    <ul class="list-group">
                        <?php foreach ($rows as $it): ?>
                        <li class="list-group-item d-flex gap-3 align-items-start">
                            <input class="form-check-input mt-1" type="checkbox" name="ids[]" value="<?= (int) $it['id'] ?>" aria-label="Select <?= h($it['title']) ?>">
                            <?php if ($isSlide): ?><img src="<?= h(media_url($it['image'], '/assets/images/logo-mark.png')) ?>" alt="" width="96" height="54" class="rounded object-fit-cover flex-shrink-0" loading="lazy">
                            <?php elseif (!$isFaq): ?><i class="fas <?= h($it['icon'] ?: 'fa-check') ?> text-primary mt-1" aria-hidden="true"></i><?php endif; ?>
                            <div class="flex-grow-1"><div class="fw-semibold"><?= h($it['title']) ?> <?= $it['is_active'] ? '' : '<span class="badge bg-secondary">Hidden</span>' ?></div><div class="small text-muted"><?= h(mb_strimwidth($it['body'], 0, 160, '…')) ?></div></div>
                            <span class="small text-muted">#<?= (int) $it['sort_order'] ?></span>
                            <a class="btn btn-sm btn-outline-primary" href="?tab=<?= h($tab) ?>&edit=<?= (int) $it['id'] ?>#itemForm">Edit</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </form>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <?php $edit = isset($_GET['edit']) ? fetchOne("SELECT * FROM content_items WHERE id = ? AND group_code = ?", [(int) $_GET['edit'], $tab]) : null; ?>
                <form method="post" class="border rounded p-3" id="itemForm"<?= $isSlide ? ' enctype="multipart/form-data"' : '' ?>>
                    <h2 class="h6 fw-bold"><?= $edit ? 'Edit' : 'Add' ?> <?= h($itemNoun[$tab] ?? 'item') ?></h2>
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="group_code" value="<?= h($tab) ?>"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <div class="mb-2"><label class="form-label small fw-semibold" for="i_title"><?= $isFaq ? 'Question' : 'Title' ?></label><input class="form-control" id="i_title" name="title" maxlength="200" required value="<?= h($edit['title'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label small fw-semibold" for="i_body"><?= $isFaq ? 'Answer' : 'Description' ?></label><textarea class="form-control" id="i_body" name="body" rows="5" required data-maxlength="1000"><?= h($edit['body'] ?? '') ?></textarea></div>
                    <?php if ($isSlide): ?>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold" for="i_image">Photo</label>
                        <?php if (!empty($edit['image'])): ?><img src="<?= h(media_url($edit['image'])) ?>" alt="" class="d-block w-100 rounded mb-2 object-fit-cover" style="aspect-ratio:16/9"><?php endif; ?>
                        <input class="form-control mb-2" id="i_image" name="image" value="<?= h($edit['image'] ?? '') ?>" placeholder="/assets/images/home/stage-riders.webp" aria-describedby="i_image_help">
                        <input type="file" name="image_file" id="i_image_file" accept="image/jpeg,image/png,image/webp" data-max-mb="5" aria-label="Upload a new photo for this slide">
                        <div class="form-text" id="i_image_help">A wide photo, at least 1600 pixels across. Text sits on the left, so keep the subject in the middle or on the right. Uploading a file replaces the path above.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6"><label class="form-label small fw-semibold" for="i_link_label">Button text</label><input class="form-control" id="i_link_label" name="link_label" maxlength="80" value="<?= h($edit['link_label'] ?? '') ?>" placeholder="Check a permit now"></div>
                        <div class="col-sm-6"><label class="form-label small fw-semibold" for="i_link_url">Button link</label><input class="form-control" id="i_link_url" name="link_url" value="<?= h($edit['link_url'] ?? '') ?>" placeholder="/verify.php"></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!$isFaq && !$isSlide): ?><div class="mb-2"><label class="form-label small fw-semibold" for="i_icon">Icon</label><input class="form-control" id="i_icon" name="icon" value="<?= h($edit['icon'] ?? 'fa-check') ?>"><div class="form-text">A Font Awesome icon name, for example fa-id-card or fa-money-bill-wave.</div></div><?php endif; ?>
                    <div class="mb-2"><label class="form-label small fw-semibold" for="i_sort">Order</label><input class="form-control" type="number" id="i_sort" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? (count($rows) + 1)) ?>"></div>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="i_active" name="is_active" value="1"<?= !$edit || $edit['is_active'] ? ' checked' : '' ?>><label class="form-check-label" for="i_active">Show on the website</label></div>
                    <button class="btn btn-primary">Save</button>
                    <?php if ($edit): ?><a class="btn btn-link" href="?tab=<?= h($tab) ?>">Cancel</a><?php endif; ?>
                </form>
                <?php if ($edit): ?>
                <form method="post" class="mt-2" data-confirm="Delete &quot;<?= h($edit['title']) ?>&quot;?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
<?php endif; ?>
    </div>
</div>
<?php portal_end(); ?>
