<?php
// <head> for every public page. Set before including: $seo = ['title' => ..., 'description' => ..., 'image'
// => ..., 'noindex' => bool]
$seo = ($seo ?? []) + ['title' => '', 'description' => '', 'image' => '', 'noindex' => false];
$siteName = get_setting('site_name', 'BodaERP');
$seoTitle = $seo['title'] !== '' ? $seo['title'] : get_setting('seo_default_title', $siteName);
$seoDesc = $seo['description'] !== '' ? $seo['description'] : get_setting('seo_default_description', get_setting('site_tagline', ''));
$seoImage = absolute_url($seo['image'] !== '' ? $seo['image'] : get_setting('seo_og_image', '/assets/images/favicon-512.png'));
$canonical = app_url(ltrim(substr(strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/', strlen(BASE_URL)), '/'));
$cssV = fn(string $f) => @filemtime(__DIR__ . '/../assets/css/' . $f) ?: 1;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($seoTitle) ?></title>
<meta name="description" content="<?= h($seoDesc) ?>">
<?php if ($seo['noindex']): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<link rel="canonical" href="<?= h($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= h($siteName) ?>">
<meta property="og:title" content="<?= h($seoTitle) ?>">
<meta property="og:description" content="<?= h($seoDesc) ?>">
<meta property="og:url" content="<?= h($canonical) ?>">
<meta property="og:image" content="<?= h($seoImage) ?>">
<meta name="twitter:card" content="summary">
<meta name="theme-color" content="<?= h(brand_color('brand_color_dark', BRAND_DEFAULT_DARK)) ?>">
<?= brand_favicon_tags() ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI" crossorigin="anonymous">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css?v=<?= $cssV('public.css') ?>">
<style><?= brand_css_vars() ?></style>
