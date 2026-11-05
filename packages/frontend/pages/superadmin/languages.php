<?php
// Super Admin → Languages: translations of the rider portal text. English lives in the code; each other language
// is typed here and shown to riders only when it is marked reviewed and the language is switched on in
// Text & branding → General → languages_enabled. SMS wording per language is edited under SMS.
require_once __DIR__ . '/../../../backend/admin_ui.php';
$me = require_role(['super_admin']);

$others = array_diff_key(LANGUAGES, ['en' => true]);
$lang = isset($others[$_GET['lang'] ?? '']) ? $_GET['lang'] : array_key_first($others);
$catalogue = translation_catalogue();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $lang = isset($others[$_POST['lang'] ?? '']) ? $_POST['lang'] : $lang;
    $saved = 0;
    foreach ((array) ($_POST['text'] ?? []) as $key => $text) {
        if (!isset($catalogue[$key])) continue;
        $text = trim((string) $text);
        $reviewed = !empty($_POST['reviewed'][$key]) ? 0 : 1;
        if ($text === '') {
            $saved += runQuery("DELETE FROM translations WHERE language = ? AND msg_key = ?", [$lang, $key])->affected_rows;
            continue;
        }
        $saved += max(0, runQuery("INSERT INTO translations (language, msg_key, text, needs_review, updated_by) VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE text = VALUES(text), needs_review = VALUES(needs_review), updated_by = VALUES(updated_by)",
            [$lang, $key, mb_substr($text, 0, 1000), $reviewed, (int) $me['user_id']])->affected_rows > 0 ? 1 : 0);
    }
    audit_log('UPDATE', 'translations', $lang, "$saved translation(s) changed");
    flash('success', "$saved change" . ($saved === 1 ? '' : 's') . ' saved for ' . LANGUAGES[$lang] . '.');
    redirect('/pages/superadmin/languages.php?lang=' . $lang);
}

$existing = array_column(fetchAll("SELECT msg_key, text, needs_review FROM translations WHERE language = ?", [$lang]), null, 'msg_key');
$reviewedCount = count(array_filter($existing, fn($t) => (int) $t['needs_review'] === 0 && isset($catalogue[$t['msg_key']])));
$enabled = isset(languages_enabled()[$lang]);

portal_start('superadmin', 'languages', 'Languages', [
    'subtitle' => 'Rider portal text in the languages riders speak',
]);
?>
<div class="layout-thirds mb-4">
    <section>
        <nav class="nav nav-pills mb-3" aria-label="Language">
            <?php foreach ($others as $code => $label): ?>
                <a class="nav-link<?= $code === $lang ? ' active' : '' ?>" href="?lang=<?= h($code) ?>"<?= $code === $lang ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="measure mb-0"><?= h(LANGUAGES[$lang]) ?>: <strong><?= $reviewedCount ?> of <?= count($catalogue) ?></strong> texts reviewed.
            <?= $enabled ? 'Riders can choose this language now.' : 'Riders cannot choose it yet.' ?></p>
    </section>
    <aside class="card">
        <div class="card-body">
            <h2 class="section-title">Before switching a language on</h2>
            <p class="small mb-2">A native speaker should read every text and tick "Reviewed". Unreviewed text is never shown; riders see English instead.</p>
            <p class="small mb-0">Then add the code (<?= h(implode(', ', array_keys($others))) ?>) to <a href="<?= BASE_URL ?>/pages/superadmin/site-settings.php?cat=general">languages_enabled</a>. SMS wording is translated under <a href="<?= BASE_URL ?>/pages/superadmin/sms.php">SMS</a>.</p>
        </div>
    </aside>
</div>

<?php if (!$catalogue): ?>
    <section class="card"><div class="card-body"><?= empty_state('fa-language', 'No translatable text found', 'Rider pages mark their text for translation with t(). None was found in the code.') ?></div></section>
<?php else: ?>
<form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="lang" value="<?= h($lang) ?>">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-top mb-0 translations-table">
                <thead><tr><th scope="col" class="w-40">English</th><th scope="col"><?= h(LANGUAGES[$lang]) ?></th><th scope="col" class="text-center">Reviewed</th></tr></thead>
                <tbody>
                <?php foreach ($catalogue as $key => $source): $row = $existing[$key] ?? null; $fid = 'tr-' . md5($key); ?>
                    <tr>
                        <td><label for="<?= $fid ?>"><?= h($source['english']) ?></label><div class="small text-muted"><?= h($source['file']) ?></div></td>
                        <td><textarea class="form-control form-control-sm" id="<?= $fid ?>" name="text[<?= h($key) ?>]" rows="<?= mb_strlen($source['english']) > 80 ? 3 : 1 ?>" maxlength="1000"><?= h($row['text'] ?? '') ?></textarea></td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="reviewed[<?= h($key) ?>]" value="1" aria-label="Reviewed: <?= h($source['english']) ?>"<?= $row && (int) $row['needs_review'] === 0 ? ' checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
        <button class="btn btn-primary" data-busy-label="Saving">Save <?= h(LANGUAGES[$lang]) ?> text</button>
    </div>
</form>
<?php endif; ?>
<?php portal_end(); ?>
