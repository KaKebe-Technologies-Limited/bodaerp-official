<?php
// <head> for every public page. Set before including:
// $seo = ['title' => ..., 'description' => ..., 'image' => ..., 'noindex' => bool,
//         'type' => schema.org page type (WebPage, AboutPage, FAQPage, ContactPage ...),
//         'crumb' => breadcrumb label (defaults to the title before " | "),
//         'entity' => extra schema.org data for the page node (e.g. FAQPage mainEntity),
//         'nodes' => extra schema.org nodes for the graph]
$seo = ($seo ?? []) + ['title' => '', 'description' => '', 'image' => '', 'noindex' => false,
    'type' => 'WebPage', 'crumb' => '', 'entity' => [], 'nodes' => []];
$siteName = get_setting('site_name', 'BodaERP');
$seoTitle = $seo['title'] !== '' ? $seo['title'] : get_setting('seo_default_title', $siteName);
$seoDesc = $seo['description'] !== '' ? $seo['description'] : get_setting('seo_default_description', get_setting('site_tagline', ''));
$seoImagePath = $seo['image'] !== '' ? $seo['image'] : get_setting('seo_og_image', '/assets/images/og-image.jpg');
$seoImage = absolute_url($seoImagePath);
$seoImageIsCard = str_ends_with($seoImagePath, '/og-image.jpg');
$requestPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$canonical = app_url(ltrim(substr($requestPath, strlen(BASE_URL)), '/'));
$siteUrl = app_url('');
$isHome = rtrim($canonical, '/') === rtrim($siteUrl, '/');
$cssV = fn(string $f) => @filemtime(__DIR__ . '/../assets/css/' . $f) ?: 1;

$schema = [];
if (!$seo['noindex']) {
    $orgId = $siteUrl . '#organization';
    $websiteId = $siteUrl . '#website';
    $contactEmail = get_setting('contact_email', get_setting('support_email', ''));
    $contactPhone = get_setting('contact_phone', '');
    $locality = trim(explode(',', get_setting('contact_address', 'Kampala, Uganda'))[0]);
    $sameAs = array_values(array_filter(array_map(fn($k) => get_setting($k, ''), ['social_facebook', 'social_x', 'social_linkedin']),
        fn($u) => preg_match('#^https://#i', $u)));
    $org = ['@type' => 'Organization', '@id' => $orgId, 'name' => $siteName, 'url' => $siteUrl,
        'logo' => ['@type' => 'ImageObject', 'url' => absolute_url('/assets/images/favicon-512.png'), 'width' => 512, 'height' => 512],
        'description' => get_setting('site_tagline', ''),
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => $locality, 'addressCountry' => 'UG'],
        'areaServed' => ['@type' => 'Country', 'name' => 'Uganda']];
    if ($legal = get_setting('operator_name', '')) $org['legalName'] = $legal;
    if ($contactEmail !== '') {
        $org['email'] = $contactEmail;
        $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $contactEmail,
            'areaServed' => 'UG', 'availableLanguage' => ['English']] + ($contactPhone !== '' ? ['telephone' => $contactPhone] : []);
    }
    if ($contactPhone !== '') $org['telephone'] = $contactPhone;
    if ($sameAs) $org['sameAs'] = $sameAs;

    $pageNode = ['@type' => $seo['type'], '@id' => $canonical . '#webpage', 'url' => $canonical, 'name' => $seoTitle,
        'description' => $seoDesc, 'isPartOf' => ['@id' => $websiteId], 'about' => ['@id' => $orgId], 'inLanguage' => 'en-UG',
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $seoImage]] + $seo['entity'];
    $schema = [
        $org,
        ['@type' => 'WebSite', '@id' => $websiteId, 'url' => $siteUrl, 'name' => $siteName,
            'description' => get_setting('seo_default_description', ''), 'publisher' => ['@id' => $orgId], 'inLanguage' => 'en-UG'],
    ];
    if (!$isHome) {
        $crumb = $seo['crumb'] !== '' ? $seo['crumb'] : trim(explode(' | ', $seoTitle)[0]);
        $pageNode['breadcrumb'] = ['@id' => $canonical . '#breadcrumb'];
        $schema[] = ['@type' => 'BreadcrumbList', '@id' => $canonical . '#breadcrumb', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $siteUrl],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $crumb, 'item' => $canonical],
        ]];
    }
    $schema[] = $pageNode;
    foreach ($seo['nodes'] as $node) $schema[] = $node;
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($seoTitle) ?></title>
<meta name="description" content="<?= h($seoDesc) ?>">
<?php if ($seo['noindex']): ?><meta name="robots" content="noindex, nofollow">
<?php else: ?><meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<?php endif; ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="en_UG">
<meta property="og:site_name" content="<?= h($siteName) ?>">
<meta property="og:title" content="<?= h($seoTitle) ?>">
<meta property="og:description" content="<?= h($seoDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:image" content="<?= h($seoImage) ?>">
<?php if ($seoImageIsCard): ?><meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/jpeg">
<?php endif; ?>
<meta property="og:image:alt" content="<?= h($siteName . ': ' . get_setting('site_tagline', '')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($seoTitle) ?>">
<meta name="twitter:description" content="<?= h($seoDesc) ?>">
<meta name="twitter:image" content="<?= h($seoImage) ?>">
<meta name="theme-color" content="<?= h(brand_color('brand_color_dark', BRAND_DEFAULT_DARK)) ?>">
<?= brand_favicon_tags() ?>
<?php if ($schema): ?><script type="application/ld+json" nonce="<?= csp_nonce() ?>"><?= js_json(['@context' => 'https://schema.org', '@graph' => $schema]) ?></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/outfit/outfit.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css?v=<?= $cssV('public.css') ?>">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/controls.css?v=<?= $cssV('controls.css') ?>">
<style><?= brand_css_vars() ?></style>
