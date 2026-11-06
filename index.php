<?php
require_once __DIR__ . '/packages/backend/site.php';

$showStats = setting_bool('home_show_stats', true);
if ($showStats) {
    $totalRiders  = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE deleted_at IS NULL");
    $activeRiders = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE status = 'active' AND deleted_at IS NULL");
    $cityCount    = (int) fetchValue("SELECT COUNT(*) FROM cities WHERE status = 'active'");
    $totalRevenue = (float) fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Confirmed'");
    $compliance   = $totalRiders ? (int) round(100 * $activeRiders / $totalRiders) : 0;
    $revenueText  = $totalRevenue >= 1000000 ? 'UGX ' . number_format($totalRevenue / 1000000, 1) . 'M' : formatCurrency($totalRevenue);
}
$slides = array_values(array_filter(content_items('home_slide'), fn($s) => home_photo((string) $s['image'])[0] !== ''));
$features = content_items('home_feature');
$steps = content_items('home_step');
$reasons = content_items('home_why');
$roles = content_items('home_role');
$faqPreview = array_slice(content_items('faq'), 0, 4);
$liveCities = setting_bool('home_show_cities', true) ? fetchAll("SELECT name FROM cities WHERE status = 'active' ORDER BY name") : [];
$iconClass = fn(?string $icon, string $fallback) => preg_replace('/[^a-z0-9- ]/', '', (string) $icon) ?: $fallback;
// A band with a background photo: the phone-sized photo by default, the full one from tablet width up.
$photoStyle = function (string $settingKey): string {
    [$full, $small] = home_photo(get_setting($settingKey));
    return $full === '' ? '' : ' style="--photo: url(\'' . h($full) . '\'); --photo-sm: url(\'' . h($small) . '\')"';
};
$interval = max(0, min(30, (int) get_setting('home_slider_interval', '7')));
// Light sections alternate grey and white in the order they render, so two neighbours never share a colour
// whichever sections are switched off. The hero, the stats band and the closing band are dark.
$tone = function (): string { static $n = 0; return $n++ % 2 === 0 ? ' section-alt' : ''; };
$seo = ['title' => get_setting('seo_default_title', 'BodaERP'), 'description' => get_setting('seo_default_description', ''),
    'nodes' => [['@type' => 'Service', '@id' => app_url('') . '#service', 'name' => 'Boda-boda rider registration and permit management',
        'serviceType' => 'Rider registration, permit fee collection and permit verification',
        'description' => get_setting('seo_default_description', ''), 'provider' => ['@id' => app_url('') . '#organization'],
        'areaServed' => ['@type' => 'Country', 'name' => 'Uganda'],
        'audience' => ['@type' => 'Audience', 'audienceType' => 'City councils, boda-boda stage chairpersons and riders']]]];
$currentPath = '/';
[$rolesPhoto] = home_photo(get_setting('home_roles_image'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?>
<?php if ($slides): [$firstFull, $firstSmall] = home_photo((string) $slides[0]['image']); ?>
<link rel="preload" as="image" href="<?= h($firstFull) ?>" imagesrcset="<?= h($firstSmall) ?> 960w, <?= h($firstFull) ?> 1920w" imagesizes="100vw" fetchpriority="high">
<?php endif; ?>
</head>
<body class="public home">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>

<main id="main">
    <h1 class="visually-hidden"><?= h(get_setting('home_hero_title', get_setting('site_name', 'BodaERP'))) ?></h1>

    <?php if ($slides): $count = count($slides); ?>
    <section class="hero-slider" aria-roledescription="carousel" aria-label="<?= h(get_setting('site_name', 'BodaERP')) ?> at a glance" data-hero data-interval="<?= $interval ?>">
        <div class="hero-slides" id="heroSlides" aria-live="<?= $interval && $count > 1 ? 'off' : 'polite' ?>">
            <?php foreach ($slides as $i => $slide): [$full, $small] = home_photo((string) $slide['image']); ?>
            <div class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" id="hero-slide-<?= $i + 1 ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= $count ?>"<?= $i === 0 ? '' : ' aria-hidden="true" inert' ?>>
                <img class="hero-photo" src="<?= h($full) ?>" srcset="<?= h($small) ?> 960w, <?= h($full) ?> 1920w" sizes="100vw" alt="" width="1920" height="1080"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
                <div class="container hero-content">
                    <h2 class="hero-title"><?= h($slide['title']) ?></h2>
                    <p class="hero-text"><?= h($slide['body']) ?></p>
                    <?php if ($slide['link_label'] && $slide['link_url']): ?>
                    <div class="hero-actions">
                        <a class="btn-brand btn-lg-brand" href="<?= h(site_link($slide['link_url'])) ?>"><?= h($slide['link_label']) ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                        <?php if (!str_contains($slide['link_url'], 'verify.php')): ?><a class="btn-outline-light" href="<?= BASE_URL ?>/verify.php"><i class="fas fa-qrcode" aria-hidden="true"></i> <?= h(get_setting('home_cta_secondary', 'Check a rider\'s permit')) ?></a><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($count > 1): ?>
        <div class="hero-arrows">
            <button type="button" class="hero-btn" data-hero-prev aria-controls="heroSlides" aria-label="Previous slide"><i class="fas fa-arrow-left" aria-hidden="true"></i></button>
            <button type="button" class="hero-btn" data-hero-next aria-controls="heroSlides" aria-label="Next slide"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($reasons): ?>
    <section class="section<?= $tone() ?> why-section" id="why-it-matters">
        <div class="container">
            <div class="why-layout">
                <div class="why-intro">
                    <h2><?= h(get_setting('home_why_title', 'Why it matters')) ?></h2>
                    <p><?= h(get_setting('home_why_text')) ?></p>
                    <?php [$whyFull, $whySmall] = home_photo(get_setting('home_why_image')); if ($whyFull !== ''): ?>
                    <picture class="why-photo">
                        <source media="(min-width: 768px)" srcset="<?= h($whyFull) ?>">
                        <img src="<?= h($whySmall) ?>" alt="" loading="lazy" decoding="async">
                    </picture>
                    <?php endif; ?>
                </div>
                <ul class="why-list">
                    <?php foreach ($reasons as $reason): ?>
                    <li class="why-item">
                        <span class="why-icon"><i class="fas <?= h($iconClass($reason['icon'], 'fa-check')) ?>" aria-hidden="true"></i></span>
                        <h3><?= h($reason['title']) ?></h3>
                        <p><?= h($reason['body']) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($steps): ?>
    <section class="section<?= $tone() ?>" id="how-it-works">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_steps_title', 'How it works')) ?></h2>
                <p><?= h(get_setting('home_steps_text')) ?></p>
            </div>
            <ol class="steps">
                <?php foreach ($steps as $i => $st): ?>
                <li class="step">
                    <div class="step-top"><span class="step-num" aria-hidden="true"><?= $i + 1 ?></span><i class="fas <?= h($iconClass($st['icon'], 'fa-check')) ?> step-icon" aria-hidden="true"></i></div>
                    <h3><?= h($st['title']) ?></h3>
                    <p><?= h($st['body']) ?></p>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($roles): ?>
    <section class="section<?= $tone() ?>" id="who-uses-it">
        <div class="container">
            <div class="roles-layout<?= $rolesPhoto === '' ? ' no-photo' : '' ?>">
                <?php if ($rolesPhoto !== ''): ?>
                <figure class="roles-photo"><img src="<?= h($rolesPhoto) ?>" alt="A boda-boda rider carrying a passenger" width="1000" height="1250" loading="lazy" decoding="async"></figure>
                <?php endif; ?>
                <div>
                    <div class="section-head section-head-start">
                        <h2><?= h(get_setting('home_roles_title', 'Who uses it')) ?></h2>
                        <p><?= h(get_setting('home_roles_text')) ?></p>
                    </div>
                    <div class="role-grid">
                        <?php foreach ($roles as $ro): ?>
                        <div class="role-card">
                            <span class="role-icon"><i class="fas <?= h($iconClass($ro['icon'], 'fa-user')) ?>" aria-hidden="true"></i></span>
                            <div><h3><?= h($ro['title']) ?></h3><p><?= h($ro['body']) ?></p></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($features): ?>
    <section class="section<?= $tone() ?>" id="features">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_features_title', 'Features')) ?></h2>
                <p><?= h(get_setting('home_features_text')) ?></p>
            </div>
            <div class="feature-grid">
                <?php foreach ($features as $f): ?>
                <div class="feature">
                    <div class="icon"><i class="fas <?= h($iconClass($f['icon'], 'fa-check')) ?>" aria-hidden="true"></i></div>
                    <h3><?= h($f['title']) ?></h3>
                    <p><?= h($f['body']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showStats && $totalRiders > 0): ?>
    <section class="photo-band stats-band" aria-label="Live totals"<?= $photoStyle('home_stats_image') ?>>
        <div class="container">
            <div class="stats-grid">
                <div class="stat"><div class="num"><?= number_format($totalRiders) ?></div><div class="lbl">Riders registered</div></div>
                <div class="stat"><div class="num"><?= number_format($activeRiders) ?></div><div class="lbl">Permits valid today</div></div>
                <div class="stat"><div class="num"><?= h($revenueText) ?></div><div class="lbl">Permit fees collected</div></div>
                <div class="stat"><div class="num"><?= $compliance ?>%</div><div class="lbl">Riders with a valid permit</div></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($liveCities || $faqPreview): ?>
    <section class="section<?= $tone() ?>" id="questions">
        <div class="container">
            <div class="split-thirds<?= $liveCities ? '' : ' is-single' ?>">
                <?php if ($liveCities): ?>
                <div class="split-aside">
                    <h2 class="split-title"><?= h(get_setting('home_cities_title', 'Cities')) ?></h2>
                    <p class="split-text"><?= h(get_setting('home_cities_text')) ?></p>
                    <ul class="city-list">
                        <?php foreach ($liveCities as $lc): ?><li><i class="fas fa-location-dot" aria-hidden="true"></i><?= h($lc['name']) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($faqPreview): ?>
                <div class="split-main">
                    <h2 class="split-title"><?= h(get_setting('home_faq_title', 'Common questions')) ?></h2>
                    <div class="faq-preview">
                        <?php foreach ($faqPreview as $q): ?><?= render_faq_item($q['title'], $q['body']) ?><?php endforeach; ?>
                    </div>
                    <a class="link-arrow" href="<?= BASE_URL ?>/faq.php">Read all questions and answers <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section<?= $tone() ?> verify-band" id="check-permit">
        <div class="container">
            <div class="verify-inner">
                <div>
                    <h2><?= h(get_setting('home_verify_title', 'Check a permit')) ?></h2>
                    <p><?= h(get_setting('home_verify_text')) ?></p>
                </div>
                <form method="get" action="<?= BASE_URL ?>/verify.php" class="verify-form" role="search" aria-label="Check a permit number">
                    <label for="homePermit">Permit number on the ID card</label>
                    <div class="verify-row">
                        <input id="homePermit" name="code" required maxlength="40" pattern="[A-Za-z0-9\-]{4,40}" data-pattern-message="Permit numbers use letters, digits and dashes, for example BODA-LIR-000123." placeholder="BODA-LIR-000123" autocapitalize="characters" autocomplete="off" enterkeyhint="search">
                        <button type="submit" class="btn-brand"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Check permit</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="photo-band cta-band"<?= $photoStyle('home_cta_image') ?>>
        <div class="container text-center">
            <h2><?= h(get_setting('home_cta_title')) ?></h2>
            <p><?= h(get_setting('home_cta_text')) ?></p>
            <div class="cta-actions">
                <a class="btn-light-solid" href="<?= BASE_URL ?>/contact.php"><i class="fas fa-envelope" aria-hidden="true"></i> <?= h(get_setting('home_cta_button', 'Contact us')) ?></a>
                <a class="btn-outline-light" href="<?= BASE_URL ?>/p/about">About <?= h(get_setting('site_name', 'BodaERP')) ?></a>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
