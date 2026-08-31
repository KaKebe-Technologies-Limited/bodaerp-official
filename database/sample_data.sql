-- ============================================================
--  BodaERP  --  SAMPLE DATA for database `u850523537_BodaERP26`
-- ============================================================
--  Import this AFTER importing the schema file
--  (u850523537_BodaERP26.sql) into an EMPTY database.
--
--  How to import on the live server (Hostinger / phpMyAdmin):
--    1. Open phpMyAdmin  ->  select database  u850523537_BodaERP26
--    2. Import tab  ->  choose  u850523537_BodaERP26.sql   (schema)  ->  Go
--    3. Import tab  ->  choose  this file (sample_data.sql)          ->  Go
--
--  Or from CLI:
--    mysql -u USER -p u850523537_BodaERP26 < database/u850523537_BodaERP26.sql
--    mysql -u USER -p u850523537_BodaERP26 < database/sample_data.sql
--
-- ------------------------------------------------------------
--  SAMPLE LOGINS  (all passwords are case-sensitive)
-- ------------------------------------------------------------
--  ROLE          EMAIL                          PASSWORD
--  super_admin   superadmin@bodaerp.com         Admin@2026
--  city_admin    lira.admin@bodaerp.com         City@2026
--  city_admin    gulu.admin@bodaerp.com         City@2026
--  city_admin    kampala.admin@bodaerp.com      City@2026
--  chairperson   chair.railway@bodaerp.com      Chair@2026
--  chairperson   chair.gulumain@bodaerp.com     Chair@2026
--  chairperson   chair.nakasero@bodaerp.com     Chair@2026
--  rider         james.rider@bodaerp.com        Rider@2026
--  rider         grace.rider@bodaerp.com        Rider@2026
--
--  >> After first login, change these passwords in Settings. <<
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- ------------------------------------------------------------
-- 1. Super admin  (created before cities: cities.created_by -> users.id)
-- ------------------------------------------------------------
INSERT INTO `users` (`id`,`name`,`email`,`phone`,`password_hash`,`role`,`city_id`,`stage_id`,`status`,`created_at`) VALUES
(1,'Kakebe Super Admin','superadmin@bodaerp.com','+256 700 000 001','$2y$10$3X6I30k2opSowP3kdqOtXuC.fYZjE/zZmsXZPXR.Ibtad5e1LrtKG','super_admin',NULL,NULL,'active','2025-01-01 09:00:00');

-- ------------------------------------------------------------
-- 2. Cities
-- ------------------------------------------------------------
INSERT INTO `cities`
(`id`,`name`,`country`,`currency`,`logo_path`,`annual_fee`,`fiscal_year`,`id_prefix`,`status`,`contact_email`,`contact_phone`,`address`,`payment_gateway`,`sms_gateway`,`revenue_split_city`,`revenue_split_association`,`revenue_split_platform`,`compliance_target`,`grace_period_days`,`reminder_days`,`created_by`,`created_at`) VALUES
('LIR','Lira City','Uganda','UGX','/assets/images/logo.png',50000.00,'2026/2027','BODA-LIR','active','council@liracityuganda.go.ug','+256 473 420 123','Lira City Council, Parliament Avenue, Lira, Uganda','MTN MoMo','Africa''s Talking',60,26,14,70,30,14,1,'2025-01-01 09:00:00'),
('GUL','Gulu City','Uganda','UGX','/assets/images/logo.png',45000.00,'2026/2027','BODA-GUL','active','council@gulucity.go.ug','+256 471 432 456','Gulu City Council, Gulu, Uganda','Airtel Money','Africa''s Talking',55,30,15,70,30,14,1,'2025-03-01 09:00:00'),
('KLA','Kampala City','Uganda','UGX','/assets/images/logo.png',60000.00,'2026/2027','BODA-KLA','active','council@kcca.go.ug','+256 417 123 456','KCCA, City Square, Kampala, Uganda','MTN MoMo','Africa''s Talking',65,22,13,75,30,14,1,'2025-06-01 09:00:00');

-- ------------------------------------------------------------
-- 3. Stages
-- ------------------------------------------------------------
INSERT INTO `stages` (`id`,`city_id`,`code`,`name`,`location`,`route`,`chairperson_name`,`chairperson_phone`,`status`,`created_at`) VALUES
(1,'LIR','LIR-STG-001','Railway Stage','Railway Ward, Lira','Railway - Town','Ocen Patrick','+256 772 111 001','active','2025-01-15 09:00:00'),
(2,'LIR','LIR-STG-002','Market Stage','Central Market, Lira','Market - Town','Opio John','+256 772 111 002','active','2025-01-15 09:00:00'),
(3,'GUL','GUL-STG-001','Gulu Main Stage','Gulu Central','Main - Layibi','Komakech Denis','+256 772 222 001','active','2025-03-10 09:00:00'),
(4,'KLA','KLA-STG-001','Nakasero Stage','Nakasero, Kampala','Nakasero - City Square','Nakato Sarah','+256 772 333 001','active','2025-06-10 09:00:00');

-- ------------------------------------------------------------
-- 4. City admins, chairpersons, rider-linked users
-- ------------------------------------------------------------
INSERT INTO `users` (`id`,`name`,`email`,`phone`,`password_hash`,`role`,`city_id`,`stage_id`,`status`,`created_at`) VALUES
(2,'Lira City Council','lira.admin@bodaerp.com','+256 473 420 123','$2y$10$WTIAkl5szK9GaKL3zu/eGefU2AwkI5XFaAqGrD.yDzI3b777A33Q2','city_admin','LIR',NULL,'active','2025-01-02 09:00:00'),
(3,'Gulu City Council','gulu.admin@bodaerp.com','+256 471 432 456','$2y$10$WTIAkl5szK9GaKL3zu/eGefU2AwkI5XFaAqGrD.yDzI3b777A33Q2','city_admin','GUL',NULL,'active','2025-03-02 09:00:00'),
(4,'Kampala City Council','kampala.admin@bodaerp.com','+256 417 123 456','$2y$10$WTIAkl5szK9GaKL3zu/eGefU2AwkI5XFaAqGrD.yDzI3b777A33Q2','city_admin','KLA',NULL,'active','2025-06-02 09:00:00'),
(5,'Ocen Patrick','chair.railway@bodaerp.com','+256 772 111 001','$2y$10$uBrs2SttZK63SVWohYSb0.OdWxPY3fIFMIjWniqdhpP0qLr.C/PZe','chairperson','LIR',1,'active','2025-01-16 09:00:00'),
(6,'Komakech Denis','chair.gulumain@bodaerp.com','+256 772 222 001','$2y$10$uBrs2SttZK63SVWohYSb0.OdWxPY3fIFMIjWniqdhpP0qLr.C/PZe','chairperson','GUL',3,'active','2025-03-11 09:00:00'),
(7,'Nakato Sarah','chair.nakasero@bodaerp.com','+256 772 333 001','$2y$10$uBrs2SttZK63SVWohYSb0.OdWxPY3fIFMIjWniqdhpP0qLr.C/PZe','chairperson','KLA',4,'active','2025-06-11 09:00:00'),
(8,'Akello James','james.rider@bodaerp.com','+256 772 123 456','$2y$10$ByGLTF9YSM/CRJ920KYK0eAiFJl879KuRS.dQidlcif5AvjgdTAe.','rider','LIR',1,'active','2025-02-01 09:00:00'),
(9,'Aciro Grace','grace.rider@bodaerp.com','+256 772 222 456','$2y$10$ByGLTF9YSM/CRJ920KYK0eAiFJl879KuRS.dQidlcif5AvjgdTAe.','rider','GUL',3,'active','2025-03-20 09:00:00');

-- ------------------------------------------------------------
-- 5. Riders
-- ------------------------------------------------------------
INSERT INTO `riders`
(`id`,`user_id`,`city_id`,`stage_id`,`id_number`,`full_name`,`nin`,`date_of_birth`,`gender`,`marital_status`,`phone`,`email`,`physical_address`,`next_of_kin_name`,`next_of_kin_contact`,`bike_plate`,`bike_model`,`route`,`photo_path`,`status`,`member_since`,`expiry_date`,`annual_tax`,`created_by`,`created_at`) VALUES
(1,8,'LIR',1,'BODA-LIR-000101','Akello James','CM95001234AB','1992-04-11','Male','Married','+256 772 123 456','james.rider@bodaerp.com','Railway Ward, Lira','Akello Mary','+256 772 900 101','UAX 123A','Bajaj Boxer 150','Railway - Town',NULL,'active','2025-02-01','2027-02-01',50000.00,2,'2025-02-01 09:00:00'),
(2,9,'GUL',3,'BODA-GUL-000102','Aciro Grace','CM94005678CD','1994-09-02','Female','Single','+256 772 222 456','grace.rider@bodaerp.com','Gulu Central','Aciro Peter','+256 772 900 102','UBK 456C','TVS Star HLX','Main - Layibi',NULL,'active','2025-03-20','2027-03-20',45000.00,3,'2025-03-20 09:00:00'),
(3,NULL,'LIR',1,'BODA-LIR-000103','Okello Brian','CM91002233EF','1991-01-19','Male','Single','+256 772 300 103','okello.brian@example.com','Adyel Division, Lira','Okello Jane','+256 772 900 103','UAP 778D','Bajaj Boxer 150','Railway - Town',NULL,'active','2025-04-05','2027-04-05',50000.00,2,'2025-04-05 09:00:00'),
(4,NULL,'LIR',2,'BODA-LIR-000104','Adong Sarah','CM90004455GH','1990-06-30','Female','Married','+256 772 300 104','adong.sarah@example.com','Central Market, Lira','Adong Paul','+256 772 900 104','UAQ 991E','TVS Star HLX','Market - Town',NULL,'expired','2024-05-10','2026-05-10',50000.00,2,'2024-05-10 09:00:00'),
(5,NULL,'GUL',3,'BODA-GUL-000105','Otim Daniel','CM89006677IJ','1989-11-12','Male','Single','+256 772 300 105','otim.daniel@example.com','Layibi, Gulu','Otim Rose','+256 772 900 105','UBL 220F','Boxer 100','Main - Layibi',NULL,'pending','2026-08-01',NULL,45000.00,3,'2026-08-01 09:00:00'),
(6,NULL,'KLA',4,'BODA-KLA-000106','Nabirye Faith','CM93008899KL','1993-03-25','Female','Married','+256 772 300 106','nabirye.faith@example.com','Nakasero, Kampala','Nabirye Joseph','+256 772 900 106','UBA 145G','Bajaj Boxer 150','Nakasero - City Square',NULL,'active','2025-07-01','2027-07-01',60000.00,4,'2025-07-01 09:00:00'),
(7,NULL,'KLA',4,'BODA-KLA-000107','Ssali Ivan','CM92009900MN','1992-08-08','Male','Single','+256 772 300 107','ssali.ivan@example.com','Old Kampala','Ssali Grace','+256 772 900 107','UBB 662H','TVS Apache','Nakasero - City Square',NULL,'active','2025-07-15','2027-07-15',60000.00,4,'2025-07-15 09:00:00'),
(8,NULL,'LIR',2,'BODA-LIR-000108','Ejang Moses','CM88001122OP','1988-12-01','Male','Married','+256 772 300 108','ejang.moses@example.com','Ojwina, Lira','Ejang Betty','+256 772 900 108','UAR 334J','Boxer 100','Market - Town',NULL,'active','2025-05-20','2027-05-20',50000.00,2,'2025-05-20 09:00:00');

-- ------------------------------------------------------------
-- 6. Payments
-- ------------------------------------------------------------
INSERT INTO `payments`
(`id`,`rider_id`,`city_id`,`amount`,`payment_method`,`receipt_number`,`status`,`fiscal_year`,`paid_at`,`collected_by`,`created_at`) VALUES
(1,1,'LIR',50000.00,'Mobile Money','RCPT-LIR-000001','Confirmed','2026/2027','2026-02-01 10:15:00',2,'2026-02-01 10:15:00'),
(2,2,'GUL',45000.00,'Mobile Money','RCPT-GUL-000001','Confirmed','2026/2027','2026-03-20 11:00:00',3,'2026-03-20 11:00:00'),
(3,3,'LIR',50000.00,'Cash','RCPT-LIR-000002','Confirmed','2026/2027','2026-04-06 09:30:00',5,'2026-04-06 09:30:00'),
(4,4,'LIR',50000.00,'Mobile Money','RCPT-LIR-000003','Confirmed','2025/2026','2025-05-11 14:20:00',2,'2025-05-11 14:20:00'),
(5,6,'KLA',60000.00,'Bank Transfer','RCPT-KLA-000001','Confirmed','2026/2027','2026-07-02 08:45:00',4,'2026-07-02 08:45:00'),
(6,7,'KLA',60000.00,'Mobile Money','RCPT-KLA-000002','Pending','2026/2027','2026-08-20 16:10:00',7,'2026-08-20 16:10:00'),
(7,8,'LIR',50000.00,'Mobile Money','RCPT-LIR-000004','Confirmed','2026/2027','2026-05-21 12:00:00',5,'2026-05-21 12:00:00'),
(8,5,'GUL',45000.00,'Cash','RCPT-GUL-000002','Failed','2026/2027','2026-08-05 10:00:00',6,'2026-08-05 10:00:00');

-- ------------------------------------------------------------
-- 7. Notifications
-- ------------------------------------------------------------
INSERT INTO `notifications` (`id`,`rider_id`,`type`,`message`,`is_read`,`created_at`) VALUES
(1,1,'Payment Confirmed','Your annual fee payment of UGX 50,000 has been confirmed. Receipt RCPT-LIR-000001.',1,'2026-02-01 10:16:00'),
(2,1,'ID Card Issued','Your BodaERP ID card BODA-LIR-000101 is ready for collection at Railway Stage.',0,'2026-02-03 09:00:00'),
(3,2,'Registration Complete','Welcome to BodaERP. Your registration under Gulu Main Stage is complete.',1,'2026-03-20 11:05:00'),
(4,4,'Renewal Reminder','Your permit expired on 2026-05-10. Please renew to avoid enforcement action.',0,'2026-06-01 08:00:00'),
(5,6,'Payment Confirmed','Your annual fee payment of UGX 60,000 has been confirmed. Receipt RCPT-KLA-000001.',0,'2026-07-02 08:46:00'),
(6,7,'Important Update','Your payment RCPT-KLA-000002 is pending confirmation from the collector.',0,'2026-08-20 16:12:00');

-- ------------------------------------------------------------
-- 8. Enforcement actions
-- ------------------------------------------------------------
INSERT INTO `enforcement_actions`
(`id`,`action_code`,`rider_id`,`city_id`,`stage_id`,`plate`,`type`,`amount`,`description`,`action_date`,`status`,`officer_user_id`,`created_at`) VALUES
(1,'ENF-LIR-000001',4,'LIR',2,'UAQ 991E','warning',NULL,'Operating with an expired permit. Verbal warning issued.','2026-06-15','resolved',5,'2026-06-15 09:00:00'),
(2,'ENF-LIR-000002',4,'LIR',2,'UAQ 991E','fine',20000.00,'Continued operation without renewal after warning.','2026-07-20','pending',2,'2026-07-20 10:30:00'),
(3,'ENF-KLA-000001',7,'KLA',4,'UBB 662H','warning',NULL,'Riding without visible ID card.','2026-08-10','closed',7,'2026-08-10 15:00:00'),
(4,'ENF-GUL-000001',5,'GUL',3,'UBL 220F','impound',50000.00,'Unregistered rider operating on Layibi route. Bike impounded pending registration.','2026-08-18','pending',6,'2026-08-18 11:00:00');

-- ------------------------------------------------------------
-- 9. Fix AUTO_INCREMENT counters
-- ------------------------------------------------------------
ALTER TABLE `users`               AUTO_INCREMENT = 10;
ALTER TABLE `stages`              AUTO_INCREMENT = 5;
ALTER TABLE `riders`              AUTO_INCREMENT = 9;
ALTER TABLE `payments`            AUTO_INCREMENT = 9;
ALTER TABLE `notifications`       AUTO_INCREMENT = 7;
ALTER TABLE `enforcement_actions` AUTO_INCREMENT = 5;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  End of sample data
-- ============================================================
