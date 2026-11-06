<?php
// Correcting a rider record: one form and one save path for chairpersons (own stage), city admins (own city) and
// the Super Admin (any city, from a correction request). Every changed field is written to the audit log with its
// old and new value (NIN shown only by its last four characters), and the rider is told what changed.
// Chairpersons cannot change stage, status or expiry: status follows payments, and moving a rider is the city's call.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';

const RIDER_FIELD_LABELS = [
    'full_name' => 'name', 'nin' => 'NIN', 'date_of_birth' => 'date of birth', 'gender' => 'gender', 'marital_status' => 'marital status',
    'phone' => 'phone', 'email' => 'email', 'physical_address' => 'address', 'next_of_kin_name' => 'next of kin', 'next_of_kin_contact' => 'next of kin contact',
    'bike_plate' => 'plate', 'bike_model' => 'motorcycle model', 'route' => 'route', 'photo_path' => 'photo',
    'stage_id' => 'stage', 'status' => 'permit status', 'expiry_date' => 'expiry date',
];
/** Fields that are printed on the ID card: changing one means the rider needs a new card. */
const RIDER_CARD_FIELDS = ['full_name', 'nin', 'date_of_birth', 'gender', 'bike_plate', 'photo_path', 'stage_id', 'expiry_date'];

function rider_ug_phone(string $raw): ?string {
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') return null;
    return '+256' . preg_replace('/^(256|0)/', '', $digits);
}

/**
 * Validates the posted form against $rider and saves it. $canPlace: may change stage, status and expiry (city staff).
 * @return array{errors: string[], changed: string[]}
 */
function rider_record_save(array $rider, array $post, array $files, bool $canPlace): array {
    $errors = [];
    $new = [
        'full_name'           => mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($post['full_name'] ?? '')))),
        'nin'                 => strtoupper(trim((string) ($post['nin'] ?? ''))) ?: null,
        'date_of_birth'       => trim((string) ($post['date_of_birth'] ?? '')) ?: null,
        'gender'              => in_array($post['gender'] ?? '', ['Male', 'Female', 'Other'], true) ? $post['gender'] : null,
        'marital_status'      => in_array($post['marital_status'] ?? '', ['Single', 'Married', 'Divorced', 'Widowed'], true) ? $post['marital_status'] : null,
        'phone'               => rider_ug_phone((string) ($post['phone'] ?? '')),
        'email'               => mb_strtolower(trim((string) ($post['email'] ?? ''))) ?: null,
        'physical_address'    => trim((string) ($post['physical_address'] ?? '')) ?: null,
        'next_of_kin_name'    => trim((string) ($post['next_of_kin_name'] ?? '')) ?: null,
        'next_of_kin_contact' => rider_ug_phone((string) ($post['next_of_kin_contact'] ?? '')),
        'bike_plate'          => strtoupper(preg_replace('/\s+/', ' ', trim((string) ($post['bike_plate'] ?? '')))),
        'bike_model'          => trim((string) ($post['bike_model'] ?? '')) ?: null,
        'route'               => trim((string) ($post['route'] ?? '')) ?: null,
    ];
    if ($new['full_name'] === '' || $new['bike_plate'] === '' || $new['phone'] === null) $errors[] = 'The name, mobile number and motorcycle plate are required.';
    if (mb_strlen($new['full_name']) > 100) $errors[] = 'The name is too long.';
    if ($new['nin'] !== null && !preg_match('/^[A-Z0-9]{14}$/', $new['nin'])) $errors[] = 'A National ID Number (NIN) has 14 letters and digits.';
    if ($new['phone'] !== null && !preg_match('/^\+2567\d{8}$/', $new['phone'])) $errors[] = 'Enter a Ugandan mobile number, for example 772 123456.';
    if ($new['next_of_kin_contact'] !== null && !preg_match('/^\+256\d{9}$/', $new['next_of_kin_contact'])) $errors[] = 'The next of kin number is not a Ugandan number.';
    if ($new['email'] !== null && !filter_var($new['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'The email address is not valid.';
    if ($new['date_of_birth'] !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new['date_of_birth']) || $new['date_of_birth'] > date('Y-m-d', strtotime('-16 years')) || $new['date_of_birth'] < '1930-01-01')) {
        $errors[] = 'Riders must be at least 16 years old; check the date of birth.';
    }
    if ($canPlace) {
        $new['stage_id'] = (int) ($post['stage_id'] ?? 0);
        if (!fetchValue("SELECT COUNT(*) FROM stages WHERE id = ? AND city_id = ?", [$new['stage_id'], $rider['city_id']])) $errors[] = 'Choose a stage in the rider\'s city.';
        $new['status'] = in_array($post['status'] ?? '', ['active', 'expired', 'pending'], true) ? $post['status'] : $rider['status'];
        $exp = trim((string) ($post['expiry_date'] ?? ''));
        $new['expiry_date'] = $exp === '' ? null : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp) && strtotime($exp) ? $exp : false);
        if ($new['expiry_date'] === false) { $errors[] = 'The expiry date is not a valid date.'; $new['expiry_date'] = $rider['expiry_date']; }
        if ($new['status'] === 'active' && !$new['expiry_date']) $errors[] = 'An active permit needs an expiry date.';
    }
    if (!$errors) {
        foreach (rider_duplicates(['nin' => $new['nin'], 'bike_plate' => $new['bike_plate']], (int) $rider['id']) as $dup) {
            if ($dup['field'] !== 'phone') $errors[] = "{$dup['label']} is already registered at {$dup['where']}.";
        }
    }
    $newPhoto = null;
    if (!$errors && ($files['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $up = store_upload($files['photo'], 'riders', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 2 * 1024 * 1024);
        if ($up['ok']) { $newPhoto = $up['path']; $new['photo_path'] = $newPhoto; }
        else $errors[] = 'Photo: ' . $up['error'];
    }
    if ($errors) return ['errors' => $errors, 'changed' => []];

    $changed = []; $diff = [];
    foreach ($new as $field => $value) {
        $old = $rider[$field] ?? null;
        if ((string) $old === (string) $value) continue;
        $changed[] = $field;
        $show = fn($v) => $v === null || $v === '' ? 'empty' : ($field === 'nin' ? '…' . substr((string) $v, -4) : ($field === 'photo_path' ? 'new file' : (string) $v));
        $diff[] = RIDER_FIELD_LABELS[$field] . ': ' . $show($old) . ' -> ' . $show($value);
    }
    if (!$changed) return ['errors' => [], 'changed' => []];
    $set = implode(', ', array_map(fn($f) => "`$f` = ?", $changed));
    $values = array_map(fn($f) => $new[$f], $changed);
    runQuery("UPDATE riders SET $set WHERE id = ?", [...$values, (int) $rider['id']])->close();
    if ($newPhoto && !empty($rider['photo_path']) && str_starts_with((string) $rider['photo_path'], '/uploads/riders/')) delete_upload($rider['photo_path']);
    if (in_array('stage_id', $changed, true)) {
        runQuery("UPDATE id_cards SET stage_id = ? WHERE rider_id = ? AND status IN ('issued','printed','collected')", [$new['stage_id'], (int) $rider['id']])->close();
    }
    audit_log('UPDATE', 'rider', (string) $rider['id_number'], mb_substr('Rider record corrected. ' . implode('; ', $diff), 0, 1000));
    rider_notify((int) $rider['id'], 'Important Update', 'Your record was updated: ' . implode(', ', array_map(fn($f) => RIDER_FIELD_LABELS[$f], $changed)) . '.');
    return ['errors' => [], 'changed' => $changed];
}

/**
 * Removes a rider's personal data on a deletion request, keeping payments and permit numbers so the city's
 * accounts still add up. The record stays as "Removed rider", can no longer sign in, and its QR stops verifying.
 */
function rider_anonymise(int $riderId, int $byUser, string $reason): bool {
    $rider = fetchOne("SELECT id, id_number, user_id, photo_path FROM riders WHERE id = ?", [$riderId]);
    if (!$rider) return false;
    $db = db();
    $db->begin_transaction();
    try {
        runQuery("UPDATE riders SET full_name = ?, nin = NULL, date_of_birth = NULL, gender = NULL, marital_status = NULL, phone = '', email = NULL,
            physical_address = NULL, next_of_kin_name = NULL, next_of_kin_contact = NULL, photo_path = NULL, route = NULL, sms_opt_out = 1,
            verify_token = ?, user_id = NULL, deleted_at = COALESCE(deleted_at, NOW()) WHERE id = ?",
            ['REMOVED RIDER ' . $rider['id_number'], bin2hex(random_bytes(10)), $riderId])->close();
        runQuery("UPDATE id_cards SET status = 'cancelled', ended_at = NOW(), ended_by = ?, end_reason = 'Personal data removed on request', print_data = NULL
            WHERE rider_id = ? AND status IN ('issued','printed','collected')", [$byUser, $riderId])->close();
        runQuery("UPDATE payments SET payer_phone = NULL WHERE rider_id = ?", [$riderId])->close();
        runQuery("DELETE FROM notifications WHERE rider_id = ?", [$riderId])->close();
        if ($rider['user_id']) {
            $uid = (int) $rider['user_id'];
            runQuery("UPDATE users SET name = 'Removed rider', email = CONCAT('removed-', id, '@invalid'), phone = NULL, avatar_path = NULL, status = 'suspended', deleted_at = NOW() WHERE id = ?", [$uid])->close();
            runQuery("DELETE FROM user_tokens WHERE user_id = ?", [$uid])->close();
            runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ?", [$uid])->close();
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    if (!empty($rider['photo_path'])) delete_upload($rider['photo_path']);
    audit_log('DELETE', 'rider', (string) $rider['id_number'], mb_substr('Personal data removed on request: ' . $reason, 0, 500));
    return true;
}
