<?php
// BodaERP - creating user accounts and inviting people to set their password. There is no public sign-up:
// riders get an account when their chairperson registers them with an email address; staff accounts are
// created in Super Admin → All Users.
require_once __DIR__ . '/auth.php';

/** Creates a user with an unusable random password. Returns the new user id, or an error string. */
function account_create(array $account): int|string {
    $email = mb_strtolower(trim((string) ($account['email'] ?? '')));
    // Riders may have no email: they sign in with their phone number instead.
    $phoneOnly = $email === '' && ($account['role'] ?? '') === 'rider' && login_phone((string) ($account['phone'] ?? '')) !== null;
    if (!$phoneOnly && !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';
    if (!$phoneOnly && fetchValue("SELECT COUNT(*) FROM users WHERE email = ? AND deleted_at IS NULL", [$email])) return 'Another account already uses ' . $email . '.';
    if ($phoneOnly) $email = null;
    if (!isset(ROLE_LABELS[$account['role'] ?? ''])) return 'Choose a valid role.';
    runQuery("INSERT INTO users (name, email, phone, password_hash, role, city_id, stage_id, agency_name, status) VALUES (?,?,?,?,?,?,?,?, 'active')", [
        trim((string) $account['name']), $email, $account['phone'] ?? null, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
        $account['role'], $account['city_id'] ?? null, $account['stage_id'] ?? null, $account['agency_name'] ?? null,
    ]);
    return (int) db()->insert_id;
}

/** Emails a 48-hour "set your password" link. Returns false if the email could not be sent. */
function account_send_invite(int $userId): bool {
    $user = fetchOne("SELECT id, name, email, phone, role FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
    if (!$user) return false;
    if (!$user['email']) {
        $phone = login_phone((string) $user['phone']);
        if ($phone === null || $user['role'] !== 'rider') return false;
        $token = user_token_create($userId, 'password_reset', 48 * 3600);
        return send_template_sms('portal_invite', $phone, ['url' => app_url('reset-password.php?welcome=1&token=' . $token)]);
    }
    $token = user_token_create($userId, 'password_reset', 48 * 3600);
    return send_template_email('account_created', $user['email'], [
        'name' => $user['name'], 'email' => $user['email'], 'role' => role_label($user['role']),
        'created_by' => $_SESSION['name'] ?? get_setting('site_name', 'BodaERP'),
        'set_password_url' => app_url('reset-password.php?welcome=1&token=' . $token),
    ]);
}

/** Emails a link to confirm the address on file. */
function account_send_verification(int $userId): bool {
    $user = fetchOne("SELECT id, name, email FROM users WHERE id = ?", [$userId]);
    if (!$user || !$user['email']) return false;
    $token = user_token_create($userId, 'email_verify', 48 * 3600);
    return send_template_email('email_verification', $user['email'], ['name' => $user['name'], 'verify_url' => app_url('verify-email.php?token=' . $token)]);
}

/** Creates (or links) the rider's portal account and sends the invite. Returns a message for the chairperson. */
function rider_portal_invite(int $riderId): string {
    $rider = fetchOne("SELECT * FROM riders WHERE id = ? AND deleted_at IS NULL", [$riderId]);
    if (!$rider) return 'Rider not found.';
    $phone = login_phone((string) $rider['phone']);
    if (!$rider['email'] && $phone === null) return 'Add an email address or a Ugandan mobile number to the rider first.';
    if (!rate_limit('portal-link:' . $riderId, 3, 3600)) return 'Three links were sent to this rider in the last hour. Wait before sending another.';
    $userId = (int) ($rider['user_id'] ?? 0);
    if (!$userId) {
        $existing = $rider['email']
            ? fetchOne("SELECT id, role FROM users WHERE email = ? AND deleted_at IS NULL", [mb_strtolower($rider['email'])])
            : fetchOne("SELECT id, role FROM users WHERE phone = ? AND role = 'rider' AND deleted_at IS NULL", [$phone]);
        if ($existing && $existing['role'] !== 'rider') return 'That email address belongs to a staff account. Use a different address for the rider.';
        if ($existing && fetchValue("SELECT COUNT(*) FROM riders WHERE user_id = ? AND id <> ?", [$existing['id'], $riderId])) {
            return 'That email address is already linked to another rider.';
        }
        $userId = $existing ? (int) $existing['id'] : account_create([
            'name' => $rider['full_name'], 'email' => $rider['email'] ?? '', 'phone' => $phone ?? $rider['phone'], 'role' => 'rider',
            'city_id' => $rider['city_id'], 'stage_id' => $rider['stage_id'],
        ]);
        if (is_string($userId)) return $userId;
        runQuery("UPDATE riders SET user_id = ? WHERE id = ?", [$userId, $riderId]);
    }
    $byEmail = (bool) fetchValue("SELECT email FROM users WHERE id = ?", [$userId]);
    $sent = account_send_invite($userId);
    $to = $byEmail ? (string) fetchValue("SELECT email FROM users WHERE id = ?", [$userId]) : 'the phone ending ' . substr((string) $phone, -3);
    audit_log('INVITE', 'user', (string) $userId, 'Portal link for rider ' . $rider['id_number'] . ($sent ? ' sent to ' . $to : ' could not be sent'));
    if (!$sent) return $byEmail ? 'The account is ready, but the email could not be sent. Check the mail settings.' : 'The account is ready, but the SMS could not be sent. Check Super Admin → SMS.';
    return 'A link to set the portal password was sent to ' . $to . '. It works for 48 hours' . ($byEmail ? '.' : '; the rider then signs in with their phone number.');
}
