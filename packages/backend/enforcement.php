<?php
// Enforcement actions as the rider and the roadside check see them. A suspension that is still pending means the
// permit is suspended: verify.php says so even when the fee is paid, and it lifts when the city resolves or closes
// the action (or upholds the rider's appeal). Only functions.php is loaded here so ussd.php can use
// rider_suspension() without a session; the appeal functions run on portal pages, which have auth.php.
require_once __DIR__ . '/functions.php';

const ENFORCEMENT_LABELS = ['warning' => 'Warning', 'fine' => 'Fine', 'suspension' => 'Permit suspended', 'impound' => 'Motorcycle impounded'];
const ENFORCEMENT_STATUS_LABELS = ['pending' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed'];

/** The suspension in force for a rider, or null. */
function rider_suspension(int $riderId): ?array {
    return fetchOne("SELECT action_code, action_date, description FROM enforcement_actions
        WHERE rider_id = ? AND type = 'suspension' AND status = 'pending' AND COALESCE(appeal_status, '') <> 'upheld'
        ORDER BY action_date DESC, id DESC LIMIT 1", [$riderId]);
}

/** Rider sends an appeal against one of their own open actions. Returns an error message, or '' when saved. */
function enforcement_appeal(int $riderId, int $actionId, string $text, int $byUser): string {
    $text = trim($text);
    if (mb_strlen($text) < 20) return 'Explain in a few sentences why the action is wrong (at least 20 characters).';
    $action = fetchOne("SELECT id, action_code, city_id, status, appeal_status FROM enforcement_actions WHERE id = ? AND rider_id = ?", [$actionId, $riderId]);
    if (!$action) return 'That action was not found.';
    if ($action['status'] !== 'pending') return 'Only open actions can be appealed.';
    if ($action['appeal_status'] !== null) return 'You have already appealed this action.';
    if (!rate_limit('appeal:' . $riderId, 5, 86400)) return 'You have sent several appeals today. Try again tomorrow.';
    runQuery("UPDATE enforcement_actions SET appeal_text = ?, appeal_at = NOW(), appeal_status = 'open' WHERE id = ? AND appeal_status IS NULL",
        [mb_substr($text, 0, 3000), $actionId])->close();
    audit_log('UPDATE', 'enforcement_action', $action['action_code'], 'Rider appealed');
    $inbox = (string) fetchValue("SELECT contact_email FROM cities WHERE id = ?", [$action['city_id']]);
    if ($inbox !== '') {
        send_email($inbox, 'Appeal against ' . $action['action_code'],
            '<p>A rider appealed enforcement action ' . h($action['action_code']) . '.</p><p>' . nl2br(h($text)) . '</p><p><a href="' . h(app_url('pages/citycouncil/enforcement-actions.php')) . '">Answer it on the Enforcement page</a>.</p>');
    }
    return '';
}

/** City answers an appeal. Upheld closes the action, so a suspension lifts at once. */
function enforcement_answer_appeal(int $actionId, string $cityId, string $decision, string $response, int $byUser): bool {
    if (!in_array($decision, ['upheld', 'rejected'], true) || trim($response) === '') return false;
    $action = fetchOne("SELECT id, action_code, rider_id FROM enforcement_actions WHERE id = ? AND city_id = ? AND appeal_status = 'open'", [$actionId, $cityId]);
    if (!$action) return false;
    runQuery("UPDATE enforcement_actions SET appeal_status = ?, appeal_response = ?, appeal_by = ?" . ($decision === 'upheld' ? ", status = 'closed'" : '') . " WHERE id = ?",
        [$decision, mb_substr(trim($response), 0, 1000), $byUser, $actionId])->close();
    audit_log('UPDATE', 'enforcement_action', $action['action_code'], 'Appeal ' . $decision);
    if ($action['rider_id']) {
        rider_notify((int) $action['rider_id'], 'Important Update', 'Your appeal against ' . $action['action_code'] . ' was ' . ($decision === 'upheld' ? 'accepted and the action is closed' : 'not accepted') . ': ' . trim($response));
        if ($target = rider_sms_target((int) $action['rider_id'])) send_sms($target['phone'], 'Appeal ' . $action['action_code'] . ': ' . ($decision === 'upheld' ? 'accepted, the action is closed.' : 'not accepted. See your portal.'));
    }
    return true;
}
