<?php
// Contact form: saved to contact_messages (Super Admin → Inbox), emailed to the contact inbox,
// and a copy is emailed to the sender. Spam protection: CSRF, honeypot field, minimum fill time, rate limit.
require_once __DIR__ . '/packages/backend/site.php';

$enabled = setting_bool('feature_contact_form', true);
$values = ['name' => '', 'email' => '', 'phone' => '', 'organisation' => '', 'subject' => '', 'message' => ''];
$errors = [];
$sent = isset($_GET['sent']);

if ($enabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($values as $k => $_) $values[$k] = trim((string) ($_POST[$k] ?? ''));
    $isBot = ($_POST['website'] ?? '') !== '' || (time() - (int) ($_SESSION['contact_form_at'] ?? 0)) < 3;

    if ($values['name'] === '' || mb_strlen($values['name']) > 120) $errors['name'] = 'Enter your name.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190) $errors['email'] = 'Enter a valid email address so we can reply.';
    if ($values['phone'] !== '' && !preg_match('/^[+\d][\d\s()-]{6,24}$/', $values['phone'])) $errors['phone'] = 'Enter a phone number with digits only, e.g. +256 772 123456.';
    if (mb_strlen($values['organisation']) > 150) $errors['organisation'] = 'Keep this under 150 characters.';
    if ($values['subject'] === '' || mb_strlen($values['subject']) > 200) $errors['subject'] = 'Enter a subject (up to 200 characters).';
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 5000) $errors['message'] = 'Write a message of 10 to 5,000 characters.';

    if (!$errors && !rate_limit('contact:' . client_ip(), 5, 3600)) {
        $errors['form'] = 'You have sent several messages in the last hour. Please wait a while, or email us directly.';
    }

    if (!$errors) {
        if (!$isBot) {
            runQuery("INSERT INTO contact_messages (name, email, phone, organisation, subject, message, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)", [
                $values['name'], mb_strtolower($values['email']), $values['phone'] ?: null, $values['organisation'] ?: null,
                $values['subject'], $values['message'], client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
            $vars = $values + ['inbox_url' => app_url('pages/superadmin/inbox.php?id=' . db()->insert_id)];
            $vars['phone'] = $vars['phone'] ?: 'not given';
            $vars['organisation'] = $vars['organisation'] ?: 'not given';
            $inbox = get_setting('contact_inbox_email', get_setting('contact_email', ''));
            if ($inbox !== '') send_template_email('contact_admin_alert', $inbox, $vars);
            send_template_email('contact_autoreply', $values['email'], $values);
        }
        unset($_SESSION['contact_form_at']);
        redirect('/contact.php?sent=1');
    }
}
$_SESSION['contact_form_at'] = $_SESSION['contact_form_at'] ?? time();

$phone = get_setting('contact_phone');
$whatsapp = preg_replace('/\D/', '', get_setting('contact_whatsapp'));
$mapUrl = get_setting('contact_map_url');
$seo = ['title' => 'Contact the BodaERP team | ' . get_setting('site_name', 'BodaERP'),
    'description' => 'Questions about rider accounts, permit payments or bringing BodaERP to your city council? Send us a message.'];
$currentPath = '/contact.php';
$field = function (string $name, string $label, string $type = 'text', bool $required = true, string $hint = '') use ($values, $errors) {
    $err = $errors[$name] ?? null;
    $id = 'c_' . $name;
    $attrs = 'id="' . $id . '" name="' . $name . '"' . ($required ? ' required' : '') . ($err ? ' class="is-invalid" aria-invalid="true" aria-describedby="' . $id . '_err"' : '');
    echo '<div class="field"><label for="' . $id . '">' . h($label) . ($required ? '' : ' <span class="text-muted fw-normal">(optional)</span>') . '</label>';
    if ($type === 'textarea') echo '<textarea ' . $attrs . ' rows="6" maxlength="5000">' . h($values[$name]) . '</textarea>';
    else echo '<input type="' . $type . '" ' . $attrs . ' value="' . h($values[$name]) . '"' . ($type === 'email' ? ' autocomplete="email"' : '') . '>';
    if ($hint) echo '<div class="hint">' . h($hint) . '</div>';
    if ($err) echo '<div class="error" id="' . $id . '_err">' . h($err) . '</div>';
    echo '</div>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head><?php require __DIR__ . '/packages/frontend/partials/public-head.php'; ?></head>
<body class="public">
<?php require __DIR__ . '/packages/frontend/partials/public-header.php'; ?>
<main id="main">
    <section class="page-hero">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= BASE_URL ?>/">Home</a> / Contact</nav>
            <h1>Contact the BodaERP team</h1>
            <p>Riders: for changes to your record, speak to your stage chairperson first. Councils and partners: tell us what you need and we will reply within two working days.</p>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="panel">
                        <?php if ($sent): ?>
                            <div class="notice notice-success" role="status"><?= h(get_setting('msg_contact_sent', 'Thank you. Your message has been sent.')) ?></div>
                            <a class="btn-ghost" href="<?= BASE_URL ?>/contact.php">Send another message</a>
                        <?php elseif (!$enabled): ?>
                            <div class="notice notice-info">The contact form is switched off at the moment. Please use the email address or phone number on this page.</div>
                        <?php else: ?>
                            <h2 class="mb-3">Send a message</h2>
                            <?php if (isset($errors['form'])): ?><div class="notice notice-error" role="alert"><?= h($errors['form']) ?></div>
                            <?php elseif ($errors): ?><div class="notice notice-error" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
                            <form method="post" novalidate data-loading="Sending">
                                <?= csrf_field() ?>
                                <div class="honeypot" aria-hidden="true"><label for="c_website">Leave this empty</label><input type="text" id="c_website" name="website" tabindex="-1" autocomplete="off"></div>
                                <div class="row g-0 gx-3">
                                    <div class="col-md-6"><?php $field('name', 'Your name'); ?></div>
                                    <div class="col-md-6"><?php $field('email', 'Email address', 'email'); ?></div>
                                    <div class="col-md-6"><?php $field('phone', 'Phone number', 'tel', false); ?></div>
                                    <div class="col-md-6"><?php $field('organisation', 'City council or organisation', 'text', false); ?></div>
                                </div>
                                <?php $field('subject', 'Subject'); ?>
                                <?php $field('message', 'Message', 'textarea', true, 'Up to 5,000 characters.'); ?>
                                <p class="small text-muted">We use these details only to reply to you. See our <a href="<?= BASE_URL ?>/p/privacy-policy">privacy policy</a>.</p>
                                <button type="submit" class="btn-brand"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send message</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel">
                        <h2 class="mb-2">Other ways to reach us</h2>
                        <ul class="contact-list">
                            <?php if ($e = get_setting('contact_email')): ?><li><i class="fas fa-envelope" aria-hidden="true"></i><div><strong>Email</strong><br><a href="mailto:<?= h($e) ?>"><?= h($e) ?></a></div></li><?php endif; ?>
                            <?php if ($phone): ?><li><i class="fas fa-phone" aria-hidden="true"></i><div><strong>Phone</strong><br><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= h($phone) ?></a></div></li><?php endif; ?>
                            <?php if ($whatsapp): ?><li><i class="fab fa-whatsapp" aria-hidden="true"></i><div><strong>WhatsApp</strong><br><a href="https://wa.me/<?= h($whatsapp) ?>" target="_blank" rel="noopener">Chat with us on WhatsApp</a></div></li><?php endif; ?>
                            <?php if ($a = get_setting('contact_address')): ?><li><i class="fas fa-location-dot" aria-hidden="true"></i><div><strong>Office</strong><br><?= h($a) ?><?php if (preg_match('#^https://#', $mapUrl)): ?><br><a href="<?= h($mapUrl) ?>" target="_blank" rel="noopener">Open in Google Maps</a><?php endif; ?></div></li><?php endif; ?>
                            <?php if ($hrs = get_setting('contact_hours')): ?><li><i class="fas fa-clock" aria-hidden="true"></i><div><strong>Office hours</strong><br><?= h($hrs) ?></div></li><?php endif; ?>
                        </ul>
                        <p class="small text-muted mt-3 mb-0">Checking a rider's permit? Use <a href="<?= BASE_URL ?>/verify.php">Verify a rider</a>; no account needed.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/packages/frontend/partials/public-footer.php'; ?>
</body>
</html>
