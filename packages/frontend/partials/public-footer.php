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
        <div class="footer-grid">
            <div class="footer-col footer-brand">
                <a class="brand-mark" href="<?= BASE_URL ?>/">
                    <img src="<?= h(brand_logo_url()) ?>" alt="" width="40" height="40">
                    <span class="brand-word"><?php if (str_ends_with($siteName, 'ERP')): ?><span class="b1"><?= h(mb_substr($siteName, 0, -3)) ?></span><span class="b2">ERP</span><?php else: ?><?= h($siteName) ?><?php endif; ?></span>
                </a>
                <p class="footer-tagline"><?= h(get_setting('site_tagline', '')) ?></p>
                <?php if ($socials): ?>
                    <ul class="social" aria-label="<?= h($siteName) ?> on social media">
                        <?php foreach ($socials as $icon => $url): ?>
                            <li><a href="<?= h($url) ?>" target="_blank" rel="noopener" aria-label="<?= h($siteName . ' on ' . ucfirst(str_replace('x-twitter', 'X', $icon))) ?>"><i class="fab fa-<?= $icon ?>" aria-hidden="true"></i></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php foreach ($footerCols as $menu => $title): $links = menu_items($menu); ?>
                <nav class="footer-col" aria-label="<?= h($title) ?>">
                    <h3><?= h($title) ?></h3>
                    <ul class="links">
                        <?php foreach ($links as $l): ?><li><a href="<?= h(site_link($l['url'])) ?>"<?= $l['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= h($l['label']) ?></a></li><?php endforeach; ?>
                    </ul>
                </nav>
            <?php endforeach; ?>
            <div class="footer-col footer-contact">
                <h3>Contact</h3>
                <ul class="links">
                    <?php if ($a = get_setting('contact_address')): ?><li><i class="fas fa-location-dot" aria-hidden="true"></i><span><?= h($a) ?></span></li><?php endif; ?>
                    <?php if ($phone): ?><li><i class="fas fa-phone" aria-hidden="true"></i><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a></li><?php endif; ?>
                    <?php if ($whatsapp): ?><li><i class="fab fa-whatsapp" aria-hidden="true"></i><a href="https://wa.me/<?= h($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a></li><?php endif; ?>
                    <?php if ($e = get_setting('contact_email')): ?><li><i class="fas fa-envelope" aria-hidden="true"></i><a href="mailto:<?= h($e) ?>"><?= h($e) ?></a></li><?php endif; ?>
                    <?php if ($hrs = get_setting('contact_hours')): ?><li><i class="fas fa-clock" aria-hidden="true"></i><span><?= h($hrs) ?></span></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <?php $legal = menu_items('footer_legal'); if ($legal): ?>
            <nav aria-label="Legal"><ul class="legal-links">
                <?php foreach ($legal as $l): ?><li><a href="<?= h(site_link($l['url'])) ?>"><?= h($l['label']) ?></a></li><?php endforeach; ?>
            </ul></nav>
            <?php endif; ?>
            <p class="copyright"><?= site_text('footer_copyright', '&copy; {year} ' . $siteName) ?></p>
            <?php if ($credit = get_setting('footer_photo_credit')): ?><p class="photo-credit"><?= h($credit) ?> <a href="<?= BASE_URL ?>/p/photo-credits">Photo credits</a></p><?php endif; ?>
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
<script src="<?= BASE_URL ?>/assets/js/controls.js?v=<?= @filemtime(__DIR__ . '/../assets/js/controls.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/public.js?v=<?= @filemtime(__DIR__ . '/../assets/js/public.js') ?: 1 ?>"></script>
