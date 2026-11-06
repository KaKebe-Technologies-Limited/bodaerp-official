<?php
// Card page body for staff (chairperson and city admin): the card, its status and print/hand-over steps, issuing a
// replacement, and every card the rider has had. The page has already called portal_start().
// Expects: $cardRider, $cardCity, $cardRow (current card or null), $cardCfg, $cardHistory, $cardSelf (this page's URL),
// $cardBack (url => label), $cardCanCancel (bool), $cardEditUrl (string|null).
$cardProblems = id_card_problems($cardRider);
$cardLastEnded = $cardRow ? null : ($cardHistory[0] ?? null);
$cardRiderName = $cardRider['full_name'];
?>
<?php
$cardCheckUrl = verifyUrl((int) $cardRider['id']);
$cardGoTo = [];
if ($cardEditUrl) $cardGoTo[] = ['label' => 'Edit rider details', 'icon' => 'pen', 'href' => BASE_URL . $cardEditUrl];
foreach ($cardBack as $backUrl => $backLabel) $cardGoTo[] = ['label' => $backLabel, 'icon' => 'arrow-left', 'href' => BASE_URL . $backUrl];
?>
<div class="doc-layout">
    <aside class="doc-rail">
        <?= action_rail([
            'Print' => $cardRow ? [
                ['label' => 'Print both sides', 'icon' => 'print', 'tone' => 'primary', 'attrs' => ['data-card-print' => 'both']],
                ['label' => 'Front only', 'icon' => 'id-card', 'attrs' => ['data-card-print' => 'front']],
                ['label' => 'Back only', 'icon' => 'qrcode', 'attrs' => ['data-card-print' => 'back']],
            ] : [],
            'Check and share' => $cardRow ? [
                ['label' => 'Enlarge', 'icon' => 'magnifying-glass-plus', 'attrs' => ['data-card-zoom' => true]],
                ['label' => 'Save QR image', 'icon' => 'download', 'attrs' => ['data-qr-download' => true]],
                ['label' => 'Copy check link', 'icon' => 'link', 'attrs' => ['data-copy' => $cardCheckUrl]],
                ['label' => 'Open the check page', 'icon' => 'up-right-from-square', 'href' => $cardCheckUrl, 'external' => true],
            ] : [],
            'Go to' => $cardGoTo,
        ], 'Card actions') ?>
    </aside>

    <div class="doc-main no-print">
        <?php if ($cardRow): $cardEdit = id_card_print_view($cardRider, $cardRow, $cardCfg); $cardLocked = $cardRow['status'] === 'collected'; ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title">What is printed on this card</h2>
            <?php if ($cardLocked): ?>
                <p class="small text-muted mb-0">This card is already with the rider, so it can no longer be changed. To print different details, make a new card below with the reason "Details changed".</p>
            <?php else: ?>
            <form method="post" class="card-edit-form" data-card-editor>
                <?= csrf_field() ?><input type="hidden" name="card_action" value="design">
                <label class="form-label" for="cardName">Name as printed</label>
                <input type="text" class="form-control" id="cardName" name="name" maxlength="40" value="<?= h($cardEdit['name']) ?>" data-card-edit="name" autocomplete="off">
                <div class="form-text mb-2">To shorten a long name use the registered names or their first letter (for example <?= h(mb_strtoupper(preg_replace('/^(\S+)\s+(\S)\S*.*$/u', '$1 $2.', trim($cardRider['full_name'])))) ?>); any other name is refused.</div>
                <label class="form-label" for="cardIssued">Date of issue</label>
                <input type="date" class="form-control mb-2" id="cardIssued" name="issued_on" value="<?= h($cardEdit['issued_on']) ?>" min="<?= h(substr((string) $cardRow['issued_at'], 0, 10)) ?>" max="<?= date('Y-m-d') ?>" data-card-edit="issued_on">
                <?php if (!empty($cardRider['photo_path'])): ?>
                <label class="form-label" for="cardPhotoY">Photo position (up and down)</label>
                <input type="range" class="form-range" id="cardPhotoY" name="photo_y" min="0" max="100" value="<?= (int) $cardEdit['photo_y'] ?>" data-card-photo="y">
                <label class="form-label" for="cardPhotoZoom">Photo size</label>
                <input type="range" class="form-range mb-2" id="cardPhotoZoom" name="photo_zoom" min="100" max="160" value="<?= (int) $cardEdit['photo_zoom'] ?>" data-card-photo="zoom">
                <?php else: ?>
                <input type="hidden" name="photo_y" value="<?= (int) $cardEdit['photo_y'] ?>"><input type="hidden" name="photo_zoom" value="<?= (int) $cardEdit['photo_zoom'] ?>">
                <?php endif; ?>
                <fieldset class="mb-2"><legend class="form-label mb-1">Show on the card</legend>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="cardShowNin" name="show_nin" value="1"<?= $cardEdit['show_nin'] ? ' checked' : '' ?> data-card-toggle="nin"<?= $cardRider['nin'] ? '' : ' disabled' ?>><label class="form-check-label" for="cardShowNin">National ID number (NIN)<?= $cardRider['nin'] ? '' : ', none on record' ?></label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="cardShowDob" name="show_dob" value="1"<?= $cardEdit['show_dob'] ? ' checked' : '' ?> data-card-toggle="dob"><label class="form-check-label" for="cardShowDob">Date of birth</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="cardShowAddress" name="show_address" value="1"<?= $cardEdit['show_address'] ? ' checked' : '' ?> data-card-toggle="address"<?= $cardRider['physical_address'] ? '' : ' disabled' ?>><label class="form-check-label" for="cardShowAddress">Home address (back)<?= $cardRider['physical_address'] ? '' : ', none on record' ?></label></div>
                    <?php $cardKinOnFile = $cardRider['next_of_kin_name'] || $cardRider['next_of_kin_contact']; ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="cardShowKin" name="show_kin" value="1"<?= $cardEdit['show_kin'] && $cardKinOnFile ? ' checked' : '' ?> data-card-toggle="kin"<?= $cardKinOnFile ? '' : ' disabled' ?>><label class="form-check-label" for="cardShowKin">Next of kin and their phone (back)<?= $cardKinOnFile ? '' : ', none on record' ?></label></div>
                </fieldset>
                <p class="form-text">The permit number, plate, stage, route, expiry date and photo come from the rider record<?= $cardEditUrl ? ' (<a href="' . h(BASE_URL . $cardEditUrl) . '">Edit rider</a>)' : '' ?>, so the card always matches what a QR check shows. Every change is kept in the audit log.</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" data-busy-label="Saving">Save card</button>
                    <a class="btn btn-outline-secondary" href="<?= h(BASE_URL . $cardSelf) ?>">Undo</a>
                </div>
                <?php if ($cardRow['edited_at']): ?><p class="small text-muted mt-2 mb-0">Last changed <?= h(timeAgo($cardRow['edited_at'])) ?>.</p><?php endif; ?>
            </form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
        <?php if ($cardRow): $cardSignUrl = !empty($cardRider['signature_path']) ? media_url($cardRider['signature_path']) : ''; ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title">Holder's signature</h2>
            <p class="small text-muted">The card carries one signature: <?= h($cardRiderName) ?>'s own. Ask the rider to sign in the box with a finger or stylus, or upload a clear photo of the signature on white paper. It is kept with the rider and printed on every later card.</p>
            <?php if ($cardSignUrl): ?>
            <div class="sign-current mb-2"><img src="<?= h($cardSignUrl) ?>" alt="Signature of <?= h($cardRiderName) ?> on file"></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" data-sign-form>
                <?= csrf_field() ?><input type="hidden" name="card_action" value="signature"><input type="hidden" name="signature_drawn" value="" data-sign-output>
                <div class="sign-pad mb-2">
                    <canvas width="720" height="220" data-sign-pad aria-label="Signature box: the rider signs here with a finger, stylus or mouse" role="img"></canvas>
                    <span class="sign-pad-hint" data-sign-hint>Sign here</span>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-sign-clear>Clear</button>
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1" data-busy-label="Saving">Use this signature</button>
                </div>
                <label class="form-label" for="cardSignFile">Or upload a photo of the signature</label>
                <input type="file" class="form-control mb-2" id="cardSignFile" name="signature_file" accept="image/png,image/jpeg,image/webp" data-max-mb="2" data-sign-file>
            </form>
            <?php if ($cardSignUrl): ?>
            <form method="post" data-confirm="Remove the signature of <?= h($cardRiderName) ?>? Cards printed after this have an empty line for the rider to sign by hand." data-confirm-ok="Remove the signature">
                <?= csrf_field() ?><input type="hidden" name="card_action" value="signature_remove">
                <button class="btn btn-link btn-sm text-danger p-0">Remove the signature</button>
            </form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title">This card</h2>
            <?php if ($cardRow): ?>
            <dl class="row small mb-3">
                <dt class="col-5 text-muted fw-normal">Card</dt><dd class="col-7"><code><?= h($cardRow['serial']) ?></code></dd>
                <dt class="col-5 text-muted fw-normal">Status</dt><dd class="col-7"><?= id_card_status_badge($cardRow['status']) ?></dd>
                <dt class="col-5 text-muted fw-normal">Issued</dt><dd class="col-7"><?= formatDate($cardRow['issued_at'], 'd M Y') ?><?= $cardRow['issued_by_name'] ? ' by ' . h($cardRow['issued_by_name']) : '' ?></dd>
                <dt class="col-5 text-muted fw-normal">Printed</dt><dd class="col-7"><?= $cardRow['printed_at'] ? formatDate($cardRow['printed_at'], 'd M Y') . ($cardRow['print_count'] > 1 ? ' (' . (int) $cardRow['print_count'] . ' times)' : '') : 'Not yet' ?></dd>
                <dt class="col-5 text-muted fw-normal">Handed over</dt><dd class="col-7"><?= $cardRow['collected_at'] ? formatDate($cardRow['collected_at'], 'd M Y') . ($cardRow['collected_by_name'] ? ' by ' . h($cardRow['collected_by_name']) : '') : 'Not yet' ?></dd>
                <dt class="col-5 text-muted fw-normal">Permit valid until</dt><dd class="col-7"><?= formatDate($cardRider['expiry_date'], 'd M Y') ?></dd>
            </dl>
            <?php foreach ($cardProblems as [$level, $problem]): ?>
                <div class="alert alert-<?= $level ?> small py-2 mb-2"><?= h($problem) ?></div>
            <?php endforeach; ?>
            <?php if ($cardRow['valid_until'] && $cardRider['expiry_date'] && $cardRow['valid_until'] !== $cardRider['expiry_date']): ?>
                <div class="alert alert-info small py-2 mb-2">The permit now runs to <?= formatDate($cardRider['expiry_date'], 'd M Y') ?>, but this card was made when it ran to <?= formatDate($cardRow['valid_until'], 'd M Y') ?>. Issue a card for the renewal so the printed date is right.</div>
            <?php endif; ?>
            <div class="d-grid gap-2 mt-3">
                <?php if ($cardRow['status'] !== 'collected'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="card_action" value="printed">
                    <button class="btn btn-outline-primary w-100"><?= $cardRow['printed_at'] ? 'Printed again' : 'Mark as printed' ?></button>
                </form>
                <form method="post" data-confirm="Confirm that <?= h($cardRiderName) ?> has card <?= h($cardRow['serial']) ?> in hand?" data-confirm-ok="Yes, handed over"><?= csrf_field() ?><input type="hidden" name="card_action" value="collected">
                    <button class="btn btn-success w-100">Handed to the rider</button>
                </form>
                <?php else: ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="card_action" value="printed">
                    <button class="btn btn-outline-primary w-100">Record a reprint of this card</button>
                </form>
                <?php endif; ?>
                <form method="post" data-confirm="Mark card <?= h($cardRow['serial']) ?> of <?= h($cardRiderName) ?> as lost? Its QR code stops working at once. You can issue a new card straight after." data-confirm-ok="Mark as lost"><?= csrf_field() ?><input type="hidden" name="card_action" value="lost">
                    <input type="hidden" name="note" value="Reported at the stage">
                    <button class="btn btn-outline-danger w-100">Card lost or stolen</button>
                </form>
            </div>
            <?php else: ?>
            <p class="small text-muted mb-0">No card is valid now, so a QR check on any older card says it was replaced.</p>
            <?php endif; ?>
        </div></div>

        <div class="card mb-4"><div class="card-body">
            <h2 class="section-title"><?= $cardRow ? 'Make a new card' : 'Issue a card' ?></h2>
            <form method="post" data-confirm="Make a new card for <?= h($cardRiderName) ?>? The card you choose to replace stops being valid." data-confirm-ok="Make the card">
                <?= csrf_field() ?><input type="hidden" name="card_action" value="issue">
                <label class="form-label" for="cardReason">Why is a new card needed?</label>
                <select class="form-select mb-2" id="cardReason" name="reason" required>
                    <option value="">Choose a reason</option>
                    <?php foreach (ID_CARD_REASONS as $code => $label): if ($code === 'first' || ($code === 'reprint' && !$cardRow)) continue; ?>
                    <option value="<?= $code ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label" for="cardNote">Note <span class="text-muted">(optional)</span></label>
                <input type="text" class="form-control mb-2" id="cardNote" name="note" maxlength="300" placeholder="For example: new plate UFA 123B">
                <p class="small text-muted">Lost, damaged or changed details give the rider a new QR code, so the old card fails a check. A renewal or reprint keeps the same QR code.</p>
                <button class="btn btn-primary w-100" data-busy-label="Making the card">Make the card</button>
            </form>
        </div></div>

        <?php if ($cardCanCancel && $cardRow): ?>
        <div class="card"><div class="card-body">
            <h2 class="section-title">Cancel this card</h2>
            <p class="small text-muted">For a card that must stop working without a replacement, for example when a permit is withdrawn. The rider is told in their portal.</p>
            <form method="post" data-confirm="Cancel card <?= h($cardRow['serial']) ?> of <?= h($cardRiderName) ?>? Its QR code stops working and no new card is made." data-confirm-ok="Cancel the card">
                <?= csrf_field() ?><input type="hidden" name="card_action" value="cancel">
                <label class="form-label" for="cancelNote">Reason</label>
                <input type="text" class="form-control mb-2" id="cancelNote" name="note" maxlength="300" required placeholder="For example: permit withdrawn after enforcement">
                <button class="btn btn-outline-danger w-100">Cancel the card</button>
            </form>
        </div></div>
        <?php endif; ?>
        <div class="card"><div class="card-body">
            <h2 class="section-title">Card history</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Card</th><th>Why it was made</th><th>Status</th><th>Issued</th><th>Printed</th><th>Handed over</th><th>Ended</th></tr></thead>
                    <tbody>
                    <?php foreach ($cardHistory as $past): ?>
                        <tr>
                            <td><code><?= h($past['serial']) ?></code></td>
                            <td><?= h(ID_CARD_REASONS[$past['reason']] ?? $past['reason']) ?><?php if ($past['note']): ?><br><small class="text-muted"><?= h($past['note']) ?></small><?php endif; ?></td>
                            <td><?= id_card_status_badge($past['status']) ?></td>
                            <td><?= formatDate($past['issued_at'], 'd M Y') ?><?php if ($past['issued_by_name']): ?><br><small class="text-muted"><?= h($past['issued_by_name']) ?></small><?php endif; ?></td>
                            <td><?= $past['printed_at'] ? formatDate($past['printed_at'], 'd M Y') . ($past['print_count'] > 1 ? ' <small class="text-muted">(' . (int) $past['print_count'] . ' times)</small>' : '') : '–' ?></td>
                            <td><?= $past['collected_at'] ? formatDate($past['collected_at'], 'd M Y') : '–' ?></td>
                            <td><?= $past['ended_at'] ? formatDate($past['ended_at'], 'd M Y') . ($past['end_reason'] ? '<br><small class="text-muted">' . h($past['end_reason']) . '</small>' : '') : '–' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cardHistory): ?><tr><td colspan="7" class="text-muted">No card has been made yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>

    <aside class="doc-preview" aria-label="Card preview">
        <div class="card"><div class="card-body">
            <?php if ($cardRow): ?>
                <?php require __DIR__ . '/id-card.php'; ?>
                <p class="small text-muted text-center mt-3 mb-0 no-print">Real card size, 85.6 × 54 mm. Print on the card printer, or on A4 at 100% scale with headers and footers off.</p>
            <?php else: ?>
                <?= empty_state('fa-id-card', $cardRiderName . ' has no valid card',
                    $cardLastEnded ? 'Card ' . $cardLastEnded['serial'] . ' was ' . strtolower(ID_CARD_STATUS[$cardLastEnded['status']][0] ?? $cardLastEnded['status']) . ' on ' . formatDate($cardLastEnded['ended_at'], 'd M Y') . '. Make a new card with the form on this page; it gets a new QR code.' : 'Issue the first card with the form on this page.') ?>
            <?php endif; ?>
        </div></div>

    </aside>
</div>
