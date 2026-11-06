<?php
// ID cards: one row per printed card (table id_cards). The rider's verify_token is the token of the current card;
// when a card is lost, damaged, changed or cancelled the rider gets a new token, so the QR on the old card stops
// verifying (verify.php then says the card was replaced). A reprint keeps the same token and serial count goes up.
require_once __DIR__ . '/auth.php';

const ID_CARD_REASONS = [
    'first'   => 'First card',
    'renewal' => 'Permit renewed (new expiry date)',
    'details' => 'Details changed (photo, plate or stage)',
    'damaged' => 'Card damaged or worn',
    'lost'    => 'Card lost or stolen',
    'reprint' => 'Reprint, same card',
];
/** Reasons that give the rider a new QR token, so the old card no longer verifies. */
const ID_CARD_NEW_TOKEN = ['details', 'damaged', 'lost'];
const ID_CARD_LIVE = ['issued', 'printed', 'collected'];
const ID_CARD_STATUS = [
    'issued'    => ['Ready to print', 'warning'],
    'printed'   => ['Printed, not handed over', 'info'],
    'collected' => ['With the rider', 'success'],
    'replaced'  => ['Replaced', 'secondary'],
    'lost'      => ['Reported lost', 'danger'],
    'cancelled' => ['Cancelled', 'danger'],
];

function id_card_status_badge(?string $status): string {
    if ($status === null) return '<span class="badge bg-danger">No valid card</span>';
    [$label, $tone] = ID_CARD_STATUS[$status] ?? [$status, 'secondary'];
    return '<span class="badge bg-' . $tone . '">' . h($label) . '</span>';
}

/** Card wording and which personal details are printed (settings category id_card). */
function id_card_settings(): array {
    return [
        'title'        => get_setting('id_card_title', 'Boda boda operator licence'),
        'statement'    => get_setting('id_card_back_statement', 'This card is the property of {city} and must be surrendered on request.'),
        'terms'        => get_setting('id_card_back_terms', 'Non-transferable. Renew every year.'),
        'show_nin'     => setting_bool('id_card_show_nin', true),
        'show_dob'     => setting_bool('id_card_show_dob', true),
        'show_address' => setting_bool('id_card_show_address', false),
        'show_kin'     => setting_bool('id_card_show_kin', true),
        'country'      => get_setting('id_card_country_line', 'The Republic of Uganda'),
        'colour'       => preg_match('/^#[0-9a-f]{6}$/i', get_setting('id_card_colour', '#b01c24')) ? get_setting('id_card_colour', '#b01c24') : '#b01c24',
    ];
}

function id_card_current(int $riderId): ?array {
    return fetchOne("SELECT c.*, ui.name AS issued_by_name, up.name AS printed_by_name, uc.name AS collected_by_name
        FROM id_cards c LEFT JOIN users ui ON ui.id = c.issued_by LEFT JOIN users up ON up.id = c.printed_by LEFT JOIN users uc ON uc.id = c.collected_by
        WHERE c.rider_id = ? AND c.status IN ('issued','printed','collected') ORDER BY c.version DESC LIMIT 1", [$riderId]);
}

function id_card_history(int $riderId): array {
    return fetchAll("SELECT c.*, ui.name AS issued_by_name, ue.name AS ended_by_name
        FROM id_cards c LEFT JOIN users ui ON ui.id = c.issued_by LEFT JOIN users ue ON ue.id = c.ended_by
        WHERE c.rider_id = ? ORDER BY c.version DESC", [$riderId]);
}

/** First card for a rider who has never had one (new registrations, imports, riders made before the register). */
function id_card_ensure(int $riderId): ?array {
    $card = id_card_current($riderId);
    if ($card || (int) fetchValue("SELECT COUNT(*) FROM id_cards WHERE rider_id = ?", [$riderId]) > 0) return $card;
    return id_card_issue($riderId, 'first', '', (int) ($_SESSION['user_id'] ?? 0) ?: null);
}

/**
 * Issues a new card version and ends the current one. Returns the new card, or null when the rider does not exist.
 * Reasons in ID_CARD_NEW_TOKEN give the rider a new QR token.
 */
function id_card_issue(int $riderId, string $reason, string $note = '', ?int $byUser = null): ?array {
    if (!isset(ID_CARD_REASONS[$reason])) $reason = 'reprint';
    $rider = fetchOne("SELECT id, city_id, stage_id, id_number, verify_token, expiry_date FROM riders WHERE id = ? AND deleted_at IS NULL", [$riderId]);
    if (!$rider) return null;
    $db = db();
    $db->begin_transaction();
    try {
        $current = fetchOne("SELECT id FROM id_cards WHERE rider_id = ? AND status IN ('issued','printed','collected') ORDER BY version DESC LIMIT 1 FOR UPDATE", [$riderId]);
        $hadCards = (int) fetchValue("SELECT COUNT(*) FROM id_cards WHERE rider_id = ?", [$riderId]) > 0;
        $token = (string) $rider['verify_token'];
        if ($token === '' || ($hadCards && in_array($reason, ID_CARD_NEW_TOKEN, true)) || (!$current && $hadCards)) {
            // No live card means the last one was lost or cancelled: its token must not come back.
            $token = bin2hex(random_bytes(10));
            runQuery("UPDATE riders SET verify_token = ? WHERE id = ?", [$token, $riderId])->close();
        }
        if ($current) {
            runQuery("UPDATE id_cards SET status = 'replaced', ended_at = NOW(), ended_by = ?, end_reason = ? WHERE id = ?",
                [$byUser, ID_CARD_REASONS[$reason] . ($note !== '' ? ': ' . $note : ''), $current['id']])->close();
        }
        $version = (int) fetchValue("SELECT COALESCE(MAX(version), 0) + 1 FROM id_cards WHERE rider_id = ?", [$riderId]);
        $serial = $rider['id_number'] . '-' . str_pad((string) $version, 2, '0', STR_PAD_LEFT);
        runQuery("INSERT INTO id_cards (rider_id, city_id, stage_id, version, serial, verify_token, reason, valid_until, note, issued_by) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$riderId, $rider['city_id'], $rider['stage_id'], $version, $serial, $token, $reason, $rider['expiry_date'], mb_substr($note, 0, 300) ?: null, $byUser])->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    if ($reason !== 'first') {
        audit_log('CREATE', 'id_card', $serial, 'ID card issued: ' . ID_CARD_REASONS[$reason]);
        rider_notify($riderId, 'ID Card Issued', 'A new ID card (' . $serial . ') is ready. Collect it from your stage chairperson.'
            . (in_array($reason, ID_CARD_NEW_TOKEN, true) ? ' Your old card no longer passes a QR check.' : ''));
    }
    return id_card_current($riderId);
}

/** Marks the current card printed (counts reprints of the same card) or handed to the rider. */
function id_card_mark(int $cardId, string $what, int $byUser): bool {
    if ($what === 'printed') {
        $stmt = runQuery("UPDATE id_cards SET status = IF(status = 'issued', 'printed', status), printed_by = ?, printed_at = NOW(), print_count = print_count + 1
            WHERE id = ? AND status IN ('issued','printed','collected')", [$byUser, $cardId]);
    } elseif ($what === 'collected') {
        $stmt = runQuery("UPDATE id_cards SET status = 'collected', collected_by = ?, collected_at = NOW(),
            printed_at = COALESCE(printed_at, NOW()), printed_by = COALESCE(printed_by, ?), print_count = GREATEST(print_count, 1)
            WHERE id = ? AND status IN ('issued','printed')", [$byUser, $byUser, $cardId]);
    } else {
        return false;
    }
    $ok = $stmt->affected_rows > 0; $stmt->close();
    if ($ok) {
        $card = fetchOne("SELECT rider_id, serial FROM id_cards WHERE id = ?", [$cardId]);
        audit_log('UPDATE', 'id_card', $card['serial'], $what === 'printed' ? 'ID card printed' : 'ID card handed to the rider');
        if ($what === 'collected') rider_notify((int) $card['rider_id'], 'ID Card Issued', 'You collected ID card ' . $card['serial'] . '. Carry it whenever you ride.');
    }
    return $ok;
}

/**
 * Ends the current card without issuing a new one: 'lost' (reported by the rider or the chairperson) or 'cancelled'
 * (city staff). The rider gets a new token at once, so whoever holds the old card cannot pass a QR check with it.
 */
function id_card_end(int $riderId, string $status, string $reason, ?int $byUser): bool {
    if (!in_array($status, ['lost', 'cancelled'], true)) return false;
    $card = fetchOne("SELECT id, serial FROM id_cards WHERE rider_id = ? AND status IN ('issued','printed','collected') ORDER BY version DESC LIMIT 1", [$riderId]);
    if (!$card) return false;
    $db = db();
    $db->begin_transaction();
    try {
        runQuery("UPDATE id_cards SET status = ?, ended_at = NOW(), ended_by = ?, end_reason = ? WHERE id = ?", [$status, $byUser, mb_substr($reason, 0, 300) ?: null, $card['id']])->close();
        runQuery("UPDATE riders SET verify_token = ? WHERE id = ?", [bin2hex(random_bytes(10)), $riderId])->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    audit_log('UPDATE', 'id_card', $card['serial'], ($status === 'lost' ? 'ID card reported lost' : 'ID card cancelled') . ($reason !== '' ? ': ' . $reason : ''));
    rider_notify($riderId, 'Important Update', 'ID card ' . $card['serial'] . ($status === 'lost' ? ' is marked lost' : ' was cancelled')
        . ' and no longer passes a QR check. Your chairperson can issue a new card.');
    return true;
}

/** An older card's token, for verify.php: tells an officer the card in front of them was replaced. */
function id_card_by_old_token(string $token): ?array {
    return fetchOne("SELECT c.serial, c.status, c.ended_at, c.end_reason, r.full_name, r.id_number, ci.name AS city_name,
        (SELECT n.serial FROM id_cards n WHERE n.rider_id = c.rider_id AND n.status IN ('issued','printed','collected') ORDER BY n.version DESC LIMIT 1) AS current_serial
        FROM id_cards c JOIN riders r ON r.id = c.rider_id LEFT JOIN cities ci ON ci.id = c.city_id
        WHERE c.verify_token = ? AND c.status IN ('replaced','lost','cancelled') AND r.verify_token <> c.verify_token AND r.deleted_at IS NULL
        ORDER BY c.version DESC LIMIT 1", [$token]);
}

/**
 * What stops a card from being ready: shown before printing so a chairperson fixes the record first.
 * @return list<array{0:string,1:string}> [level (danger|warning), text]
 */
function id_card_problems(array $rider): array {
    $out = [];
    if (empty($rider['photo_path'])) $out[] = ['danger', 'No photo. An officer cannot match the card to the rider. Add one on Edit rider.'];
    if (($rider['status'] ?? '') === 'pending') $out[] = ['warning', 'The permit fee is not paid yet, so the card shows PENDING and a QR check says not yet paid.'];
    if (($rider['status'] ?? '') === 'expired' || (!empty($rider['expiry_date']) && $rider['expiry_date'] < date('Y-m-d'))) $out[] = ['danger', 'The permit has expired. Renew it before printing, or the card shows EXPIRED.'];
    if (trim((string) ($rider['bike_plate'] ?? '')) === '') $out[] = ['warning', 'No motorcycle plate on record.'];
    if (trim((string) ($rider['stage_name'] ?? '')) === '') $out[] = ['warning', 'The rider is not assigned to a stage.'];
    return $out;
}

/** Whether the signed-in user may manage this rider's card. Chairpersons: own stage; city admins: own city. */
function id_card_can_manage(array $rider): bool {
    return match ($_SESSION['role'] ?? '') {
        'super_admin' => true,
        'city_admin'  => (string) $rider['city_id'] === (string) ($_SESSION['city_id'] ?? ''),
        'chairperson' => (int) $rider['stage_id'] === (int) ($_SESSION['stage_id'] ?? 0),
        default       => false,
    };
}

/** Rider row with the joins the card template needs. */
function id_card_rider(int $riderId): ?array {
    return fetchOne("SELECT r.*, s.name AS stage_name, s.location AS stage_location, s.route AS stage_route, u.name AS registrar_name
        FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN users u ON u.id = r.created_by
        WHERE r.id = ? AND r.deleted_at IS NULL", [$riderId]);
}

/**
 * Handles the card actions posted from a card page (chairperson, city admin). Redirects back to $returnUrl.
 * Actions: issue (reason, note), printed, collected, lost, cancel (city staff only).
 */
function id_card_handle_post(array $rider, string $returnUrl): void {
    csrf_verify();
    $uid = (int) $_SESSION['user_id'];
    $action = (string) ($_POST['card_action'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));
    $riderId = (int) $rider['id'];
    if (!id_card_can_manage($rider)) app_error_page(403);
    $current = id_card_current($riderId);
    switch ($action) {
        case 'issue':
            $reason = (string) ($_POST['reason'] ?? '');
            if (!isset(ID_CARD_REASONS[$reason]) || $reason === 'first') { flash('error', 'Choose why the new card is needed.'); break; }
            if ($reason === 'reprint' && !$current) { flash('error', 'There is no valid card to reprint. Choose another reason.'); break; }
            $card = id_card_issue($riderId, $reason, $note, $uid);
            flash('success', 'Card ' . $card['serial'] . ' is ready to print.' . (in_array($reason, ID_CARD_NEW_TOKEN, true) || !$current ? ' The old card no longer passes a QR check.' : ''));
            break;
        case 'printed':
        case 'collected':
            if ($current && id_card_mark((int) $current['id'], $action, $uid)) {
                flash('success', $action === 'printed' ? 'Card ' . $current['serial'] . ' is marked printed.' : 'Card ' . $current['serial'] . ' is marked as handed to ' . $rider['full_name'] . '.');
            } else {
                flash('error', 'The card could not be updated. Reload the page and try again.');
            }
            break;
        case 'lost':
        case 'cancel':
            if ($action === 'cancel' && !in_array($_SESSION['role'] ?? '', ['city_admin', 'super_admin'], true)) app_error_page(403);
            if ($action === 'cancel' && $note === '') { flash('error', 'Give a reason for cancelling the card.'); break; }
            if (id_card_end($riderId, $action === 'lost' ? 'lost' : 'cancelled', $note, $uid)) {
                flash('success', 'Card ' . ($current['serial'] ?? '') . ($action === 'lost' ? ' is marked lost.' : ' is cancelled.') . ' Its QR code no longer verifies.');
            } else {
                flash('error', 'There is no valid card to end.');
            }
            break;
        case 'signature':
            $signed = id_card_store_signature($rider, $_FILES['signature_file'] ?? null, (string) ($_POST['signature_drawn'] ?? ''));
            if ($signed['ok']) {
                audit_log('UPDATE', 'rider', (string) $riderId, 'Holder\'s signature for the ID card ' . ($signed['drawn'] ? 'drawn on screen' : 'uploaded'));
                flash('success', 'The signature of ' . $rider['full_name'] . ' is on the card.' . ($current && $current['printed_at'] ? ' Print the card again.' : ''));
            } else {
                flash('error', $signed['error']);
            }
            break;
        case 'signature_remove':
            if (!empty($rider['signature_path'])) {
                runQuery("UPDATE riders SET signature_path = NULL WHERE id = ?", [$riderId])->close();
                delete_upload($rider['signature_path']);
                audit_log('UPDATE', 'rider', (string) $riderId, 'Holder\'s signature for the ID card removed');
                flash('success', 'The signature was removed. The card now has an empty line for the rider to sign by hand.');
            }
            break;
        case 'design':
            if (!$current) { flash('error', 'There is no valid card to change. Make a new card first.'); break; }
            if ($current['status'] === 'collected') { flash('error', 'This card is already with the rider. Make a new card (details changed) instead.'); break; }
            $changes = id_card_save_print_data($current, $rider, $_POST, $uid);
            flash('success', $changes ? 'Card ' . $current['serial'] . ' updated: ' . implode(', ', $changes) . '.' . ($current['printed_at'] ? ' Print it again.' : '') : 'Nothing was changed.');
            break;
        default:
            flash('error', 'Unknown action.');
    }
    redirect($returnUrl);
}

/** Bulk "mark printed" from a card list. $scopeSql limits riders to the user's stage or city. */
function id_card_bulk_printed(array $riderIds, string $scopeSql, array $scopeParams): int {
    if (!$riderIds) return 0;
    $marks = 0;
    $place = implode(',', array_fill(0, count($riderIds), '?'));
    $cards = fetchAll("SELECT c.id FROM id_cards c JOIN riders r ON r.id = c.rider_id
        WHERE c.rider_id IN ($place) AND c.status IN ('issued','printed','collected') AND ($scopeSql)", array_merge($riderIds, $scopeParams));
    foreach ($cards as $card) if (id_card_mark((int) $card['id'], 'printed', (int) $_SESSION['user_id'])) $marks++;
    return $marks;
}

/** A card nobody has printed yet follows the permit: after a payment its printed expiry date is brought up to date. */
function id_card_sync_unprinted(int $riderId): void {
    runQuery("UPDATE id_cards c JOIN riders r ON r.id = c.rider_id SET c.valid_until = r.expiry_date
        WHERE c.rider_id = ? AND c.status = 'issued' AND c.print_count = 0", [$riderId])->close();
}

/** Fields staff may adjust before printing, with their limits. Everything else on the card comes from the rider record. */
const ID_CARD_EDITABLE = [
    'name'      => 'name as printed',
    'issued_on' => 'date of issue',
    'photo_y'   => 'photo position',
    'photo_zoom'=> 'photo size',
    'show_nin'  => 'NIN shown',
    'show_dob'  => 'date of birth shown',
    'show_address' => 'address shown',
    'show_kin'  => 'next of kin shown',
];

/** What is printed on this card: settings and the rider record, then the card's own adjustments on top. */
function id_card_print_view(array $rider, ?array $card, array $cfg): array {
    $saved = $card && $card['print_data'] ? (json_decode($card['print_data'], true) ?: []) : [];
    $view = [
        'name'       => mb_strtoupper(trim((string) $rider['full_name'])),
        'issued_on'  => $card ? substr((string) $card['issued_at'], 0, 10) : date('Y-m-d'),
        'photo_y'    => 30,
        'photo_zoom' => 100,
        'show_nin'   => $cfg['show_nin'],
        'show_dob'   => $cfg['show_dob'],
        'show_address' => $cfg['show_address'],
        'show_kin'   => $cfg['show_kin'],
    ];
    foreach ($saved as $key => $value) if (array_key_exists($key, $view)) $view[$key] = $value;
    return $view;
}

/** Validates and stores the adjustments posted from the card page. Returns the labels of what changed (audited). */
function id_card_save_print_data(array $card, array $rider, array $post, int $byUser): array {
    $cfg = id_card_settings();
    $before = id_card_print_view($rider, $card, $cfg);
    $name = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($post['name'] ?? ''))));
    $issued = (string) ($post['issued_on'] ?? '');
    $after = [
        'name'       => $name !== '' ? mb_substr($name, 0, 40) : $before['name'],
        'issued_on'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $issued) && strtotime($issued) && $issued <= date('Y-m-d') && $issued >= substr((string) $card['issued_at'], 0, 10) ? $issued : $before['issued_on'],
        'photo_y'    => max(0, min(100, (int) ($post['photo_y'] ?? 30))),
        'photo_zoom' => max(100, min(160, (int) ($post['photo_zoom'] ?? 100))),
        'show_nin'   => !empty($post['show_nin']),
        'show_dob'   => !empty($post['show_dob']),
        'show_address' => !empty($post['show_address']),
        'show_kin'   => !empty($post['show_kin']),
    ];
    // Words from the rider's registered name only: the card may be shortened (long names), never renamed.
    $registered = preg_split('/\s+/', mb_strtoupper(trim((string) $rider['full_name']))) ?: [];
    foreach (preg_split('/\s+/', $after['name']) ?: [] as $word) {
        $word = rtrim($word, '.');
        $ok = false;
        foreach ($registered as $part) if ($part === $word || (mb_strlen($word) === 1 && str_starts_with($part, $word))) $ok = true;
        if (!$ok) { $after['name'] = $before['name']; break; }
    }
    $changed = [];
    foreach (ID_CARD_EDITABLE as $key => $label) if ($before[$key] !== $after[$key]) $changed[$key] = $label;
    if (!$changed) return [];
    runQuery("UPDATE id_cards SET print_data = ?, edited_by = ?, edited_at = NOW() WHERE id = ?", [json_encode($after, JSON_UNESCAPED_UNICODE), $byUser, $card['id']])->close();
    $diff = [];
    foreach ($changed as $key => $label) $diff[] = $label . ': ' . json_encode($before[$key], JSON_UNESCAPED_UNICODE) . ' -> ' . json_encode($after[$key], JSON_UNESCAPED_UNICODE);
    audit_log('UPDATE', 'id_card', $card['serial'], mb_substr('Card print fields changed. ' . implode('; ', $diff), 0, 1000));
    return array_values($changed);
}

/**
 * Machine-readable zone on the back, in the three-line, 30-character layout of ID cards (ICAO 9303 TD1),
 * with its check digits, so a document reader can take the permit number, birth date and expiry from it.
 * @return string[] three lines
 */
function id_card_mrz(array $rider, array $city, array $view): array {
    $clean = function (string $text, int $len): string {
        $text = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
        return str_pad(substr(preg_replace('/[^A-Z0-9<]/', '<', str_replace([' ', '-', "'"], '<', $text)), 0, $len), $len, '<');
    };
    $check = function (string $data): string {
        $weights = [7, 3, 1]; $sum = 0;
        foreach (str_split($data) as $i => $ch) {
            $v = ctype_digit($ch) ? (int) $ch : ($ch === '<' ? 0 : ord($ch) - 55);
            $sum += $v * $weights[$i % 3];
        }
        return (string) ($sum % 10);
    };
    $country = $clean(match (strtolower((string) ($city['country'] ?? 'Uganda'))) { 'kenya' => 'KEN', 'tanzania' => 'TZA', 'rwanda' => 'RWA', default => 'UGA' }, 3);
    $doc = $clean(preg_replace('/[^A-Z0-9]/', '', preg_replace('/^BODA-/', '', strtoupper((string) $rider['id_number']))), 9);
    $optional1 = $clean((string) ($city['id'] ?? ''), 15);
    $dob = $rider['date_of_birth'] ? date('ymd', strtotime($rider['date_of_birth'])) : '<<<<<<';
    $exp = $rider['expiry_date'] ? date('ymd', strtotime($rider['expiry_date'])) : '<<<<<<';
    $sex = match (strtolower((string) $rider['gender'])) { 'male' => 'M', 'female' => 'F', default => '<' };
    $line1 = 'IP' . $country . $doc . $check($doc) . $optional1;
    $optional2 = $clean('', 11);
    $line2 = $dob . $check($dob) . $sex . $exp . $check($exp) . $country . $optional2;
    $composite = substr($line1, 5, 25) . substr($line2, 0, 7) . substr($line2, 8, 7) . substr($line2, 18, 11);
    $line2 .= $check($composite);
    $parts = preg_split('/\s+/', mb_strtoupper(trim((string) $rider['full_name']))) ?: [];
    $surname = array_shift($parts) ?? '';
    $line3 = $clean($surname . '<<' . implode('<', $parts), 30);
    return [$line1, $line2, $line3];
}

/**
 * The rider's name as printed, split the way an ID card shows it: the first registered name is the surname,
 * the second the given name, anything after that the other names.
 * @return array{surname:string,given:string,other:string}
 */
function id_card_name_parts(string $printedName): array {
    $words = preg_split('/\s+/', trim($printedName)) ?: [];
    return ['surname' => (string) array_shift($words), 'given' => (string) array_shift($words), 'other' => implode(' ', $words)];
}

/** The city's name as printed on its cards: "Lira City" gives LIRA, so every city's cards carry their own name. */
function id_card_city_word(array $city): string {
    $name = trim(preg_replace('/\s+(city\s+council|municipal\s+council|council|city|municipality)$/i', '', (string) ($city['name'] ?? '')));
    return mb_strtoupper($name !== '' ? $name : (string) ($city['id'] ?? ''));
}

/**
 * Stores the holder's signature: an uploaded photo of the signature, or the PNG drawn on the card page
 * (a data: URL). It belongs to the rider, so every later card prints it. Files go under uploads/riders/ and
 * are served only to the people who may see the rider's photo (rider_photo_allowed()).
 * @return array{ok:bool,error?:string,drawn?:bool}
 */
function id_card_store_signature(array $rider, ?array $file, string $drawn): array {
    if ($drawn !== '') {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $drawn, $m)) return ['ok' => false, 'error' => 'The drawn signature could not be read. Draw it again.'];
        $saved = store_generated_image((string) base64_decode($m[1], true), 'riders/signatures', ['image/png' => 'png'], 400000);
    } elseif ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $saved = store_upload($file, 'riders/signatures', ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 2097152);
    } else {
        return ['ok' => false, 'error' => 'Draw the signature in the box or choose a photo of it.'];
    }
    if (!$saved['ok']) return $saved;
    if (($saved['width'] ?? 0) < 120 || ($saved['height'] ?? 0) < 30) {
        delete_upload($saved['path']);
        return ['ok' => false, 'error' => 'The signature image is too small to print clearly. Use one at least 120 by 30 pixels.'];
    }
    runQuery("UPDATE riders SET signature_path = ? WHERE id = ?", [$saved['path'], (int) $rider['id']])->close();
    if (!empty($rider['signature_path'])) delete_upload($rider['signature_path']);
    return ['ok' => true, 'drawn' => $drawn !== ''];
}

/**
 * Fine wave lines and a rosette behind the card text, the kind printed on security documents. Drawn once per
 * request as an inline SVG (no image request, nothing for the CSP to allow). Coordinates in tenths of a mm.
 */
function id_card_pattern_svg(string $side): string {
    static $cache = [];
    if (isset($cache[$side])) return $cache[$side];
    $waves = '';
    for ($k = 0; $k < 34; $k++) {
        $y0 = 20 + $k * 15.5; $amp = 9 + ($k % 5) * 2.2; $len = 150 + ($k % 3) * 40; $phase = $k * 0.55;
        $d = '';
        for ($x = 0; $x <= 860; $x += 10) {
            $y = $y0 + $amp * sin(($x / $len) * 2 * M_PI + $phase) + 4 * sin(($x / 61) + $k);
            $d .= ($x === 0 ? 'M' : 'L') . $x . ' ' . round($y, 1);
        }
        $waves .= '<path d="' . $d . '"/>';
    }
    $rosette = '';
    [$cx, $cy, $r] = $side === 'front' ? [690, 170, 150] : [150, 400, 120];
    for ($a = 0; $a < 180; $a += 7.5) {
        $rosette .= '<ellipse cx="' . $cx . '" cy="' . $cy . '" rx="' . $r . '" ry="' . round($r * 0.38) . '" transform="rotate(' . $a . ' ' . $cx . ' ' . $cy . ')"/>';
    }
    return $cache[$side] = '<svg class="idc-pattern" viewBox="0 0 856 540" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<g class="idc-waves">' . $waves . '</g><g class="idc-rosette">' . $rosette . '</g></svg>';
}

