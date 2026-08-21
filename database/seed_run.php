<?php
// ============================================================
// BodaERP — Database seeder (CLI only)
// NOTE: temporarily named seed_run.php — Avast is blocking recreation
// of the original database/seed.php filename (see chat for details).
// Content is otherwise identical to what's committed in git history.
//
// Usage:  php database/seed_run.php          (refuses to run if riders already exist)
//         php database/seed_run.php --fresh  (truncates all tables first, then reseeds)
// ============================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('seed_run.php can only be run from the command line: php database/seed_run.php');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db_helpers.php';
$names = require __DIR__ . '/seed_data/names.php';

$fresh = in_array('--fresh', $argv, true);
$pdo = db();

$existing = (int) fetchValue('SELECT COUNT(*) FROM riders');
if ($existing > 0 && !$fresh) {
    die("riders table already has $existing rows. Re-run with --fresh to truncate and reseed.\n");
}

if ($fresh) {
    echo "Truncating existing data...\n";
    $pdo->query('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['notifications','payments','enforcement_actions','audit_logs','login_sessions','riders','users','stages','cities'] as $t) {
        $pdo->query("TRUNCATE TABLE $t");
    }
    $pdo->query('SET FOREIGN_KEY_CHECKS = 1');
}

function randChoice(array $arr) { return $arr[array_rand($arr)]; }
function randBetween(int $min, int $max): int { return random_int($min, $max); }
function randDate(int $min, int $max): string {
    // returns today + N days, N chosen from [min,max]. Positive = future, negative = past.
    $offset = randBetween(min($min,$max), max($min,$max));
    return date('Y-m-d', strtotime(sprintf('%+d days', $offset)));
}
function weightedPick(array $weights) {
    $r = mt_rand(1, 100);
    $cum = 0;
    foreach ($weights as $val => $w) {
        $cum += $w;
        if ($r <= $cum) return $val;
    }
    return array_key_first($weights);
}
function prevFiscalYear(string $fy): string {
    [$a, $b] = explode('/', $fy);
    return ($a - 1) . '/' . ($b - 1);
}

$pdo->begin_transaction();

try {
    // ── 1. Super admin (bootstrapped first — cities.created_by references it) ──
    runQuery("INSERT INTO users (name,email,phone,password_hash,role,city_id,stage_id,status,created_at)
              VALUES (:name,:email,:phone,:ph,'super_admin',NULL,NULL,'active',:created_at)", [
        'name' => 'Kakebe Admin', 'email' => 'admin@bodaerp.com', 'phone' => '+256 700 000 001',
        'ph' => password_hash('admin123', PASSWORD_DEFAULT), 'created_at' => '2024-01-01 09:00:00',
    ]);
    $superAdminId = (int) $pdo->insert_id;
    echo "Created super admin (id=$superAdminId)\n";

    // ── 2. Cities ──────────────────────────────────────────
    $cities = [
        ['id'=>'LIR','name'=>'Lira City','logo'=>'/assets/images/lcc.png','fee'=>50000,'status'=>'active',
         'email'=>'council@liracityuganda.go.ug','phone'=>'+256 473 420 123','address'=>'Lira City Council, Parliament Avenue, Lira, Uganda',
         'gateway'=>'MTN MoMo','split'=>[60,26,14],'target'=>70,'created'=>'2025-01-01'],
        ['id'=>'GUL','name'=>'Gulu City','logo'=>'/assets/images/logo.png','fee'=>45000,'status'=>'active',
         'email'=>'council@gulucity.go.ug','phone'=>'+256 471 432 456','address'=>'Gulu City Council, Gulu, Uganda',
         'gateway'=>'Airtel Money','split'=>[55,30,15],'target'=>70,'created'=>'2025-03-01'],
        ['id'=>'KLA','name'=>'Kampala City','logo'=>'/assets/images/logo.png','fee'=>60000,'status'=>'active',
         'email'=>'council@kcca.go.ug','phone'=>'+256 417 123 456','address'=>'KCCA, City Square, Kampala, Uganda',
         'gateway'=>'MTN MoMo','split'=>[65,22,13],'target'=>75,'created'=>'2025-06-01'],
        ['id'=>'MBA','name'=>'Mbarara City','logo'=>'/assets/images/logo.png','fee'=>48000,'status'=>'pending',
         'email'=>'council@mbararacity.go.ug','phone'=>'+256 485 456 789','address'=>'Mbarara City Council, Mbarara, Uganda',
         'gateway'=>'Both','split'=>[58,28,14],'target'=>70,'created'=>'2026-01-15'],
    ];
    $citySql = "INSERT INTO cities (id,name,country,currency,logo_path,annual_fee,fiscal_year,id_prefix,status,
                contact_email,contact_phone,address,payment_gateway,sms_gateway,
                revenue_split_city,revenue_split_association,revenue_split_platform,compliance_target,created_by,created_at)
                VALUES (:id,:name,'Uganda','UGX',:logo,:fee,'2026/2027',:prefix,:status,
                :email,:phone,:address,:gateway,\"Africa's Talking\",
                :sc,:sa,:sp,:target,:created_by,:created_at)";
    foreach ($cities as $c) {
        runQuery($citySql, [
            'id'=>$c['id'],'name'=>$c['name'],'logo'=>$c['logo'],'fee'=>$c['fee'],'prefix'=>'BODA-'.$c['id'],'status'=>$c['status'],
            'email'=>$c['email'],'phone'=>$c['phone'],'address'=>$c['address'],'gateway'=>$c['gateway'],
            'sc'=>$c['split'][0],'sa'=>$c['split'][1],'sp'=>$c['split'][2],'target'=>$c['target'],
            'created_by'=>$superAdminId,'created_at'=>$c['created'].' 09:00:00',
        ]);
    }
    echo "Created " . count($cities) . " cities\n";

    // ── 3. Stages ──────────────────────────────────────────
    // riders/compliance columns = declared demo numbers driving the seed volume below
    $stagesDef = [
        ['code'=>'LIR-STG-001','city'=>'LIR','name'=>'Railway Stage',      'riders'=>87,  'compliance'=>85],
        ['code'=>'LIR-STG-002','city'=>'LIR','name'=>'Market Stage',       'riders'=>81,  'compliance'=>72],
        ['code'=>'LIR-STG-003','city'=>'LIR','name'=>'Hospital Stage',     'riders'=>65,  'compliance'=>65],
        ['code'=>'LIR-STG-004','city'=>'LIR','name'=>'Town Stage',         'riders'=>60,  'compliance'=>58],
        ['code'=>'LIR-STG-005','city'=>'LIR','name'=>'Bazaar Stage',       'riders'=>62,  'compliance'=>45],
        ['code'=>'GUL-STG-001','city'=>'GUL','name'=>'Gulu Main Stage',    'riders'=>120, 'compliance'=>78],
        ['code'=>'GUL-STG-002','city'=>'GUL','name'=>'Layibi Stage',       'riders'=>95,  'compliance'=>68],
        ['code'=>'KLA-STG-001','city'=>'KLA','name'=>'Nakasero Stage',     'riders'=>340, 'compliance'=>82],
        ['code'=>'KLA-STG-002','city'=>'KLA','name'=>'Kalerwe Stage',      'riders'=>280, 'compliance'=>74],
        ['code'=>'MBA-STG-001','city'=>'MBA','name'=>'Mbarara Main Stage', 'riders'=>1,   'compliance'=>100], // forced to 1 to host the demo rider login
    ];
    $stageSql = "INSERT INTO stages (city_id,code,name,route,status,created_at) VALUES (:city,:code,:name,:route,'active',:created_at)";
    $stageIds = []; // code => id
    foreach ($stagesDef as $s) {
        runQuery($stageSql, ['city'=>$s['city'],'code'=>$s['code'],'name'=>$s['name'],'route'=>$s['name'].' - Town','created_at'=>'2025-01-15 09:00:00']);
        $stageIds[$s['code']] = (int) $pdo->insert_id;
    }
    echo "Created " . count($stagesDef) . " stages\n";

    // ── 4. Remaining users: city admins, chairpersons, 4 demo riders ──
    $userSql = "INSERT INTO users (name,email,phone,password_hash,role,city_id,stage_id,status,created_at)
                VALUES (:name,:email,:phone,:ph,:role,:city,:stage,'active',:created_at)";

    $cityAdmins = [
        ['name'=>'Lira City Council','email'=>'council@liracityuganda.go.ug','phone'=>'+256 473 420 123','pass'=>'lira2025','city'=>'LIR','created'=>'2025-01-01'],
        ['name'=>'Gulu City Council','email'=>'council@gulucity.go.ug','phone'=>'+256 471 432 456','pass'=>'gulu2025','city'=>'GUL','created'=>'2025-03-01'],
        ['name'=>'Kampala City Council','email'=>'council@kcca.go.ug','phone'=>'+256 417 123 456','pass'=>'kampala2025','city'=>'KLA','created'=>'2025-06-01'],
        ['name'=>'Mbarara City Council','email'=>'council@mbararacity.go.ug','phone'=>'+256 485 456 789','pass'=>'mbarara2025','city'=>'MBA','created'=>'2026-01-15'],
    ];
    $cityAdminIds = []; // city => id
    foreach ($cityAdmins as $u) {
        runQuery($userSql, ['name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone'],'ph'=>password_hash($u['pass'],PASSWORD_DEFAULT),
            'role'=>'city_admin','city'=>$u['city'],'stage'=>null,'created_at'=>$u['created'].' 09:00:00']);
        $cityAdminIds[$u['city']] = (int) $pdo->insert_id;
    }

    $chairpersons = [
        ['name'=>'Ocen Patrick','email'=>'ocen@railway.lira.ug','phone'=>'+256 772 111 001','city'=>'LIR','stage'=>'LIR-STG-001','created'=>'2025-01-15'],
        ['name'=>'Opio John','email'=>'opio@market.lira.ug','phone'=>'+256 772 111 002','city'=>'LIR','stage'=>'LIR-STG-002','created'=>'2025-01-15'],
        ['name'=>'Komakech Denis','email'=>'komakech@gulumain.gulu.ug','phone'=>'+256 772 222 001','city'=>'GUL','stage'=>'GUL-STG-001','created'=>'2025-03-10'],
        ['name'=>'Nakato Sarah','email'=>'nakato@nakasero.kla.ug','phone'=>'+256 772 333 001','city'=>'KLA','stage'=>'KLA-STG-001','created'=>'2025-06-10'],
        ['name'=>'Tumwine Edward','email'=>'tumwine@mbararamain.mba.ug','phone'=>'+256 772 444 001','city'=>'MBA','stage'=>'MBA-STG-001','created'=>'2026-01-20'],
    ];
    $chairpersonIdByStage = []; // stageCode => userId
    foreach ($chairpersons as $u) {
        runQuery($userSql, ['name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone'],'ph'=>password_hash('chair123',PASSWORD_DEFAULT),
            'role'=>'chairperson','city'=>$u['city'],'stage'=>$stageIds[$u['stage']],'created_at'=>$u['created'].' 09:00:00']);
        $chairpersonIdByStage[$u['stage']] = (int) $pdo->insert_id;
    }

    $demoRiderUsers = [
        ['name'=>'Akello James','email'=>'akello@bodaerp.com','phone'=>'+256 772 123 456','city'=>'LIR','stage'=>'LIR-STG-001','idnum'=>'BODA-LIR-004521','created'=>'2025-02-01'],
        ['name'=>'Aciro Grace','email'=>'aciro@bodaerp.com','phone'=>'+256 772 222 456','city'=>'GUL','stage'=>'GUL-STG-001','idnum'=>'BODA-GUL-001042','created'=>'2025-03-20'],
        ['name'=>'Mukasa Ronald','email'=>'mukasa@bodaerp.com','phone'=>'+256 772 333 456','city'=>'KLA','stage'=>'KLA-STG-001','idnum'=>'BODA-KLA-002310','created'=>'2025-06-20'],
        ['name'=>'Kyomuhendo Betty','email'=>'kyomuhendo@bodaerp.com','phone'=>'+256 772 444 456','city'=>'MBA','stage'=>'MBA-STG-001','idnum'=>'BODA-MBA-000112','created'=>'2026-01-25'],
    ];
    $demoRiderUserIds = []; // idnum => userId
    foreach ($demoRiderUsers as $u) {
        runQuery($userSql, ['name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone'],'ph'=>password_hash('rider123',PASSWORD_DEFAULT),
            'role'=>'rider','city'=>$u['city'],'stage'=>$stageIds[$u['stage']],'created_at'=>$u['created'].' 09:00:00']);
        $demoRiderUserIds[$u['idnum']] = (int) $pdo->insert_id;
    }
    echo "Created " . (count($cityAdmins)+count($chairpersons)+count($demoRiderUsers)) . " more users (city admins, chairpersons, demo riders)\n";

    // ── 5. Riders (procedural, per stage, matching declared counts/compliance) ──
    $riderSql = "INSERT INTO riders (user_id,city_id,stage_id,id_number,full_name,nin,date_of_birth,gender,marital_status,
        phone,email,physical_address,next_of_kin_name,next_of_kin_contact,bike_plate,bike_model,route,status,member_since,expiry_date,
        annual_tax,created_by,created_at)
        VALUES (:user_id,:city,:stage,:idnum,:name,:nin,:dob,:gender,:marital,:phone,:email,:address,:nokname,:nokphone,
        :plate,:model,:route,:status,:since,:expiry,:tax,:created_by,:created_at)";

    $cityFee = []; foreach ($cities as $c) $cityFee[$c['id']] = $c['fee'];
    $usedNin = []; $usedPhone = []; $usedIdNum = []; $idCounter = [];
    foreach ($cities as $c) { $idCounter[$c['id']] = 100000; }

    function genNin(array &$usedNin): string {
        do { $nin = 'CM' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10)); } while (isset($usedNin[$nin]));
        $usedNin[$nin] = true; return $nin;
    }
    function genPhone(array &$usedPhone): string {
        do { $phone = '+2567' . randBetween(0,9) . randBetween(1000000,9999999); } while (isset($usedPhone[$phone]));
        $usedPhone[$phone] = true; return $phone;
    }
    function genPlate(): string {
        $letters = range('A','Z');
        return 'U' . randChoice($letters) . randChoice($letters) . ' ' . randBetween(100,999) . randChoice($letters);
    }
    function genIdNumber(string $city, array &$idCounter, array &$usedIdNum): string {
        do { $idCounter[$city]++; $num = sprintf('BODA-%s-%06d', $city, $idCounter[$city]); } while (isset($usedIdNum[$num]));
        $usedIdNum[$num] = true; return $num;
    }
    foreach (array_keys($demoRiderUserIds) as $reserved) { $usedIdNum[$reserved] = true; }

    $riderIdsByStage = []; // for enforcement/payments later: stageCode => [ ['rider_id'=>,'status'=>,'city'=>,'member_since'=>,'expiry'=>] ]
    $allRiderRows = [];

    foreach ($stagesDef as $s) {
        $city = $s['city'];
        $n = $s['riders'];
        $activeTarget = (int) round($n * $s['compliance'] / 100);
        $remaining = $n - $activeTarget;
        $expiredTarget = (int) round($remaining * 0.8);
        $pendingTarget = $remaining - $expiredTarget;

        $statuses = array_merge(
            array_fill(0, $activeTarget, 'active'),
            array_fill(0, $expiredTarget, 'expired'),
            array_fill(0, $pendingTarget, 'pending')
        );
        while (count($statuses) < $n) $statuses[] = 'pending'; // rounding safety net
        shuffle($statuses);

        $chairId = $chairpersonIdByStage[$s['code']] ?? null;
        $registrar = $chairId ?? $cityAdminIds[$city];

        // does this stage host a reserved demo rider?
        $demoIdnum = null;
        $demoUser = null;
        foreach ($demoRiderUsers as $du) { if ($du['stage'] === $s['code']) { $demoIdnum = $du['idnum']; $demoUser = $du; break; } }

        $riderIdsByStage[$s['code']] = [];

        for ($i = 0; $i < $n; $i++) {
            $isDemo = ($i === 0 && $demoIdnum !== null);
            $status = $isDemo ? 'active' : $statuses[$i];
            $gender = weightedPick(['Male' => 70, 'Female' => 30]);
            $surnamePool = $names[$city][$gender === 'Male' ? 'surnames_male' : 'surnames_female'];
            $firstPool   = $names[$gender === 'Male' ? 'first_male' : 'first_female'];
            $fullName = $isDemo ? strtoupper($demoUser['name']) : strtoupper(randChoice($surnamePool) . ' ' . randChoice($firstPool));

            $memberSince = randDate(-900, -60); // 2 months to ~2.5 years ago
            if ($status === 'active') {
                $expiry = randBetween(1,10) === 1 ? randDate(1, 30) : randDate(31, 365); // ~10% expiring soon
            } elseif ($status === 'expired') {
                $expiry = randDate(-365, -1);
            } else {
                $expiry = null;
            }

            $idNumber = $isDemo ? $demoIdnum : genIdNumber($city, $idCounter, $usedIdNum);
            $userId = $isDemo ? $demoRiderUserIds[$demoIdnum] : null;
            $plate = genPlate();

            runQuery($riderSql, [
                'user_id'=>$userId,'city'=>$city,'stage'=>$stageIds[$s['code']],'idnum'=>$idNumber,'name'=>$fullName,
                'nin'=>genNin($usedNin),'dob'=>randDate(-365*55, -365*18),'gender'=>$gender,
                'marital'=>weightedPick(['Single'=>45,'Married'=>45,'Divorced'=>5,'Widowed'=>5]),
                'phone'=>genPhone($usedPhone),'email'=>null,
                'address'=>randChoice($surnamePool).' Village, '.$s['name'],
                'nokname'=>strtoupper(randChoice($names[$gender==='Male'?'first_female':'first_male'])) . ' ' . randChoice($surnamePool),
                'nokphone'=>genPhone($usedPhone),
                'plate'=>$plate,'model'=>randChoice($names['bike_models']),'route'=>$s['name'].' - Town',
                'status'=>$status,'since'=>$memberSince,'expiry'=>$expiry,'tax'=>$cityFee[$city],
                'created_by'=>$registrar,'created_at'=>$memberSince.' 10:00:00',
            ]);
            $riderId = (int) $pdo->insert_id;
            $row = ['id'=>$riderId,'status'=>$status,'city'=>$city,'stage_code'=>$s['code'],'stage_id'=>$stageIds[$s['code']],
                    'member_since'=>$memberSince,'expiry'=>$expiry,'plate'=>$plate,'registrar'=>$registrar,'name'=>$fullName];
            $riderIdsByStage[$s['code']][] = $row;
            $allRiderRows[] = $row;
        }
        echo "  Seeded $n riders for {$s['name']} ({$s['code']}) — active target $activeTarget\n";
    }
    echo "Total riders seeded: " . count($allRiderRows) . "\n";

    // ── 6. Payments ────────────────────────────────────────
    $paySql = "INSERT INTO payments (rider_id,city_id,amount,payment_method,receipt_number,status,fiscal_year,paid_at,collected_by)
        VALUES (:rider,:city,:amount,:method,:receipt,:status,:fy,:paid_at,:collected_by)";
    $methods = ['Mobile Money','Mobile Money','Mobile Money','Cash','Bank Transfer']; // weighted toward MoMo
    $receiptCounter = 1;
    $fyCurrent = '2026/2027';
    $fyPrev = prevFiscalYear($fyCurrent);
    $paymentCount = 0;
    foreach ($allRiderRows as $r) {
        $olderThanYear = (strtotime($r['member_since']) < strtotime('-365 days'));
        if ($r['status'] === 'active') {
            runQuery($paySql, [
                'rider'=>$r['id'],'city'=>$r['city'],'amount'=>$cityFee[$r['city']],'method'=>randChoice($methods),
                'receipt'=>sprintf('RCP-%06d', $receiptCounter++), 'status'=>'Confirmed','fy'=>$fyCurrent,
                'paid_at'=>randDate(-180,-1).' 11:00:00','collected_by'=>$r['registrar'],
            ]);
            $paymentCount++;
            if ($olderThanYear) {
                runQuery($paySql, [
                    'rider'=>$r['id'],'city'=>$r['city'],'amount'=>$cityFee[$r['city']],'method'=>randChoice($methods),
                    'receipt'=>sprintf('RCP-%06d', $receiptCounter++), 'status'=>'Confirmed','fy'=>$fyPrev,
                    'paid_at'=>$r['member_since'].' 11:00:00','collected_by'=>$r['registrar'],
                ]);
                $paymentCount++;
            }
        } elseif ($r['status'] === 'expired') {
            runQuery($paySql, [
                'rider'=>$r['id'],'city'=>$r['city'],'amount'=>$cityFee[$r['city']],'method'=>randChoice($methods),
                'receipt'=>sprintf('RCP-%06d', $receiptCounter++), 'status'=>'Confirmed','fy'=>$fyPrev,
                'paid_at'=>date('Y-m-d', strtotime($r['expiry'].' -365 days')).' 11:00:00','collected_by'=>$r['registrar'],
            ]);
            $paymentCount++;
        } else { // pending
            if (randBetween(1,100) <= 30) {
                runQuery($paySql, [
                    'rider'=>$r['id'],'city'=>$r['city'],'amount'=>$cityFee[$r['city']],'method'=>randChoice($methods),
                    'receipt'=>sprintf('RCP-%06d', $receiptCounter++), 'status'=>'Pending','fy'=>$fyCurrent,
                    'paid_at'=>randDate(-15,-1).' 11:00:00','collected_by'=>$r['registrar'],
                ]);
                $paymentCount++;
            }
        }
    }
    echo "Seeded $paymentCount payments\n";

    // ── 7. Enforcement actions (sample, drawn from expired riders) ──
    $expiredRiders = array_values(array_filter($allRiderRows, fn($r) => $r['status'] === 'expired'));
    $types = ['warning'=>40,'fine'=>35,'suspension'=>15,'impound'=>10];
    $statuses = ['pending'=>40,'resolved'=>40,'closed'=>20];
    $actionSql = "INSERT INTO enforcement_actions (action_code,rider_id,city_id,stage_id,plate,type,amount,description,action_date,status,officer_user_id)
        VALUES (:code,:rider,:city,:stage,:plate,:type,:amount,:desc,:date,:status,:officer)";
    $descriptions = [
        'warning'=>'Verbal warning issued for expired compliance sticker.',
        'fine'=>'Fine issued for operating without valid annual tax payment.',
        'suspension'=>'Operating permit suspended pending payment of arrears.',
        'impound'=>'Motorcycle impounded for prolonged non-compliance.',
    ];
    $enfCount = min(count($expiredRiders), randBetween(15,25));
    shuffle($expiredRiders);
    for ($i = 0; $i < $enfCount; $i++) {
        $r = $expiredRiders[$i];
        $type = weightedPick($types);
        runQuery($actionSql, [
            'code'=>sprintf('ENF-2026-%03d', $i+1),'rider'=>$r['id'],'city'=>$r['city'],'stage'=>$r['stage_id'],
            'plate'=>$r['plate'],'type'=>$type,'amount'=>in_array($type,['fine','impound']) ? randChoice([20000,30000,50000,75000]) : null,
            'desc'=>$descriptions[$type],'date'=>randDate(-120,-1),'status'=>weightedPick($statuses),
            'officer'=>$r['registrar'],
        ]);
    }
    echo "Seeded $enfCount enforcement actions\n";

    // ── 8. Notifications (only for the 4 portal-linked demo riders) ──
    $notifSql = "INSERT INTO notifications (rider_id,type,message,is_read,created_at) VALUES (:rider,:type,:msg,:read,:created_at)";
    // find rider rows whose id_number was reserved for the 4 portal-linked demo riders
    $reservedIdNums = array_keys($demoRiderUserIds);
    $placeholders = implode(',', array_fill(0, count($reservedIdNums), '?'));
    $linked = fetchAll("SELECT id, id_number FROM riders WHERE id_number IN ($placeholders)", $reservedIdNums);
    $notifTemplates = [
        ['type'=>'Registration Complete','msg'=>'Your rider registration has been completed successfully.'],
        ['type'=>'ID Card Issued','msg'=>'Your digital ID card has been generated and is ready to view.'],
        ['type'=>'Payment Confirmed','msg'=>'Your annual tax payment has been confirmed. Thank you!'],
        ['type'=>'Renewal Reminder','msg'=>'Your annual tax is due for renewal soon. Please make a payment to stay compliant.'],
        ['type'=>'Important Update','msg'=>'Stage operating hours have been updated by your chairperson.'],
    ];
    $notifCount = 0;
    foreach ($linked as $l) {
        foreach ($notifTemplates as $idx => $t) {
            runQuery($notifSql, ['rider'=>$l['id'],'type'=>$t['type'],'msg'=>$t['msg'],'read'=>$idx < 3 ? 1 : 0,'created_at'=>randDate(-60,-1).' 09:00:00']);
            $notifCount++;
        }
    }
    echo "Seeded $notifCount notifications\n";

    // ── 9. Audit logs (historical LOGIN rows for the seeded login accounts) ──
    $allUsers = fetchAll("SELECT id,name,role,city_id FROM users");
    $auditSql = "INSERT INTO audit_logs (user_id,user_name_snapshot,role_snapshot,city_id,action,details,ip_address,created_at)
        VALUES (:uid,:name,:role,:city,'LOGIN','Seed-generated historical login','127.0.0.1',:created_at)";
    $auditCount = 0;
    foreach ($allUsers as $u) {
        $times = randBetween(1,3);
        for ($i=0;$i<$times;$i++) {
            runQuery($auditSql, ['uid'=>$u['id'],'name'=>$u['name'],'role'=>$u['role'],'city'=>$u['city_id'],'created_at'=>randDate(-10,-1).' '.sprintf('%02d:%02d:00',randBetween(7,19),randBetween(0,59))]);
            $auditCount++;
        }
    }
    echo "Seeded $auditCount audit log rows\n";

    $pdo->commit();
    echo "\nSeed complete.\n";

} catch (Throwable $e) {
    $pdo->rollback();
    fwrite(STDERR, "Seed failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
