<?php
// Super Admin → Site Text & Branding: every row of the `settings` table, grouped by category,
// with an input that suits its type (text, long text, on/off, number, colour, image).
require_once __DIR__ . '/../../../backend/admin_ui.php';
require_once __DIR__ . '/../../../backend/site.php';
require_once __DIR__ . '/../../../backend/uploads.php';
require_role(['super_admin']);

$labels = [
    'general' => ['General', 'Site name and operator'], 'branding' => ['Branding', 'Logo, icon and colours'],
    'contact' => ['Contact details', 'Shown in the footer and on the contact page'], 'home' => ['Home page', 'Main heading, buttons and sections'],
    'login' => ['Sign-in page', 'Text around the sign-in form'], 'seo' => ['Search and sharing', 'Defaults for search engines and social media'],
    'messages' => ['Messages', 'Notices shown after actions'], 'errors' => ['Error and maintenance pages', 'Text on 404, 403, 500 and maintenance pages'],
    'mail' => ['Email', 'Sender name and small print'], 'security' => ['Sign-in security', 'Lockout and password rules'],
    'features' => ['Features', 'Switch parts of the site on or off'], 'labels' => ['Portal labels', 'Dashboard titles and welcome text'],
    'navigation' => ['Navigation labels', 'Menu words used in the portals'], 'appearance' => ['Appearance (legacy)', 'Older appearance options'],
];
$cats = array_column(fetchAll("SELECT DISTINCT category FROM settings ORDER BY category"), 'category');
$order = array_flip(array_keys($labels));
usort($cats, fn($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
$cat = in_array($_GET['cat'] ?? '', $cats, true) ? $_GET['cat'] : ($cats[0] ?? 'general');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $rows = fetchAll("SELECT `key`, value, value_type FROM settings WHERE category = ?", [$cat]);
    $errors = []; $changed = [];
    foreach ($rows as $r) {
        $k = $r['key'];
        $type = $r['value_type'];
        if ($type === 'boolean') $new = !empty($_POST['s'][$k]) ? 'true' : 'false';
        elseif ($type === 'image' && !empty($_FILES['f']['name'][$k])) {
            $file = ['name' => $_FILES['f']['name'][$k], 'type' => $_FILES['f']['type'][$k], 'tmp_name' => $_FILES['f']['tmp_name'][$k], 'error' => $_FILES['f']['error'][$k], 'size' => $_FILES['f']['size'][$k]];
            $up = store_upload($file, 'branding', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 2 * 1024 * 1024);
            if (!$up['ok']) { $errors[] = $k . ': ' . $up['error']; continue; }
            runQuery("INSERT INTO media (path, original_name, mime, size_bytes, width, height, uploaded_by) VALUES (?,?,?,?,?,?,?)",
                [$up['path'], mb_substr($file['name'], 0, 255), $up['mime'], $up['size'], $up['width'], $up['height'], $_SESSION['user_id']]);
            $new = $up['path'];
        } else {
            if (!array_key_exists($k, $_POST['s'] ?? [])) continue;
            $new = trim((string) $_POST['s'][$k]);
            if ($type === 'number' && !ctype_digit($new)) { $errors[] = "$k must be a whole number."; continue; }
            if ($type === 'color' && !preg_match('/^#[0-9a-f]{6}$/i', $new)) { $errors[] = "$k must be a colour like #056CFE."; continue; }
            if (str_starts_with($k, 'social_') || $k === 'contact_map_url') {
                if ($new !== '' && !preg_match('#^https://#i', $new)) { $errors[] = "$k must start with https://"; continue; }
            }
            if (($type === 'email' || in_array($k, ['contact_email', 'contact_inbox_email', 'support_email'], true)) && $new !== '' && !filter_var($new, FILTER_VALIDATE_EMAIL)) { $errors[] = "$k must be an email address."; continue; }
            if ($k === 'languages_enabled') {
                $codes = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower($new))))));
                if ($bad = array_diff($codes, array_keys(LANGUAGES))) { $errors[] = 'Unknown language code: ' . implode(', ', $bad) . '. Use ' . implode(', ', array_keys(LANGUAGES)) . '.'; continue; }
                $new = implode(',', array_unique(array_merge(['en'], $codes)));
            }
            if ($k === 'ussd_code' && $new !== '' && !preg_match('/^\*[0-9*]+#$/', $new)) { $errors[] = 'The USSD code must look like *217*55#.'; continue; }
            if (mb_strlen($new) > 5000) { $errors[] = "$k is too long."; continue; }
        }
        if ($new !== (string) $r['value']) {
            runQuery("UPDATE settings SET value = ? WHERE `key` = ?", [$new, $k]);
            $changed[] = $k;
        }
    }
    if ($changed) audit_log('UPDATE', 'settings', $cat, 'Changed: ' . implode(', ', $changed));
    if ($errors) flash('error', implode(' ', $errors));
    flash('success', $changed ? count($changed) . ' setting' . (count($changed) === 1 ? '' : 's') . ' saved.' : 'No changes to save.');
    redirect('/pages/superadmin/site-settings.php?cat=' . urlencode($cat));
}

$settings = fetchAll("SELECT * FROM settings WHERE category = ? ORDER BY sort_order, `key`", [$cat]);
portal_start('superadmin', 'site-settings', 'Site text & branding', [
    'subtitle' => 'Every word, colour and switch on the public site and in the portals',
    'breadcrumbs' => ['Super Admin' => '/pages/superadmin/dashboard.php', 'Site Text & Branding' => null],
]);
?>
<div class="row g-3">
    <div class="col-lg-3">
        <div class="list-group">
            <?php foreach ($cats as $c): ?>
                <a class="list-group-item list-group-item-action<?= $c === $cat ? ' active' : '' ?>" href="?cat=<?= h($c) ?>"<?= $c === $cat ? ' aria-current="page"' : '' ?>><?= h($labels[$c][0] ?? ucfirst($c)) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-9">
        <form method="post" enctype="multipart/form-data" class="card">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1"><?= h($labels[$cat][0] ?? ucfirst($cat)) ?></h2>
                <p class="text-muted small mb-4"><?= h($labels[$cat][1] ?? '') ?></p>
                <?= csrf_field() ?>
                <?php if (!$settings) echo empty_state('fa-sliders', 'No settings in this group'); ?>
                <?php foreach ($settings as $s): $id = 's_' . $s['key']; $name = 's[' . h($s['key']) . ']'; ?>
                    <div class="mb-3">
                        <?php if ($s['value_type'] === 'boolean'): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="<?= h($id) ?>" name="<?= $name ?>" value="1"<?= filter_var($s['value'], FILTER_VALIDATE_BOOLEAN) ? ' checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="<?= h($id) ?>"><?= h($s['description'] ?: $s['key']) ?></label>
                            </div>
                        <?php else: ?>
                            <label class="form-label fw-semibold" for="<?= h($id) ?>"><?= h($s['description'] ?: $s['key']) ?></label>
                            <?php if ($s['value_type'] === 'textarea'): ?>
                                <textarea class="form-control" id="<?= h($id) ?>" name="<?= $name ?>" rows="3"><?= h($s['value']) ?></textarea>
                            <?php elseif ($s['value_type'] === 'color'): ?>
                                <div class="d-flex gap-2"><input type="color" class="form-control form-control-color" value="<?= h($s['value']) ?>" aria-label="Pick colour" data-color-for="<?= h($id) ?>"><input class="form-control" id="<?= h($id) ?>" name="<?= $name ?>" value="<?= h($s['value']) ?>" pattern="#[0-9a-fA-F]{6}" data-pattern-message="Use a colour code like #056CFE: a # and six letters or digits." style="max-width:9rem"></div>
                            <?php elseif ($s['value_type'] === 'image'): ?>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= h(media_url($s['value'], '/assets/images/logo.png')) ?>" alt="" width="48" height="48" class="border rounded" style="object-fit:contain">
                                    <div class="flex-grow-1">
                                        <input class="form-control mb-1" id="<?= h($id) ?>" name="<?= $name ?>" value="<?= h($s['value']) ?>">
                                        <input class="form-control form-control-sm" type="file" name="f[<?= h($s['key']) ?>]" accept="image/png,image/jpeg,image/webp" data-max-mb="2" aria-label="Upload a new image">
                                    </div>
                                </div>
                            <?php else: ?>
                                <input class="form-control" id="<?= h($id) ?>" name="<?= $name ?>" value="<?= h($s['value']) ?>"<?= $s['value_type'] === 'number' ? ' inputmode="numeric" pattern="\d+" data-pattern-message="Enter a whole number, for example 30."' : '' ?>>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="form-text"><code><?= h($s['key']) ?></code></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($settings): ?><div class="card-footer bg-white"><button class="btn btn-primary" data-busy-label="Saving">Save <?= h(strtolower($labels[$cat][0] ?? $cat)) ?></button></div><?php endif; ?>
        </form>
    </div>
</div>
<script nonce="<?= csp_nonce() ?>">
document.querySelectorAll('[data-color-for]').forEach(function (picker) {
    var text = document.getElementById(picker.getAttribute('data-color-for'));
    picker.addEventListener('input', function () { text.value = picker.value; });
    text.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
});
</script>
<?php portal_end(); ?>
