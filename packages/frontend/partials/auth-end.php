<?php
// Closing half of the sign-in shell (see auth-start.php).
?>
            <p class="login-footer"><?= site_text('footer_copyright', '&copy; {year} ' . h(get_setting('site_name', 'BodaERP'))) ?>
                · <a href="<?= BASE_URL ?>/p/privacy-policy">Privacy</a> · <a href="<?= BASE_URL ?>/contact.php">Help</a></p>
        </div>
    </div>
</main>
<script src="<?= BASE_URL ?>/assets/js/public.js?v=<?= @filemtime(__DIR__ . '/../assets/js/public.js') ?: 1 ?>"></script>
</body>
</html>
