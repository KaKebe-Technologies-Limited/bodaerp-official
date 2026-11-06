<?php
// Bulk rider import for a city admin: a CSV or Excel (.xlsx) register, checked row by row before anything is
// saved. Valid rows become riders in one go; rows with problems are listed with the reason and skipped.
// No Composer: .xlsx is read with ZipArchive + SimpleXML (first worksheet, values only).
require_once __DIR__ . '/functions.php';

const IMPORT_MAX_ROWS = 2000;
const IMPORT_MAX_BYTES = 3 * 1048576;

/** Column => [label, required, example]. Header names are matched loosely ("Full name", "full_name", "FULL NAME"). */
const IMPORT_COLUMNS = [
    'full_name'           => ['Full name', true, 'AKELLO JAMES'],
    'nin'                 => ['NIN', true, 'CM90012345ABCD'],
    'phone'               => ['Phone', true, '0772123456'],
    'bike_plate'          => ['Bike plate', true, 'UFA 123K'],
    'stage'               => ['Stage', true, 'Lira Main Market'],
    'date_of_birth'       => ['Date of birth', false, '1994-03-21'],
    'gender'              => ['Gender', false, 'Male'],
    'email'               => ['Email', false, ''],
    'physical_address'    => ['Address', false, 'Adyel Division'],
    'next_of_kin_name'    => ['Next of kin', false, ''],
    'next_of_kin_contact' => ['Next of kin phone', false, ''],
    'bike_model'          => ['Bike model', false, 'Bajaj Boxer'],
    'route'               => ['Route', false, ''],
    'expiry_date'         => ['Permit valid until', false, '2027-06-30'],
];

function import_header_key(string $header): ?string {
    $norm = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($header, " \t\n\r\0\x0B\xEF\xBB\xBF"))), '_');
    $aliases = ['name' => 'full_name', 'rider_name' => 'full_name', 'national_id' => 'nin', 'nin_number' => 'nin', 'phone_number' => 'phone',
        'mobile' => 'phone', 'plate' => 'bike_plate', 'number_plate' => 'bike_plate', 'stage_name' => 'stage', 'dob' => 'date_of_birth',
        'address' => 'physical_address', 'next_of_kin' => 'next_of_kin_name', 'next_of_kin_phone' => 'next_of_kin_contact',
        'model' => 'bike_model', 'permit_valid_until' => 'expiry_date', 'expiry' => 'expiry_date', 'valid_until' => 'expiry_date'];
    $norm = $aliases[$norm] ?? $norm;
    return isset(IMPORT_COLUMNS[$norm]) ? $norm : null;
}

/** @return list<list<string>> raw cells, first row = headers */
function import_read_cells(string $path, string $originalName): array {
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') return import_read_xlsx($path);
    if ($ext !== 'csv' && $ext !== 'txt') throw new RuntimeException('Upload a .csv or .xlsx file. In Excel: File → Save As → CSV.');
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('The file could not be read.');
    $first = (string) fgets($handle);
    rewind($handle);
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $rows = [];
    while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
        if ($cells === [null]) continue;
        $rows[] = array_map(fn($c) => trim(mb_convert_encoding((string) $c, 'UTF-8', 'UTF-8, Windows-1252')), $cells);
        if (count($rows) > IMPORT_MAX_ROWS + 1) break;
    }
    fclose($handle);
    return $rows;
}

function import_read_xlsx(string $path): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('This server cannot read .xlsx files. Save the sheet as CSV and upload that.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('The .xlsx file could not be opened. Save it as CSV and try again.');
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sst = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sst->si ?? [] as $si) {
            $text = isset($si->t) ? (string) $si->t : '';
            foreach ($si->r ?? [] as $run) $text .= (string) $run->t;
            $shared[] = $text;
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) throw new RuntimeException('The .xlsx file has no worksheet. Save it as CSV and try again.');
    $doc = simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
    $rows = [];
    foreach ($doc->sheetData->row ?? [] as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = 0;
            foreach (str_split(preg_replace('/\d/', '', (string) $c['r'])) as $letter) $col = $col * 26 + (ord($letter) - 64);
            $type = (string) $c['t'];
            $value = $type === 's' ? ($shared[(int) $c->v] ?? '') : ($type === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
            $cells[$col - 1] = trim($value);
        }
        if (!$cells) continue;
        $max = max(array_keys($cells));
        $rows[] = array_map(fn($i) => $cells[$i] ?? '', range(0, $max));
        if (count($rows) > IMPORT_MAX_ROWS + 1) break;
    }
    return $rows;
}

/** Excel stores dates as day numbers; people type 21/03/1994 or 1994-03-21. Returns Y-m-d or null. */
function import_date(string $value): ?string {
    if ($value === '') return null;
    if (is_numeric($value) && (float) $value > 10000 && (float) $value < 80000) return gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
    if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m)) return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $value, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
    return null;
}

/**
 * Checks every row. Returns ready rows (normalised for insert) and problems per row number as people see it in
 * Excel (header = row 1). @return array{ready: list<array>, problems: array<int, list<string>>, missing_columns: list<string>, total: int}
 */
function import_check(array $cells, string $cityId): array {
    $header = array_shift($cells) ?? [];
    $map = [];
    foreach ($header as $i => $h) if (($key = import_header_key((string) $h)) !== null && !isset($map[$key])) $map[$key] = $i;
    $missing = [];
    foreach (IMPORT_COLUMNS as $key => [$label, $required]) if ($required && !isset($map[$key])) $missing[] = $label;
    $result = ['ready' => [], 'problems' => [], 'missing_columns' => $missing, 'total' => 0];
    if ($missing) return $result;

    $stages = [];
    foreach (fetchAll("SELECT id, name FROM stages WHERE city_id = ?", [$cityId]) as $s) {
        $stages[mb_strtolower(trim($s['name']))] = (int) $s['id'];
        $stages[(string) $s['id']] = (int) $s['id'];
    }
    $seen = ['nin' => [], 'bike_plate' => [], 'phone' => []];
    foreach ($cells as $n => $raw) {
        if (!array_filter($raw, fn($c) => $c !== '')) continue;
        $rowNo = $n + 2;
        $result['total']++;
        if ($result['total'] > IMPORT_MAX_ROWS) { $result['problems'][$rowNo] = ['More than ' . IMPORT_MAX_ROWS . ' rows: split the file and import the rest separately.']; break; }
        $v = [];
        foreach ($map as $key => $i) $v[$key] = trim((string) ($raw[$i] ?? ''));
        $errors = [];

        $row = ['full_name' => mb_strtoupper(preg_replace('/\s+/', ' ', $v['full_name'])), 'nin' => strtoupper(str_replace(' ', '', $v['nin'])),
            'bike_plate' => strtoupper(preg_replace('/\s+/', ' ', $v['bike_plate']))];
        if ($row['full_name'] === '') $errors[] = 'Full name is empty.';
        if (!preg_match('/^[A-Z0-9]{14}$/', $row['nin'])) $errors[] = 'NIN must be 14 letters and digits.';
        $digits = substr(preg_replace('/\D/', '', $v['phone']), -9);
        $row['phone'] = preg_match('/^7\d{8}$/', $digits) ? '+256' . $digits : null;
        if (!$row['phone']) $errors[] = 'Phone is not a Ugandan mobile number.';
        if ($row['bike_plate'] === '') $errors[] = 'Bike plate is empty.';
        $row['stage_id'] = $stages[mb_strtolower($v['stage'])] ?? null;
        if (!$row['stage_id']) $errors[] = 'Stage "' . $v['stage'] . '" is not a stage in this city. Add it in Manage stages first.';

        $row['date_of_birth'] = import_date($v['date_of_birth'] ?? '');
        if (($v['date_of_birth'] ?? '') !== '' && !$row['date_of_birth']) $errors[] = 'Date of birth is not a date (use 1994-03-21 or 21/03/1994).';
        $gender = ucfirst(strtolower($v['gender'] ?? ''));
        $row['gender'] = in_array($gender, ['Male', 'Female', 'Other'], true) ? $gender : (in_array($gender, ['M', 'F'], true) ? ($gender === 'M' ? 'Male' : 'Female') : null);
        $row['email'] = ($v['email'] ?? '') !== '' ? mb_strtolower($v['email']) : null;
        if ($row['email'] !== null && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
        $nok = substr(preg_replace('/\D/', '', $v['next_of_kin_contact'] ?? ''), -9);
        $row['next_of_kin_contact'] = strlen($nok) === 9 ? '+256' . $nok : null;
        foreach (['physical_address' => 255, 'next_of_kin_name' => 150, 'bike_model' => 100, 'route' => 150] as $key => $len) $row[$key] = mb_substr($v[$key] ?? '', 0, $len) ?: null;
        $row['expiry_date'] = import_date($v['expiry_date'] ?? '');
        if (($v['expiry_date'] ?? '') !== '' && !$row['expiry_date']) $errors[] = 'Permit valid until is not a date.';

        foreach (['nin', 'bike_plate', 'phone'] as $field) {
            $key = str_replace(' ', '', (string) $row[$field]);
            if ($key === '') continue;
            if (isset($seen[$field][$key]) && $field !== 'phone') $errors[] = IMPORT_COLUMNS[$field][0] . ' is repeated in this file (row ' . $seen[$field][$key] . ').';
            $seen[$field][$key] ??= $rowNo;
        }
        if (!$errors) {
            foreach (rider_duplicates(['nin' => $row['nin'], 'bike_plate' => $row['bike_plate']]) as $dup) {
                $errors[] = $dup['label'] . ' is already registered at ' . $dup['where'] . '.';
            }
        }
        if ($errors) $result['problems'][$rowNo] = $errors;
        else $result['ready'][] = $row + ['row_no' => $rowNo];
    }
    return $result;
}

/** Saves checked rows as riders in one transaction. Returns the import id. */
function import_commit(array $ready, string $cityId, int $userId, string $fileName, int $totalRows): int {
    $fee = (float) fetchValue("SELECT annual_fee FROM cities WHERE id = ?", [$cityId]);
    $db = db();
    $db->begin_transaction();
    try {
        runQuery("INSERT INTO rider_imports (city_id, imported_by, file_name, rows_total) VALUES (?,?,?,?)", [$cityId, $userId, mb_substr($fileName, 0, 200), $totalRows])->close();
        $importId = (int) $db->insert_id;
        $created = 0;
        foreach ($ready as $r) {
            $status = $r['expiry_date'] ? ($r['expiry_date'] >= date('Y-m-d') ? 'active' : 'expired') : 'pending';
            runQuery("INSERT INTO riders (city_id, stage_id, id_number, verify_token, full_name, nin, date_of_birth, gender, phone, email, physical_address,
                      next_of_kin_name, next_of_kin_contact, bike_plate, bike_model, route, status, member_since, expiry_date, annual_tax, created_by, import_id)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?)",
                [$cityId, $r['stage_id'], 'TMP-' . bin2hex(random_bytes(6)), bin2hex(random_bytes(10)), $r['full_name'], $r['nin'], $r['date_of_birth'], $r['gender'],
                 $r['phone'], $r['email'], $r['physical_address'], $r['next_of_kin_name'], $r['next_of_kin_contact'], $r['bike_plate'], $r['bike_model'], $r['route'],
                 $status, $r['expiry_date'], $fee, $userId, $importId])->close();
            $riderId = (int) $db->insert_id;
            runQuery("UPDATE riders SET id_number = ? WHERE id = ?", [nextIdNumber($riderId, $cityId), $riderId])->close();
            $created++;
        }
        runQuery("UPDATE rider_imports SET rows_created = ?, rows_skipped = ? WHERE id = ?", [$created, max(0, $totalRows - $created), $importId])->close();
        $db->commit();
        require_once __DIR__ . '/id_cards.php';
        foreach (fetchAll("SELECT id FROM riders WHERE import_id = ?", [$importId]) as $imported) id_card_ensure((int) $imported['id']);
        return $importId;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/** The checked file waits in storage/imports between preview and confirm, never in the session. */
function import_stash_path(string $token): string {
    return storage_path('imports') . '/' . preg_replace('/[^a-f0-9]/', '', $token) . '.json';
}
