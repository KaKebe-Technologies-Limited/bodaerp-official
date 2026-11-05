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
$features = content_items('home_feature');
$steps = content_items('home_step');
$roles = content_items('home_role');
$faqPreview = array_slice(content_items('faq'), 0, 4);
$liveCities = setting_bool('home_show_cities', true) ? fetchAll("SELECT name FROM cities WHERE status = 'active' ORDER BY name") : [];
$iconClass = fn(?string $icon, string $fallback) => preg_replace('/[^a-z0-9- ]/', '', (string) $icon) ?: $fallback;
$seo = ['title' => get_setting('seo_default_title', 'BodaERP'), 'description' => get_setting('seo_default_description', '')];
$currentPath = '/';
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?></head>
<body class="public">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>

<main id="main">
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-7">
                    <h1><?= h(get_setting('home_hero_title', get_setting('site_name', 'BodaERP'))) ?></h1>
                    <p class="lead-text"><?= h(get_setting('home_hero_text')) ?></p>
                    <div class="actions">
                        <a class="btn-brand" href="<?= BASE_URL ?>/login.php"><i class="fas fa-right-to-bracket" aria-hidden="true"></i> <?= h(get_setting('home_cta_primary', 'Sign in')) ?></a>
                        <a class="btn-ghost" href="<?= BASE_URL ?>/verify.php"><i class="fas fa-qrcode" aria-hidden="true"></i> <?= h(get_setting('home_cta_secondary', 'Verify a rider')) ?></a>
                    </div>
                    <?php if ($showStats && $totalRiders > 0): ?>
                    <div class="hero-stats">
                        <div><strong><?= number_format($totalRiders) ?></strong><span>riders registered</span></div>
                        <div><strong><?= number_format($cityCount) ?></strong><span><?= $cityCount === 1 ? 'city' : 'cities' ?> using BodaERP</span></div>
                        <div><strong><?= $compliance ?>%</strong><span>with a valid permit</span></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-5">
                    <div class="hero-card" aria-label="Example of a permit check">
                        <div class="card-title"><i class="fas fa-shield-halved" aria-hidden="true"></i> What a QR scan shows an officer</div>
                        <div class="row-line"><span>Permit status</span><span class="status-ok">Valid</span></div>
                        <div class="row-line"><span>Stage</span><span>Named stage and city</span></div>
                        <div class="row-line"><span>Photo</span><span>Taken at registration</span></div>
                        <div class="row-line"><span>Valid until</span><span>Date of next renewal</span></div>
                        <a class="btn-ghost w-100 justify-content-center mt-3" href="<?= BASE_URL ?>/verify.php">Check a permit number</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($steps): ?>
    <section class="section" id="how-it-works">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_steps_title', 'How it works')) ?></h2>
                <p><?= h(get_setting('home_steps_text')) ?></p>
            </div>
            <ol class="steps">
                <?php foreach ($steps as $i => $st): ?>
                <li class="step">
                    <span class="step-num" aria-hidden="true"><?= $i + 1 ?></span>
                    <h3><?= h($st['title']) ?></h3>
                    <p><?= h($st['body']) ?></p>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($roles): ?>
    <section class="section section-alt" id="who-uses-it">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_roles_title', 'Who uses it')) ?></h2>
                <p><?= h(get_setting('home_roles_text')) ?></p>
            </div>
            <div class="row g-3 g-lg-4">
                <?php foreach ($roles as $ro): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="role-card">
                        <i class="fas <?= h($iconClass($ro['icon'], 'fa-user')) ?>" aria-hidden="true"></i>
                        <h3><?= h($ro['title']) ?></h3>
                        <p><?= h($ro['body']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($features): ?>
    <section class="section" id="features">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_features_title', 'Features')) ?></h2>
                <p><?= h(get_setting('home_features_text')) ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($features as $f): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="fas <?= h(preg_replace('/[^a-z0-9- ]/', '', (string) $f['icon']) ?: 'fa-check') ?>" aria-hidden="true"></i></div>
                        <h3><?= h($f['title']) ?></h3>
                        <p><?= h($f['body']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showStats && $totalRiders > 0): ?>
    <section class="stats-band" aria-label="Live totals">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-6 col-md-3"><div class="num"><?= number_format($totalRiders) ?></div><div class="lbl">Riders registered</div></div>
                <div class="col-6 col-md-3"><div class="num"><?= number_format($activeRiders) ?></div><div class="lbl">Permits currently valid</div></div>
                <div class="col-6 col-md-3"><div class="num"><?= h($revenueText) ?></div><div class="lbl">Permit fees collected</div></div>
                <div class="col-6 col-md-3"><div class="num"><?= $compliance ?>%</div><div class="lbl">Compliance rate</div></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($liveCities): ?>
    <section class="section" id="cities">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_cities_title', 'Cities')) ?></h2>
                <p><?= h(get_setting('home_cities_text')) ?></p>
            </div>
            <ul class="city-list">
                <?php foreach ($liveCities as $lc): ?><li><i class="fas fa-location-dot" aria-hidden="true"></i><?= h($lc['name']) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($faqPreview): ?>
    <section class="section section-alt" id="questions">
        <div class="container">
            <div class="section-head">
                <h2><?= h(get_setting('home_faq_title', 'Common questions')) ?></h2>
            </div>
            <div class="faq-preview">
                <?php foreach ($faqPreview as $q): ?>
                <details class="faq-item">
                    <summary><?= h($q['title']) ?></summary>
                    <div class="answer"><?= nl2br(h($q['body'])) ?></div>
                </details>
                <?php endforeach; ?>
                <p class="text-center mt-4 mb-0"><a class="btn-ghost" href="<?= BASE_URL ?>/faq.php"><i class="fas fa-circle-question" aria-hidden="true"></i> Read all questions and answers</a></p>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="verify-band" id="check-permit">
        <div class="container">
            <div class="verify-inner">
                <div>
                    <h2><?= h(get_setting('home_verify_title', 'Check a permit')) ?></h2>
                    <p><?= h(get_setting('home_verify_text')) ?></p>
                </div>
                <form method="get" action="<?= BASE_URL ?>/verify.php" class="verify-form" role="search" aria-label="Check a permit number">
                    <label class="visually-hidden" for="homePermit">Permit number</label>
                    <input id="homePermit" name="code" required maxlength="40" pattern="[A-Za-z0-9-]{4,40}" placeholder="BODA-LIR-000123" autocapitalize="characters">
                    <button type="submit" class="btn-brand"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Check permit</button>
                </form>
            </div>
        </div>
    </section>

    <section class="cta-band text-center">
        <div class="container">
            <h2><?= h(get_setting('home_cta_title')) ?></h2>
            <p><?= h(get_setting('home_cta_text')) ?></p>
            <a class="btn-light-solid" href="<?= BASE_URL ?>/contact.php"><i class="fas fa-envelope" aria-hidden="true"></i> <?= h(get_setting('home_cta_button', 'Contact us')) ?></a>
        </div>
    </section>
</main>

<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
