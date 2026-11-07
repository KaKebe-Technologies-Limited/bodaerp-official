<?php
// Search box in the portal top bar. One query string is matched against every record the signed-in role may open:
// riders (name, permit number, NIN, phone, plate, bike make and model, email), payments (receipt number, transaction
// reference), ID cards (serial), enforcement codes, cash handover references, stages, cities and staff accounts
// (name, email, phone, role). Each role sees only its own scope, the same walls the pages enforce:
// chairperson = own stage, city admin = own city, super admin = everything, government = the read-only registry
// fields (name, permit, plate, city) and cities. Riders have no search.
require_once __DIR__ . '/functions.php';

const SEARCH_ROLES = ['super_admin', 'city_admin', 'chairperson', 'government'];

function search_like(string $q): string {
    return '%' . addcslashes($q, '%_\\') . '%';
}

/** Where a rider opens for each role. */
function search_rider_url(string $role, int $riderId, string $idNumber): string {
    return match ($role) {
        'super_admin' => '/pages/superadmin/edit-rider.php?id=' . $riderId,
        'city_admin'  => '/pages/citycouncil/edit-rider.php?id=' . $riderId,
        'chairperson' => '/pages/chairperson/rider-profile.php?id=' . $riderId,
        default       => '/pages/government/registry.php?q=' . rawurlencode($idNumber),
    };
}

/**
 * @return array<string, list<array{title:string, detail:string, url:string}>> group label => results, in the order shown
 */
function global_search(string $role, string $q, int $perGroup = 6): array {
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if (!in_array($role, SEARCH_ROLES, true) || mb_strlen($q) < 2) return [];
    $q = mb_substr($q, 0, 80);
    $like = search_like($q);
    $compact = search_like(str_replace(' ', '', $q));         // plates and numbers typed with or without spaces
    $digits = preg_replace('/\D/', '', $q);
    $phoneLike = strlen($digits) >= 4 ? search_like(substr($digits, -9)) : null;
    $limit = max(1, min(25, $perGroup));
    $cityId = (string) ($_SESSION['city_id'] ?? '');
    $stageId = (int) ($_SESSION['stage_id'] ?? 0);
    $scope = match ($role) {
        'chairperson' => ['r.stage_id = ?', $stageId],
        'city_admin'  => ['r.city_id = ?', $cityId],
        default       => ['1 = ?', 1],
    };
    $groups = [];

    $riderMatch = "(r.full_name LIKE ? OR r.id_number LIKE ? OR REPLACE(r.bike_plate, ' ', '') LIKE ?";
    $params = [$like, $like, $compact];
    if ($role !== 'government') {
        $riderMatch .= " OR r.nin LIKE ? OR r.bike_model LIKE ? OR r.email LIKE ?" . ($phoneLike ? " OR r.phone LIKE ? OR r.next_of_kin_contact LIKE ?" : '');
        array_push($params, $like, $like, $like);
        if ($phoneLike) array_push($params, $phoneLike, $phoneLike);
    }
    $riderMatch .= ')';
    $rows = fetchAll("SELECT r.id, r.full_name, r.id_number, r.bike_plate, r.bike_model, r.status, s.name AS stage_name, c.name AS city_name
        FROM riders r LEFT JOIN stages s ON s.id = r.stage_id LEFT JOIN cities c ON c.id = r.city_id
        WHERE r.deleted_at IS NULL AND {$scope[0]} AND $riderMatch
        ORDER BY (r.id_number = ? OR REPLACE(r.bike_plate, ' ', '') = ?) DESC, r.full_name LIMIT $limit",
        array_merge([$scope[1]], $params, [$q, str_replace(' ', '', $q)]));
    foreach ($rows as $r) {
        $where = $role === 'chairperson' ? '' : ' · ' . trim(($r['stage_name'] ?? '') . ($role !== 'city_admin' && $r['city_name'] ? ', ' . $r['city_name'] : ''), ', ');
        $groups['Riders'][] = [
            'title' => $r['full_name'],
            'detail' => trim($r['id_number'] . ' · ' . ($r['bike_plate'] ?: 'no plate') . ($role !== 'government' && $r['bike_model'] ? ' ' . $r['bike_model'] : '') . $where . ' · ' . ucfirst((string) $r['status']), ' ·'),
            'url' => search_rider_url($role, (int) $r['id'], (string) $r['id_number']),
        ];
    }

    if ($role === 'government') {
        foreach (fetchAll("SELECT id, name FROM cities WHERE name LIKE ? OR id LIKE ? ORDER BY name LIMIT $limit", [$like, $like]) as $c) {
            $groups['Cities'][] = ['title' => $c['name'], 'detail' => 'City code ' . $c['id'], 'url' => '/pages/government/comparison.php'];
        }
        return $groups;
    }

    $rows = fetchAll("SELECT p.id, p.rider_id, p.receipt_number, p.transaction_reference, p.amount, p.payment_method, p.status, p.paid_at, p.created_at,
            r.full_name, r.id_number, r.city_id
        FROM payments p JOIN riders r ON r.id = p.rider_id
        WHERE {$scope[0]} AND (p.receipt_number LIKE ? OR p.transaction_reference LIKE ? OR p.iotec_transaction_id LIKE ?" . ($phoneLike ? " OR p.payer_phone LIKE ?" : '') . ")
        ORDER BY p.created_at DESC LIMIT $limit",
        array_merge([$scope[1], $like, $like, $like], $phoneLike ? [$phoneLike] : []));
    foreach ($rows as $p) {
        $hasReceipt = $p['status'] === 'Confirmed' && $p['receipt_number'];
        $url = match (true) {
            $role === 'city_admin' && $hasReceipt  => '/pages/citycouncil/receipt.php?id=' . (int) $p['id'],
            $role === 'chairperson' && $hasReceipt => '/pages/chairperson/receipt.php?id=' . (int) $p['id'],
            $role === 'city_admin'                 => '/pages/citycouncil/payment-transactions.php?status=' . rawurlencode((string) $p['status']),
            default                                => search_rider_url($role, (int) $p['rider_id'], (string) $p['id_number']),
        };
        $groups['Payments'][] = [
            'title' => ($p['receipt_number'] ?: $p['transaction_reference'] ?: 'Payment ' . $p['id']) . ' · ' . formatCurrency($p['amount']),
            'detail' => $p['full_name'] . ' · ' . $p['payment_method'] . ' · ' . $p['status'] . ' · ' . formatDate($p['paid_at'] ?: $p['created_at']),
            'url' => $url,
        ];
    }

    $rows = fetchAll("SELECT k.serial, k.status, k.rider_id, r.full_name, r.id_number FROM id_cards k JOIN riders r ON r.id = k.rider_id
        WHERE {$scope[0]} AND r.deleted_at IS NULL AND k.serial LIKE ? ORDER BY k.issued_at DESC LIMIT $limit", [$scope[1], $like]);
    foreach ($rows as $k) {
        $groups['ID cards'][] = [
            'title' => $k['serial'],
            'detail' => $k['full_name'] . ' · ' . ucfirst((string) $k['status']),
            'url' => match ($role) {
                'city_admin'  => '/pages/citycouncil/id-card.php?id=' . (int) $k['rider_id'],
                'chairperson' => '/pages/chairperson/id-preview.php?id=' . (int) $k['rider_id'],
                default       => search_rider_url($role, (int) $k['rider_id'], (string) $k['id_number']),
            },
        ];
    }

    if ($role !== 'chairperson') {
        $eScope = $role === 'city_admin' ? ['e.city_id = ?', $cityId] : ['1 = ?', 1];
        $rows = fetchAll("SELECT e.action_code, e.type, e.status, e.plate, e.rider_id, r.full_name, r.id_number FROM enforcement_actions e LEFT JOIN riders r ON r.id = e.rider_id
            WHERE {$eScope[0]} AND (e.action_code LIKE ? OR REPLACE(e.plate, ' ', '') LIKE ?) ORDER BY e.action_date DESC LIMIT $limit", [$eScope[1], $like, $compact]);
        foreach ($rows as $e) {
            $groups['Enforcement'][] = [
                'title' => $e['action_code'] . ' · ' . $e['type'],
                'detail' => ($e['full_name'] ?: $e['plate'] ?: 'Unknown rider') . ' · ' . ucfirst((string) $e['status']),
                'url' => $role === 'city_admin' ? '/pages/citycouncil/enforcement-actions.php'
                    : ($e['rider_id'] ? search_rider_url($role, (int) $e['rider_id'], (string) $e['id_number']) : '/pages/government/enforcement.php'),
            ];
        }
    }

    $hScope = match ($role) { 'chairperson' => ['h.stage_id = ?', $stageId], 'city_admin' => ['h.city_id = ?', $cityId], default => ['1 = ?', 1] };
    if ($role !== 'super_admin') {
        $rows = fetchAll("SELECT h.reference, h.status, h.handed_amount, h.created_at, s.name AS stage_name FROM cash_handovers h JOIN stages s ON s.id = h.stage_id
            WHERE {$hScope[0]} AND h.reference LIKE ? ORDER BY h.created_at DESC LIMIT $limit", [$hScope[1], $like]);
        foreach ($rows as $h) {
            $groups['Cash handovers'][] = [
                'title' => $h['reference'] . ' · ' . formatCurrency($h['handed_amount']),
                'detail' => $h['stage_name'] . ' · ' . ucfirst((string) $h['status']) . ' · ' . formatDate($h['created_at']),
                'url' => $role === 'city_admin' ? '/pages/citycouncil/cash-handovers.php?status=' . rawurlencode((string) $h['status']) : '/pages/chairperson/cash-handover.php',
            ];
        }
    }

    if ($role !== 'chairperson') {
        $sScope = $role === 'city_admin' ? ['s.city_id = ?', $cityId] : ['1 = ?', 1];
        $rows = fetchAll("SELECT s.id, s.name, s.code, s.location, s.chairperson_name, s.status, c.name AS city_name, s.city_id FROM stages s JOIN cities c ON c.id = s.city_id
            WHERE {$sScope[0]} AND (s.name LIKE ? OR s.code LIKE ? OR s.location LIKE ? OR s.route LIKE ? OR s.chairperson_name LIKE ?" . ($phoneLike ? " OR s.chairperson_phone LIKE ?" : '') . ")
            ORDER BY s.name LIMIT $limit", array_merge([$sScope[1], $like, $like, $like, $like, $like], $phoneLike ? [$phoneLike] : []));
        foreach ($rows as $s) {
            $groups['Stages'][] = [
                'title' => $s['name'],
                'detail' => trim(($s['location'] ?: '') . ($role === 'super_admin' ? ', ' . $s['city_name'] : '') . ($s['chairperson_name'] ? ' · Chairperson ' . $s['chairperson_name'] : ''), ', ·'),
                'url' => $role === 'city_admin' ? '/pages/citycouncil/all-stages.php?q=' . rawurlencode($s['name']) : '/pages/superadmin/cities.php?q=' . rawurlencode($s['city_name']),
            ];
        }
    }

    if ($role === 'super_admin') {
        foreach (fetchAll("SELECT id, name, status FROM cities WHERE name LIKE ? OR id LIKE ? ORDER BY name LIMIT $limit", [$like, $like]) as $c) {
            $groups['Cities'][] = ['title' => $c['name'], 'detail' => 'City code ' . $c['id'] . ' · ' . ucfirst((string) $c['status']), 'url' => '/pages/superadmin/cities.php?q=' . rawurlencode($c['name'])];
        }
    }

    // Staff accounts. A typed role name ("chairperson", "city admin") lists accounts with that role.
    if ($role !== 'chairperson') {
        $roles = array_keys(array_filter(ROLE_LABELS, fn($label, $key) => stripos($label, $q) !== false || stripos(str_replace('_', ' ', $key), $q) !== false, ARRAY_FILTER_USE_BOTH));
        $uScope = $role === 'city_admin' ? ["u.city_id = ? AND u.role IN ('chairperson', 'city_admin')", $cityId] : ["u.role <> 'rider' AND 1 = ?", 1];
        $roleSql = $roles ? ' OR u.role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')' : '';
        $rows = fetchAll("SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.stage_id, s.name AS stage_name, c.name AS city_name FROM users u
            LEFT JOIN stages s ON s.id = u.stage_id LEFT JOIN cities c ON c.id = u.city_id
            WHERE u.deleted_at IS NULL AND {$uScope[0]} AND (u.name LIKE ? OR u.email LIKE ?" . ($phoneLike ? " OR u.phone LIKE ?" : '') . $roleSql . ")
            ORDER BY u.name LIMIT $limit", array_merge([$uScope[1], $like, $like], $phoneLike ? [$phoneLike] : [], $roles));
        foreach ($rows as $u) {
            $groups['Users'][] = [
                'title' => $u['name'],
                'detail' => role_label($u['role']) . ($u['stage_name'] ? ' · ' . $u['stage_name'] : '') . ($role === 'super_admin' && $u['city_name'] ? ', ' . $u['city_name'] : '')
                    . ($u['email'] ? ' · ' . $u['email'] : '') . ($u['status'] === 'suspended' ? ' · Suspended' : ''),
                'url' => $role === 'super_admin' ? '/pages/superadmin/users.php?q=' . rawurlencode((string) ($u['email'] ?: $u['name']))
                    : '/pages/citycouncil/all-stages.php?q=' . rawurlencode((string) ($u['stage_name'] ?? '')),
            ];
        }
    }
    return $groups;
}
