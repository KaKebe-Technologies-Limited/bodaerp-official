<?php
require_once __DIR__ . '/packages/backend/site.php';

$faqs = content_items('faq');
$faqText = fn(string $html) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$seo = ['title' => 'Boda-boda permit questions answered | ' . get_setting('site_name', 'BodaERP'),
    'description' => "Answers for riders, stage chairpersons and city councils on registration, permit fees, mobile money payments, receipts and checking a rider's ID card.",
    'type' => 'FAQPage', 'crumb' => 'FAQ',
    'entity' => $faqs ? ['mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $faqText((string) $f['title']),
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faqText((string) $f['body'])]], $faqs)] : []];
$currentPath = '/faq.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?>
<?php if ($faqs): ?>
<script type="application/ld+json"><?= js_json([
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['title'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['body']]], $faqs),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="public">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>
<main id="main">
    <section class="page-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= BASE_URL ?>/">Home</a> / FAQ</nav>
            <h1>Frequently asked questions</h1>
            <p>For riders, stage chairpersons and city councils. Can't find your answer? <a href="<?= BASE_URL ?>/contact.php">Send us a message</a>.</p>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container">
            <?php if (!$faqs): ?>
                <p class="text-center text-muted">No questions have been published yet.</p>
            <?php else: ?>
                <div class="faq-search field">
                    <label for="faqSearch">Search the questions</label>
                    <input type="search" id="faqSearch" placeholder="For example: payment, password, ID card" data-faq-search>
                </div>
                <div class="faq-list">
                    <?php foreach ($faqs as $i => $f): ?>
                    <?= render_faq_item($f['title'], $f['body'], $i === 0) ?>
                    <?php endforeach; ?>
                    <p class="text-muted text-center" data-faq-empty hidden>No question matches that search. <a href="<?= BASE_URL ?>/contact.php">Ask us directly</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
