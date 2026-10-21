<?php
// Soroti City (Teso sub-region) — starter data. Idempotent: anything that already exists is left untouched.
return [
    'title'       => 'Soroti City — starter data',
    'description' => 'Adds Soroti City with 4 stages, a City Council admin, 4 stage chairpersons, 12 riders (5 with rider logins), '
                   . 'payments, 2 enforcement actions and notifications. Dates are relative to today so the data always looks current. '
                   . 'Safe to re-apply — existing records are never overwritten.',
    'hint' => 'Default passwords — City Council: Soroti@2026 · Chairpersons: Chair@2026 · Riders: Rider@2026. Ask users to change them after first sign-in.',
    'apply' => function (): array {
        $cityId = 'SOR';
        $today  = new DateTimeImmutable('today');
        $d      = fn(int $days) => $today->modify(sprintf('%+d days', $days))->format('Y-m-d');
        $count  = ['city' => 0, 'stages' => 0, 'users' => 0, 'riders' => 0, 'payments' => 0, 'enforcement' => 0, 'notifications' => 0];
        $creds  = [];

        // ---- city (revenue split taken from the platform defaults when they are valid) ----
        $fee = 45000;
        if (!(int) fetchValue("SELECT COUNT(*) FROM cities WHERE id = ?", [$cityId])) {
            $ps = fetchOne("SELECT * FROM platform_settings WHERE id = 1") ?: [];
            $sp = [(int) ($ps['default_split_city'] ?? 55), (int) ($ps['default_split_association'] ?? 26),
                   (int) ($ps['default_split_platform'] ?? 9), (int) ($ps['default_split_government'] ?? 10)];
            if (array_sum($sp) !== 100) $sp = [55, 26, 9, 10];
            $superId = fetchValue("SELECT id FROM users WHERE role = 'super_admin' AND deleted_at IS NULL ORDER BY id LIMIT 1");
            runQuery("INSERT INTO cities (id,name,country,currency,logo_path,annual_fee,fiscal_year,id_prefix,status,contact_email,contact_phone,address,
                        payment_gateway,sms_gateway,revenue_split_city,revenue_split_association,revenue_split_platform,revenue_split_government,compliance_target,created_by)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$cityId, 'Soroti City', 'Uganda', 'UGX', '/assets/images/logo.png', $fee, '2026/2027', 'BODA-SOR', 'active',
                 'council@soroticity.go.ug', '+256 454 461 000', 'Soroti City Council, Eastern Division, Soroti, Teso Sub-region, Uganda',
                 'Both', "Africa's Talking", $sp[0], $sp[1], $sp[2], $sp[3], 70, $superId ?: null])->close();
            $count['city'] = 1;
        } else {
            $fee = (float) fetchValue("SELECT annual_fee FROM cities WHERE id = ?", [$cityId]);
        }

        // ---- stages ----
        $stageDefs = [
            ['SOR-STG-001', 'Central Market Stage', 'Soroti Central Market, Eastern Division', 'Market - Town', 'Emuron Peter', '+256 772 500 101'],
            ['SOR-STG-002', 'Railway Stage', 'Soroti Railway Station, Western Division', 'Railway - Hospital Road', 'Apolot Jane', '+256 772 500 102'],
            ['SOR-STG-003', 'Hospital Stage', 'Soroti Regional Referral Hospital', 'Hospital - Town', 'Okiror David', '+256 772 500 103'],
            ['SOR-STG-004', 'Arapai Stage', 'Arapai, Soroti University Road', 'Arapai - Town', 'Among Rose', '+256 772 500 104'],
        ];
        $stageId = [];
        foreach ($stageDefs as $i => [$code, $name, $loc, $route, $chair, $phone]) {
            $id = fetchValue("SELECT id FROM stages WHERE code = ?", [$code]);
            if (!$id) {
                runQuery("INSERT INTO stages (city_id,code,name,location,route,chairperson_name,chairperson_phone,status) VALUES (?,?,?,?,?,?,?,'active')",
                    [$cityId, $code, $name, $loc, $route, $chair, $phone])->close();
                $id = db()->insert_id; $count['stages']++;
            }
            $stageId[$i + 1] = (int) $id;
        }

        // ---- users ----
        $mkUser = function (string $name, string $email, string $phone, string $password, string $role, ?int $stage) use ($cityId, &$count, &$creds): int {
            $id = fetchValue("SELECT id FROM users WHERE email = ?", [$email]);
            $isNew = !$id;
            if ($isNew) {
                runQuery("INSERT INTO users (name,email,phone,password_hash,role,city_id,stage_id,status) VALUES (?,?,?,?,?,?,?,'active')",
                    [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $role, $cityId, $stage])->close();
                $id = db()->insert_id; $count['users']++;
            }
            $creds[] = ['role' => $role, 'name' => $name, 'email' => $email, 'password' => $isNew ? $password : null, 'new' => $isNew];
            return (int) $id;
        };
        $adminId = $mkUser('Soroti City Council', 'soroti.admin@bodaerp.com', '+256 454 461 000', 'Soroti@2026', 'city_admin', null);
        $chairId = [
            1 => $mkUser('Emuron Peter', 'chair.sorotimarket@bodaerp.com',   '+256 772 500 101', 'Chair@2026', 'chairperson', $stageId[1]),
            2 => $mkUser('Apolot Jane',  'chair.sorotirailway@bodaerp.com',  '+256 772 500 102', 'Chair@2026', 'chairperson', $stageId[2]),
            3 => $mkUser('Okiror David', 'chair.sorotihospital@bodaerp.com', '+256 772 500 103', 'Chair@2026', 'chairperson', $stageId[3]),
            4 => $mkUser('Among Rose',   'chair.arapai@bodaerp.com',         '+256 772 500 104', 'Chair@2026', 'chairperson', $stageId[4]),
        ];

        // ---- riders: name, gender, dob, marital, stage, plate, model, state, days since registration, rider login e-mail ----
        $riders = [
            ['EKURU SAMUEL',  'Male',   '1991-03-14', 'Married', 1, 'UFB 214K', 'Bajaj Boxer 150',  'active',  120, 'ekuru.samuel@bodaerp.com'],
            ['AMUGE SARAH',   'Female', '1995-07-22', 'Single',  1, 'UFC 508M', 'TVS Star HLX 125', 'active',  95,  'amuge.sarah@bodaerp.com'],
            ['OPOLOT ISAAC',  'Male',   '1989-11-05', 'Married', 2, 'UEY 733T', 'Bajaj Boxer 100',  'soon',    355, 'opolot.isaac@bodaerp.com'],
            ['ADEKE MARY',    'Female', '1993-04-18', 'Married', 3, 'UFA 119R', 'Honda CG 125',     'expired', 410, 'adeke.mary@bodaerp.com'],
            ['ETYANG MOSES',  'Male',   '1990-09-30', 'Single',  4, 'UFD 842P', 'Bajaj Boxer 150',  'pending', 2,   'etyang.moses@bodaerp.com'],
            ['EBONG FRANCIS', 'Male',   '1987-02-09', 'Married', 1, 'UEZ 305H', 'TVS HLX 150',      'active',  70,  null],
            ['APOLOT GRACE',  'Female', '1996-12-01', 'Single',  2, 'UFE 671G', 'Honda CG 125',     'active',  45,  null],
            ['OKIROR DENIS',  'Male',   '1992-06-27', 'Single',  2, 'UFA 902B', 'Bajaj Boxer 150',  'expired', 440, null],
            ['EJALU BETTY',   'Female', '1994-08-16', 'Married', 3, 'UFB 447N', 'TVS Star HLX 125', 'active',  200, null],
            ['EDYEGU PAUL',   'Male',   '1985-01-23', 'Married', 3, 'UEX 218W', 'Bajaj Boxer 100',  'active',  30,  null],
            ['ATIM JOYCE',    'Female', '1998-10-11', 'Single',  4, 'UFD 356S', 'Honda CG 125',     'pending', 1,   null],
            ['EPURU JOSEPH',  'Male',   '1988-05-06', 'Married', 4, 'UFC 690A', 'Bajaj Boxer 150',  'active',  15,  null],
        ];
        $methods = ['Mobile Money', 'Cash', 'Mobile Money', 'Bank Transfer'];
        $riderIdByName = [];
        foreach ($riders as $i => [$name, $gender, $dob, $marital, $st, $plate, $model, $state, $ago, $loginEmail]) {
            $nin = 'CM' . strtoupper(substr(md5('SOR-NIN-' . $name), 0, 10));
            $existing = fetchValue("SELECT id FROM riders WHERE nin = ?", [$nin]);
            if ($existing) { $riderIdByName[$name] = (int) $existing; continue; }

            $first  = ucwords(strtolower(explode(' ', $name)[0]));
            $phone  = '+256 77' . (2 + ($i % 6)) . ' ' . (500200 + $i * 137);
            $userId = $loginEmail ? $mkUser(ucwords(strtolower($name)), $loginEmail, $phone, 'Rider@2026', 'rider', $stageId[$st]) : null;

            $since  = $d(-$ago);
            $status = $state === 'pending' ? 'pending' : ($state === 'expired' ? 'expired' : 'active');
            if ($state === 'pending')      $expiry = null;
            elseif ($state === 'soon')     $expiry = $d(10);                    // expires in 10 days: shows in Expiry Alerts and triggers a reminder
            elseif ($state === 'expired')  $expiry = $d(-random_int(40, 90));   // permit lapsed 40-90 days ago
            else                           $expiry = $d(-$ago + 365);

            runQuery("INSERT INTO riders (user_id,city_id,stage_id,id_number,full_name,nin,date_of_birth,gender,marital_status,phone,physical_address,
                        next_of_kin_name,next_of_kin_contact,bike_plate,bike_model,route,status,member_since,expiry_date,annual_tax,created_by)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$userId, $cityId, $stageId[$st], 'TMP-' . uniqid(), $name, $nin, $dob, $gender, $marital, $phone,
                 $stageDefs[$st - 1][2], 'Family of ' . $first, '+256 703 ' . (600100 + $i * 211),
                 $plate, $model, $stageDefs[$st - 1][3], $status, $since, $expiry, $fee, $chairId[$st]])->close();
            $rid = (int) db()->insert_id;
            runQuery("UPDATE riders SET id_number = ? WHERE id = ?", ['BODA-SOR-' . str_pad((string) $rid, 6, '0', STR_PAD_LEFT), $rid])->close();
            $riderIdByName[$name] = $rid; $count['riders']++;

            if ($state !== 'pending') {   // payment on the registration date
                $fy = $state === 'expired' ? '2025/2026' : '2026/2027';
                $paidAt = $since . ' 09:' . str_pad((string) (10 + $i * 4), 2, '0', STR_PAD_LEFT) . ':00';
                runQuery("INSERT INTO payments (rider_id,city_id,amount,payment_method,receipt_number,status,fiscal_year,paid_at,collected_by)
                          VALUES (?,?,?,?,?,'Confirmed',?,?,?)",
                    [$rid, $cityId, $fee, $methods[$i % 4], 'TMP-' . uniqid(), $fy, $paidAt, $chairId[$st]])->close();
                $pid = (int) db()->insert_id;
                runQuery("UPDATE payments SET receipt_number = ? WHERE id = ?", ['RCP-' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT), $pid])->close();
                $count['payments']++;
            }
            if ($userId) {   // notifications for riders who have logins
                if ($state === 'pending')      $notes = [['Registration Complete', 'Welcome to BodaERP. Your registration is complete — pay your annual tax to activate your permit.']];
                elseif ($state === 'expired')  $notes = [['Renewal Reminder', 'Your permit has expired. Please renew to avoid enforcement action.']];
                else $notes = [['Registration Complete', 'Welcome to BodaERP. Your registration is complete.'],
                               ['Payment Confirmed', 'Your annual tax payment has been confirmed. Thank you!'],
                               ['ID Card Issued', 'Your BodaERP ID card is ready for collection at your stage.']];
                foreach ($notes as [$type, $msg]) {
                    runQuery("INSERT INTO notifications (rider_id,type,message) VALUES (?,?,?)", [$rid, $type, $msg])->close();
                    $count['notifications']++;
                }
            }
        }

        // ---- two enforcement actions against a lapsed rider ----
        $offender = $riderIdByName['OKIROR DENIS'] ?? null;
        if ($offender) {
            $actions = [['ENF-SOR-0001', 'warning', null, 'Operating with an expired permit. Verbal warning issued.', -40, 'resolved'],
                        ['ENF-SOR-0002', 'fine', 15000, 'Continued operation without renewing the permit after a warning.', -12, 'pending']];
            foreach ($actions as [$code, $type, $amt, $desc, $off, $stt]) {
                if (fetchValue("SELECT COUNT(*) FROM enforcement_actions WHERE action_code = ?", [$code])) continue;
                runQuery("INSERT INTO enforcement_actions (action_code,rider_id,city_id,stage_id,plate,type,amount,description,action_date,status,officer_user_id)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?)", [$code, $offender, $cityId, $stageId[2], 'UFA 902B', $type, $amt, $desc, $d($off), $stt, $adminId])->close();
                $count['enforcement']++;
            }
        }

        $summary = sprintf('Soroti City: %d city, %d stages, %d users, %d riders, %d payments, %d enforcement, %d notifications added%s.',
            $count['city'], $count['stages'], $count['users'], $count['riders'], $count['payments'], $count['enforcement'], $count['notifications'],
            array_sum($count) === 0 ? ' (everything already existed)' : '');
        return ['summary' => $summary, 'credentials' => $creds];
    },
];
