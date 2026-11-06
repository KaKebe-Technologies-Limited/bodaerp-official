<?php
// Closing half of the sign-in shell (see auth-start.php).
?>
            <?php
            $authPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $authLinks = array_filter([
                'login.php' => $authPage !== 'login.php' ? 'Sign in' : null,
                'forgot-password.php' => !in_array($authPage, ['login.php', 'forgot-password.php', 'reset-password.php'], true) ? 'Forgot password' : null,
                '' => 'Home', 'verify.php' => 'Check a permit', 'faq.php' => 'FAQ', 'contact.php' => 'Contact', 'p/privacy-policy' => 'Privacy',
            ]);
            ?>
            <nav class="login-links" aria-label="More">
                <?php foreach ($authLinks as $path => $label): ?><a href="<?= BASE_URL ?>/<?= h($path) ?>"><?= h($label) ?></a><?php endforeach; ?>
            </nav>
            <p class="login-footer"><?= site_text('footer_copyright', '&copy; {year} ' . h(get_setting('site_name', 'BodaERP'))) ?></p>
        </div>
    </div>
</main>
<script src="<?= BASE_URL ?>/assets/js/controls.js?v=<?= @filemtime(__DIR__ . '/../assets/js/controls.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/public.js?v=<?= @filemtime(__DIR__ . '/../assets/js/public.js') ?: 1 ?>"></script>
</body>
</html>
