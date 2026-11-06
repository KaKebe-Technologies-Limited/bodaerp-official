<?php
// One rider's permit card, front and back, laid out like the Ugandan national ID (ISO ID-1, 85.6 x 54 mm).
// Front: city emblem and heading, photo with the city name and permit digits under it, surname / given / other
// names beside sex, birth, plate, NIN and dates, the holder's signature, a ghost photo and the card number on the
// edge (the ghost only when there is a photo). Back: where the rider works and who to call (tinted panel), the QR
// check, the property statement and conditions one sentence per line, and the machine-readable zone.
// Expects: $cardRider (id_card_rider()), $cardCity (cities row), $cardRow (id_cards row or null), $cardCfg (id_card_settings()).
// Optional: $cardSide ('both' | 'front' | 'back'), $cardVoid (text stamped across a card that no longer verifies), $cardZoom (bool).
// data-card-text / data-card-part let the card page preview edits before they are saved (assets/js/id-card.js).
$cardSide = $cardSide ?? 'both';
$cardVoid = $cardVoid ?? '';
$cardZoom = $cardZoom ?? true;
$cardView = id_card_print_view($cardRider, $cardRow, $cardCfg);
$cardNames = id_card_name_parts($cardView['name']);
$cardLogo = !empty($cardCity['logo_path']) ? media_url($cardCity['logo_path']) : brand_logo_url();
$cardCityName = (string) ($cardCity['name'] ?? '');
$cardIssuer = $cardCityName === '' ? '' : (preg_match('/council$/i', $cardCityName) ? $cardCityName : $cardCityName . ' Council');
$cardSerial = $cardRow['serial'] ?? $cardRider['id_number'];
$cardDate = fn(?string $d) => $d ? date('d.m.Y', strtotime($d)) : '–';
$cardVoidAttr = $cardVoid !== '' ? ' is-void" data-void="' . h($cardVoid) : '';
$cardPhoto = !empty($cardRider['photo_path']) ? media_url($cardRider['photo_path']) : '';
$cardSign = !empty($cardRider['signature_path']) ? media_url($cardRider['signature_path']) : '';
$cardPhotoStyle = '--photo-y: ' . (int) $cardView['photo_y'] . '%; --photo-zoom: ' . ((int) $cardView['photo_zoom'] / 100) . ';';
$cardSex = match (strtolower((string) $cardRider['gender'])) { 'male' => 'M', 'female' => 'F', default => '–' };
$cardDigits = preg_replace('/^.*-/', '', (string) $cardRider['id_number']);
$cardWord = id_card_city_word($cardCity ?? []);
$cardWordSize = match (true) { mb_strlen($cardWord) <= 4 => 15, mb_strlen($cardWord) <= 6 => 11.5, default => 9 };
// Each sentence of the back text on its own line (assets/js/id-card.js shrinks a long line to fit, never wraps it).
$cardSentences = fn(string $text) => array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+(?=[A-Z])/', $text) ?: [])));
$cardBackLines = $cardSentences(str_replace('{city}', $cardIssuer, $cardCfg['statement']));
$cardHasNin = $cardView['show_nin'] && $cardRider['nin'];
$cardHasAddress = $cardView['show_address'] && $cardRider['physical_address'];
$cardHasKin = $cardView['show_kin'] && ($cardRider['next_of_kin_name'] || $cardRider['next_of_kin_contact']);
$cardReturn = implode(', ', array_filter([stripos((string) ($cardCity['address'] ?? ''), $cardCityName) === 0 ? '' : $cardIssuer, $cardCity['address'] ?? '', $cardCity['contact_phone'] ?? '']));
?>
<div class="id-pair" style="--card-colour: <?= h($cardCfg['colour']) ?>;">
    <?php if ($cardSide !== 'back'): ?>
    <div class="id-side id-side-front">
        <span class="id-side-label">Front</span>
        <?php if ($cardZoom): ?><button type="button" class="id-zoom-btn" data-card-zoom aria-label="Enlarge the front of the card" title="Enlarge"><i class="fas fa-magnifying-glass-plus" aria-hidden="true"></i></button><?php endif; ?>
        <div class="id-card idc-front<?= $cardPhoto ? '' : ' no-photo' ?><?= $cardVoidAttr ?>" style="<?= $cardPhotoStyle ?>">
            <?= id_card_pattern_svg('front') ?>
            <header class="idc-head">
                <img class="idc-emblem" alt="<?= h($cardCityName) ?> emblem" src="<?= h($cardLogo) ?>" data-hide-broken>
                <div class="idc-head-text">
                    <?php if ($cardCfg['country'] !== ''): ?><div class="idc-country"><?= h($cardCfg['country']) ?></div><?php endif; ?>
                    <div class="idc-issuer"><?= h($cardIssuer) ?></div>
                    <div class="idc-doc"><?= h($cardCfg['title']) ?></div>
                </div>
                <div class="idc-code"><?= h($cardWord) ?></div>
            </header>
            <div class="idc-ribbon" aria-hidden="true"><i></i><i></i><i></i></div>

            <div class="idc-photo-col">
                <div class="idc-photo">
                    <?php if ($cardPhoto): ?><img alt="Photo of <?= h($cardRider['full_name']) ?>" src="<?= h($cardPhoto) ?>">
                    <?php else: ?><div class="idc-photo-missing"><i class="fas fa-user" aria-hidden="true"></i><span>No photo</span></div><?php endif; ?>
                </div>
                <div class="idc-mark"><b style="font-size: <?= $cardWordSize ?>pt"><?= h($cardWord) ?></b><span><?= h($cardDigits) ?></span></div>
            </div>

            <div class="idc-fields">
                <dl class="idc-col">
                    <div class="idc-f"><dt>Surname</dt><dd data-card-text="surname"><?= h($cardNames['surname']) ?></dd></div>
                    <div class="idc-f"><dt>Given name</dt><dd data-card-text="given"><?= h($cardNames['given'] ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Other names</dt><dd data-card-text="other"><?= h($cardNames['other'] ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Permit no.</dt><dd class="idc-permit"><?= h($cardRider['id_number']) ?></dd></div>
                    <div class="idc-f"><dt>Date of issue</dt><dd data-card-text="issued_on"><?= $cardDate($cardView['issued_on']) ?></dd></div>
                </dl>
                <dl class="idc-col">
                    <div class="idc-f"><dt>Sex</dt><dd><?= h($cardSex) ?></dd></div>
                    <div class="idc-f" data-card-part="dob"<?= $cardView['show_dob'] ? '' : ' hidden' ?>><dt>Date of birth</dt><dd><?= $cardDate($cardRider['date_of_birth']) ?></dd></div>
                    <div class="idc-f"><dt>Motorcycle</dt><dd class="idc-plate"><?= h($cardRider['bike_plate'] ?: '–') ?></dd></div>
                    <div class="idc-f" data-card-part="nin"<?= $cardHasNin ? '' : ' hidden' ?>><dt>NIN</dt><dd class="idc-mono"><?= h($cardRider['nin'] ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Date of expiry</dt><dd class="idc-expiry"><?= $cardDate($cardRider['expiry_date']) ?></dd></div>
                </dl>
            </div>

            <div class="idc-sign">
                <span class="idc-sign-label">Holder's signature</span>
                <div class="idc-sign-box"><?php if ($cardSign): ?><img alt="Signature of <?= h($cardRider['full_name']) ?>" src="<?= h($cardSign) ?>"><?php endif; ?></div>
            </div>
            <?php if ($cardPhoto): ?><div class="idc-ghost" aria-hidden="true"><img alt="" src="<?= h($cardPhoto) ?>"></div><?php endif; ?>
            <div class="idc-edge" aria-label="Card number"><?= h($cardSerial) ?></div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($cardSide !== 'front'): ?>
    <div class="id-side id-side-back">
        <span class="id-side-label">Back</span>
        <?php if ($cardZoom): ?><button type="button" class="id-zoom-btn" data-card-zoom aria-label="Enlarge the back of the card" title="Enlarge"><i class="fas fa-magnifying-glass-plus" aria-hidden="true"></i></button><?php endif; ?>
        <div class="id-card idc-back<?= $cardVoidAttr ?>">
            <?= id_card_pattern_svg('back') ?>
            <div class="idc-panel">
                <dl class="idc-col">
                    <div class="idc-f"><dt>City</dt><dd><?= h($cardCityName ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Stage</dt><dd><?= h($cardRider['stage_name'] ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Route</dt><dd><?= h($cardRider['route'] ?: ($cardRider['stage_route'] ?: ($cardRider['stage_location'] ?: '–'))) ?></dd></div>
                    <div class="idc-f" data-card-part="address"<?= $cardHasAddress ? '' : ' hidden' ?>><dt>Home</dt><dd><?= h($cardRider['physical_address'] ?: '–') ?></dd></div>
                </dl>
                <dl class="idc-col">
                    <div class="idc-f"><dt>Make</dt><dd><?= h($cardRider['bike_model'] ?: '–') ?></dd></div>
                    <div class="idc-f" data-card-part="kin"<?= $cardHasKin ? '' : ' hidden' ?>><dt>Next of kin</dt><dd><?= h($cardRider['next_of_kin_name'] ?: '–') ?></dd></div>
                    <div class="idc-f" data-card-part="kin"<?= $cardHasKin ? '' : ' hidden' ?>><dt>Kin's phone</dt><dd><?= h($cardRider['next_of_kin_contact'] ?: '–') ?></dd></div>
                    <div class="idc-f"><dt>Rider since</dt><dd><?= $cardDate($cardRider['member_since'] ?: $cardRider['created_at']) ?></dd></div>
                </dl>
            </div>
            <div class="idc-qr">
                <div class="qr-wrap" data-qr="<?= h(verifyUrl((int) $cardRider['id'])) ?>" data-qr-name="<?= h($cardSerial) ?>" role="img" aria-label="QR code that checks this permit"></div>
                <span>Scan to check</span>
            </div>
            <div class="idc-terms">
                <?php foreach ($cardBackLines as $i => $line): ?><p class="idc-fit<?= $i === 0 ? ' idc-statement' : '' ?>"><?= h($line) ?></p><?php endforeach; ?>
                <?php if ($cardReturn !== ''): ?><p class="idc-fit"><strong>If found, return to:</strong> <?= h($cardReturn) ?></p><?php endif; ?>
                <?php foreach ($cardSentences($cardCfg['terms']) as $line): ?><p class="idc-fit"><?= h($line) ?></p><?php endforeach; ?>
            </div>
            <div class="idc-mrz" aria-label="Machine-readable zone"><?php foreach (id_card_mrz($cardRider, $cardCity, $cardView) as $mrzLine): ?><div><?= h($mrzLine) ?></div><?php endforeach; ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>
