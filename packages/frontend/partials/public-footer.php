<?php
// Site footer: menus, contact details and social links from Super Admin → Content, plus the cookie notice.
$siteName = get_setting('site_name', 'BodaERP');
$footerCols = ['footer_product' => 'Product', 'footer_company' => 'Company'];
$phone = get_setting('contact_phone');
$whatsapp = preg_replace('/\D/', '', get_setting('contact_whatsapp'));
$socials = array_filter(['facebook' => get_setting('social_facebook'), 'x-twitter' => get_setting('social_x'), 'linkedin' => get_setting('social_linkedin')],
    fn($u) => preg_match('#^https://#i', $u));
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <a class="brand-mark mb-2" href="<?= BASE_URL ?>/">
                    <img src="<?= h(brand_logo_url()) ?>" alt="" width="40" height="40">
                    <span class="brand-word"><?php if (str_ends_with($siteName, 'ERP')): ?><span class="b1"><?= h(mb_substr($siteName, 0, -3)) ?></span><span class="b2">ERP</span><?php else: ?><?= h($siteName) ?><?php endif; ?></span>
                </a>
                <p class="mt-2 mb-3" style="max-width:34ch"><?= h(get_setting('site_tagline', '')) ?></p>
                <?php if ($socials): ?>
                    <div class="social">
                        <?php foreach ($socials as $icon => $url): ?>
                            <a href="<?= h($url) ?>" target="_blank" rel="noopener" aria-label="<?= h($siteName . ' on ' . ucfirst(str_replace('x-twitter', 'X', $icon))) ?>"><i class="fab fa-<?= $icon ?>" aria-hidden="true"></i></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php foreach ($footerCols as $menu => $title): $links = menu_items($menu); if (!$links) continue; ?>
                <div class="col-6 col-lg-2">
                    <h3><?= h($title) ?></h3>
                    <ul class="links">
                        <?php foreach ($links as $l): ?><li><a href="<?= h(site_link($l['url'])) ?>"<?= $l['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= h($l['label']) ?></a></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
            <div class="col-lg-4">
                <h3>Contact</h3>
                <ul class="links">
                    <?php if ($a = get_setting('contact_address')): ?><li><i class="fas fa-location-dot me-2" aria-hidden="true"></i><?= h($a) ?></li><?php endif; ?>
                    <?php if ($phone): ?><li><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><i class="fas fa-phone me-2" aria-hidden="true"></i><?= h($phone) ?></a></li><?php endif; ?>
                    <?php if ($whatsapp): ?><li><a href="https://wa.me/<?= h($whatsapp) ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp me-2" aria-hidden="true"></i>WhatsApp us</a></li><?php endif; ?>
                    <?php if ($e = get_setting('contact_email')): ?><li><a href="mailto:<?= h($e) ?>"><i class="fas fa-envelope me-2" aria-hidden="true"></i><?= h($e) ?></a></li><?php endif; ?>
                    <?php if ($hrs = get_setting('contact_hours')): ?><li><i class="fas fa-clock me-2" aria-hidden="true"></i><?= h($hrs) ?></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="legal">
            <span><?= site_text('footer_copyright', '&copy; {year} ' . $siteName) ?></span>
            <span>
                <?php foreach (menu_items('footer_legal') as $i => $l): ?><?= $i ? ' · ' : '' ?><a href="<?= h(site_link($l['url'])) ?>"><?= h($l['label']) ?></a><?php endforeach; ?>
            </span>
        </div>
    </div>
</footer>
<?php if (setting_bool('feature_cookie_banner', true)): ?>
<div class="cookie-notice" role="region" aria-label="Cookie notice" data-cookie-notice>
    <p><?= h(get_setting('cookie_banner_text', '')) ?> <a href="<?= BASE_URL ?>/p/cookie-policy">Cookie policy</a></p>
    <button type="button" class="btn-brand" data-cookie-accept>OK, got it</button>
</div>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/public.js?v=<?= @filemtime(__DIR__ . '/../assets/js/public.js') ?: 1 ?>"></script>
