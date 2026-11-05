<?php
// Public content page by slug: /p/about, /p/privacy-policy ... (edited in Super Admin → Content → Pages).
require_once __DIR__ . '/packages/backend/site.php';

$slug = (string) ($_GET['slug'] ?? '');
$preview = isset($_GET['preview']) && ($_SESSION['role'] ?? '') === 'super_admin';
$page = page_by_slug($slug, $preview);
if (!$page) app_error_page(404);

$seo = [
    'title' => $page['meta_title'] ?: $page['title'] . ' | ' . get_setting('site_name', 'BodaERP'),
    'description' => $page['meta_description'] ?: (string) $page['summary'],
    'image' => (string) $page['og_image'],
    'noindex' => $page['status'] !== 'published',
];
$currentPath = '/p/' . $page['slug'];
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?></head>
<body class="public">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>
<main id="main">
    <section class="page-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= BASE_URL ?>/">Home</a> / <?= h($page['title']) ?></nav>
            <h1><?= h($page['title']) ?></h1>
            <?php if ($page['summary']): ?><p><?= h($page['summary']) ?></p><?php endif; ?>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container">
            <?php if ($page['status'] !== 'published'): ?><div class="notice notice-info">Draft preview: visitors cannot see this page yet.</div><?php endif; ?>
            <article class="prose">
                <?= render_rich_text($page['body']) ?>
                <p class="updated">Last updated <?= h(formatDate($page['updated_at'], 'j F Y')) ?></p>
            </article>
        </div>
    </section>
</main>
<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
