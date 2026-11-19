-- =====================================================================
--  TeekaCard - Vaccination Management System
--  File    : database/vaccination_db.sql
--  Purpose : Creates the database, all tables and demo data.
--  Import  : phpMyAdmin > Import > choose this file > Go
--            (or)  mysql -u root -p < database/vaccination_db.sql
--
--  NOTE: Demo dates are written relative to CURDATE(), so the sample
--  children, bookings and "today's appointments" always look current
--  on the day you import this file.
-- =====================================================================

SET NAMES utf8mb4;
-- Demo dates use Pakistan time, the same time zone as the application
SET time_zone = '+05:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `vaccination_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `vaccination_db`;

-- Drop old tables so the script can be re-imported safely
DROP TABLE IF EXISTS `appointment_vaccines`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `vaccination_history`;
DROP TABLE IF EXISTS `children`;
DROP TABLE IF EXISTS `vaccines`;
DROP TABLE IF EXISTS `hospitals`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `contact_messages`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. admins : system administrators
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash (password_hash)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. parents : parents / guardians who register their children
-- ---------------------------------------------------------------------
CREATE TABLE `parents` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(20)  NOT NULL,
  `cnic`       VARCHAR(15)  NULL DEFAULT NULL,
  `city`       VARCHAR(60)  NOT NULL,
  `address`    VARCHAR(255) NULL DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `status`     ENUM('Active','Blocked') NOT NULL DEFAULT 'Active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_email` (`email`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. hospitals : vaccination centres (self-registered or added by admin)
--    Self-registered hospitals stay 'Pending' until the admin approves.
-- ---------------------------------------------------------------------
CREATE TABLE `hospitals` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `phone`      VARCHAR(20)  NOT NULL,
  `city`       VARCHAR(60)  NOT NULL,
  `address`    VARCHAR(255) NOT NULL,
  `location`   VARCHAR(255) NULL DEFAULT NULL COMMENT 'Area / landmark / map link',
  `timings`    VARCHAR(100) NULL DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `status`     ENUM('Pending','Active','Inactive') NOT NULL DEFAULT 'Pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hospital_email` (`email`),
  KEY `idx_hospital_city` (`city`),
  KEY `idx_hospital_status` (`status`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. children : each child belongs to one parent
-- ---------------------------------------------------------------------
CREATE TABLE `children` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`     INT UNSIGNED NOT NULL,
  `name`          VARCHAR(100) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender`        ENUM('Male','Female') NOT NULL,
  `blood_group`   VARCHAR(5) NULL DEFAULT NULL,
  `birth_weight`  DECIMAL(4,2) NULL DEFAULT NULL COMMENT 'kg',
  `notes`         VARCHAR(255) NULL DEFAULT NULL COMMENT 'allergies / medical notes',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_child_parent` (`parent_id`),
  CONSTRAINT `fk_child_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. vaccines : vaccine catalogue + recommended age + availability
--    age_days = recommended age in days after birth (used to work out
--    every child's due dates automatically).
-- ---------------------------------------------------------------------
CREATE TABLE `vaccines` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `short_name`       VARCHAR(20)  NOT NULL,
  `protects_against` VARCHAR(255) NOT NULL,
  `age_days`         INT UNSIGNED NOT NULL DEFAULT 0,
  `age_label`        VARCHAR(30)  NOT NULL,
  `route`            VARCHAR(50)  NULL DEFAULT NULL,
  `description`      TEXT NULL,
  `status`           ENUM('Available','Unavailable') NOT NULL DEFAULT 'Available',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vaccine_short` (`short_name`),
  KEY `idx_vaccine_age` (`age_days`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. appointments : a booking request = one hospital visit
--    request_status     -> handled by parent (cancel) and admin (approve/reject)
--    vaccination_status -> handled by hospital after the visit
-- ---------------------------------------------------------------------
CREATE TABLE `appointments` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`          INT UNSIGNED NOT NULL,
  `child_id`           INT UNSIGNED NOT NULL,
  `hospital_id`        INT UNSIGNED NOT NULL,
  `appointment_date`   DATE NOT NULL,
  `appointment_time`   TIME NOT NULL,
  `parent_note`        VARCHAR(255) NULL DEFAULT NULL,
  `request_status`     ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_remark`       VARCHAR(255) NULL DEFAULT NULL,
  `decided_at`         DATETIME NULL DEFAULT NULL,
  `vaccination_status` ENUM('Pending','Vaccinated','Partially Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  `hospital_remark`    VARCHAR(255) NULL DEFAULT NULL,
  `completed_at`       DATETIME NULL DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appt_date` (`appointment_date`),
  KEY `idx_appt_request` (`request_status`),
  KEY `idx_appt_vacc` (`vaccination_status`),
  CONSTRAINT `fk_appt_parent`   FOREIGN KEY (`parent_id`)   REFERENCES `parents` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_appt_child`    FOREIGN KEY (`child_id`)    REFERENCES `children` (`id`)  ON DELETE CASCADE,
  -- RESTRICT keeps vaccination records safe: a hospital with bookings
  -- cannot be deleted by mistake (the admin can deactivate it instead).
  CONSTRAINT `fk_appt_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. appointment_vaccines : vaccines requested in an appointment and
--    the per-vaccine result entered by the hospital.
-- ---------------------------------------------------------------------
CREATE TABLE `appointment_vaccines` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NOT NULL,
  `vaccine_id`     INT UNSIGNED NOT NULL,
  `status`         ENUM('Pending','Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  `given_at`       DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_vaccine` (`appointment_id`, `vaccine_id`),
  KEY `idx_av_status` (`status`),
  CONSTRAINT `fk_av_appt`    FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_av_vaccine` FOREIGN KEY (`vaccine_id`)     REFERENCES `vaccines` (`id`)     ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. vaccination_history : doses given BEFORE joining the system
--    (recorded by the parent from the child's paper card).
-- ---------------------------------------------------------------------
CREATE TABLE `vaccination_history` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `child_id`   INT UNSIGNED NOT NULL,
  `vaccine_id` INT UNSIGNED NOT NULL,
  `given_on`   DATE NOT NULL,
  `place`      VARCHAR(150) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_history_dose` (`child_id`, `vaccine_id`),
  CONSTRAINT `fk_hist_child`   FOREIGN KEY (`child_id`)   REFERENCES `children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. notifications : dashboard notifications for every role
--    user_id = 0 with user_role = 'admin' means "all admins".
-- ---------------------------------------------------------------------
CREATE TABLE `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_role`  ENUM('admin','parent','hospital') NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `title`      VARCHAR(150) NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `link`       VARCHAR(255) NULL DEFAULT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notify_user` (`user_role`, `user_id`, `is_read`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. contact_messages : messages sent from the public contact page
-- ---------------------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `subject`    VARCHAR(150) NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;


-- =====================================================================
--  DEMO DATA
--  Admin    : admin@teekacard.pk    / Admin@123
--  Parent   : parent@teekacard.pk   / Parent@123
--  Hospital : hospital@teekacard.pk / Hospital@123
--  (all other demo parents use Parent@123, hospitals use Hospital@123)
-- =====================================================================

INSERT INTO `admins` (`id`, `name`, `email`, `password`) VALUES
(1, 'System Administrator', 'admin@teekacard.pk', '$2y$10$uctFu04orS/N9e8ZuKadC.dUz9c9iyuK6qpnFbGKc1bBgPhK4J1Km');

INSERT INTO `parents` (`id`, `name`, `email`, `phone`, `cnic`, `city`, `address`, `password`, `status`, `created_at`) VALUES
(1, 'Ayesha Khan',  'parent@teekacard.pk',     '0300-1234567', '42101-1234567-2', 'Karachi', 'House 14, Block 13-D, Gulshan-e-Iqbal', '$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2', 'Active', NOW() - INTERVAL 112 DAY),
(2, 'Bilal Ahmed',  'bilal.ahmed@example.com', '0321-1234567', '42201-7654321-1', 'Karachi', 'Flat 3B, Block H, North Nazimabad',     '$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2', 'Active', NOW() - INTERVAL 282 DAY),
(3, 'Sana Tariq',   'sana.tariq@example.com',  '0333-1234567', NULL,              'Karachi', 'Street 5, Block 2, Clifton',             '$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2', 'Active', NOW() - INTERVAL 40 DAY),
(4, 'Usman Raza',   'usman.raza@example.com',  '0345-1234567', '42301-1112223-5', 'Karachi', 'House 88, Sector 11-A, North Karachi',   '$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2', 'Active', NOW() - INTERVAL 472 DAY);

INSERT INTO `hospitals` (`id`, `name`, `email`, `phone`, `city`, `address`, `location`, `timings`, `password`, `status`, `created_at`) VALUES
(1, 'Crescent Children''s Hospital', 'hospital@teekacard.pk',  '021-34980011', 'Karachi',   'Plot 21, Block 13-D, Gulshan-e-Iqbal', 'Near Maskan Chowrangi',       'Mon to Sat, 9:00 AM to 5:00 PM', '$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO', 'Active',  NOW() - INTERVAL 500 DAY),
(2, 'Nazimabad Family Clinic',       'nazimabad@example.com',  '021-36620044', 'Karachi',   'B-12, Block H, North Nazimabad',       'Opposite Hydari Market',      'Mon to Sat, 10:00 AM to 6:00 PM', '$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO', 'Active', NOW() - INTERVAL 500 DAY),
(3, 'Seaview Medical Centre',        'seaview@example.com',    '021-35870077', 'Karachi',   '7-C, Block 5, Clifton',                'Near Abdullah Shah Ghazi Road','Daily, 8:00 AM to 8:00 PM',       '$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO', 'Active',  NOW() - INTERVAL 500 DAY),
(4, 'Mall Road Health Centre',       'mallroad@example.com',   '042-37320099', 'Lahore',    '41 Mall Road, near Regal Chowk',       'Regal Chowk',                 'Mon to Fri, 9:00 AM to 4:00 PM',  '$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO', 'Active',  NOW() - INTERVAL 30 DAY),
(5, 'Kotri Mother & Child Centre',   'kotri.mcc@example.com',  '022-38710022', 'Hyderabad', 'Main Kotri Road, Unit 4',              'Near Kotri Barrage',          'Mon to Sat, 9:00 AM to 3:00 PM',  '$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO', 'Pending', NOW() - INTERVAL 1 DAY);

-- Routine childhood schedule (modelled on Pakistan's EPI schedule: 6 visits)
INSERT INTO `vaccines` (`id`, `name`, `short_name`, `protects_against`, `age_days`, `age_label`, `route`, `description`, `status`) VALUES
(1,  'BCG',                                  'BCG',     'Childhood tuberculosis',                                  0,   'At birth',  'Injection (left upper arm)', 'Given as soon as possible after birth. A small scar at the injection site is normal.', 'Available'),
(2,  'Oral Polio Vaccine (birth dose)',      'OPV-0',   'Poliomyelitis',                                           0,   'At birth',  'Oral drops',                 'Birth dose of oral polio drops.', 'Available'),
(3,  'Hepatitis B (birth dose)',             'HepB-0',  'Hepatitis B',                                             0,   'At birth',  'Injection (thigh)',          'Best given within 24 hours of birth.', 'Available'),
(4,  'Oral Polio Vaccine (dose 1)',          'OPV-1',   'Poliomyelitis',                                           42,  '6 weeks',   'Oral drops',                 NULL, 'Available'),
(5,  'Pentavalent (dose 1)',                 'Penta-1', 'Diphtheria, pertussis, tetanus, hepatitis B, Hib',        42,  '6 weeks',   'Injection (thigh)',          'Five-in-one vaccine.', 'Available'),
(6,  'Pneumococcal conjugate (dose 1)',      'PCV-1',   'Pneumonia and meningitis (pneumococcal)',                 42,  '6 weeks',   'Injection (thigh)',          NULL, 'Available'),
(7,  'Rotavirus (dose 1)',                   'Rota-1',  'Severe diarrhoea caused by rotavirus',                    42,  '6 weeks',   'Oral drops',                 NULL, 'Available'),
(8,  'Oral Polio Vaccine (dose 2)',          'OPV-2',   'Poliomyelitis',                                           70,  '10 weeks',  'Oral drops',                 NULL, 'Available'),
(9,  'Pentavalent (dose 2)',                 'Penta-2', 'Diphtheria, pertussis, tetanus, hepatitis B, Hib',        70,  '10 weeks',  'Injection (thigh)',          NULL, 'Available'),
(10, 'Pneumococcal conjugate (dose 2)',      'PCV-2',   'Pneumonia and meningitis (pneumococcal)',                 70,  '10 weeks',  'Injection (thigh)',          NULL, 'Available'),
(11, 'Rotavirus (dose 2)',                   'Rota-2',  'Severe diarrhoea caused by rotavirus',                    70,  '10 weeks',  'Oral drops',                 NULL, 'Available'),
(12, 'Oral Polio Vaccine (dose 3)',          'OPV-3',   'Poliomyelitis',                                           98,  '14 weeks',  'Oral drops',                 NULL, 'Available'),
(13, 'Pentavalent (dose 3)',                 'Penta-3', 'Diphtheria, pertussis, tetanus, hepatitis B, Hib',        98,  '14 weeks',  'Injection (thigh)',          NULL, 'Available'),
(14, 'Pneumococcal conjugate (dose 3)',      'PCV-3',   'Pneumonia and meningitis (pneumococcal)',                 98,  '14 weeks',  'Injection (thigh)',          NULL, 'Available'),
(15, 'Inactivated Polio Vaccine (dose 1)',   'IPV-1',   'Poliomyelitis',                                           98,  '14 weeks',  'Injection (thigh)',          NULL, 'Available'),
(16, 'Measles-Rubella (dose 1)',             'MR-1',    'Measles and rubella',                                     270, '9 months',  'Injection (upper arm)',      NULL, 'Available'),
(17, 'Typhoid Conjugate Vaccine',            'TCV',     'Typhoid fever',                                           270, '9 months',  'Injection (thigh)',          'Single dose.', 'Unavailable'),
(18, 'Inactivated Polio Vaccine (dose 2)',   'IPV-2',   'Poliomyelitis',                                           270, '9 months',  'Injection (thigh)',          NULL, 'Available'),
(19, 'Measles-Rubella (dose 2)',             'MR-2',    'Measles and rubella',                                     450, '15 months', 'Injection (upper arm)',      'Completes the routine schedule.', 'Available');

-- Children (dates of birth relative to the import date)
INSERT INTO `children` (`id`, `parent_id`, `name`, `date_of_birth`, `gender`, `blood_group`, `birth_weight`, `notes`) VALUES
(1, 1, 'Hania Khan',   CURDATE() - INTERVAL 110 DAY, 'Female', 'O+', 3.10, NULL),
(2, 1, 'Zayan Khan',   CURDATE() - INTERVAL 38 DAY,  'Male',   'B+', 2.90, 'Mild jaundice at birth, resolved'),
(3, 2, 'Inaya Bilal',  CURDATE() - INTERVAL 280 DAY, 'Female', 'A+', 3.30, NULL),
(4, 3, 'Rayan Tariq',  CURDATE() - INTERVAL 75 DAY,  'Male',   NULL, 3.00, NULL),
(5, 4, 'Mahnoor Raza', CURDATE() - INTERVAL 470 DAY, 'Female', 'AB+', 2.80, 'Allergic to eggs');

-- Appointments (one row per hospital visit)
INSERT INTO `appointments` (`id`, `parent_id`, `child_id`, `hospital_id`, `appointment_date`, `appointment_time`, `parent_note`, `request_status`, `admin_remark`, `decided_at`, `vaccination_status`, `hospital_remark`, `completed_at`, `created_at`) VALUES
-- Hania (child 1) : three visits done, 14-week visit is TODAY
(1,  1, 1, 1, CURDATE() - INTERVAL 110 DAY, '10:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 111 DAY, 'Vaccinated', 'Birth doses given.', (CURDATE() - INTERVAL 110 DAY) + INTERVAL 10 HOUR, NOW() - INTERVAL 112 DAY),
(2,  1, 1, 1, CURDATE() - INTERVAL 68 DAY,  '10:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 70 DAY,  'Vaccinated', NULL, (CURDATE() - INTERVAL 68 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 72 DAY),
(3,  1, 1, 1, CURDATE() - INTERVAL 40 DAY,  '11:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 42 DAY,  'Vaccinated', NULL, (CURDATE() - INTERVAL 40 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 44 DAY),
(4,  1, 1, 1, CURDATE(),                    '11:30:00', 'She had a mild cold last week.', 'Approved', 'Please bring the EPI card.', NOW() - INTERVAL 3 DAY, 'Pending', NULL, NULL, NOW() - INTERVAL 5 DAY),
-- Zayan (child 2) : birth doses done, 6-week request waiting for admin
(5,  1, 2, 1, CURDATE() - INTERVAL 38 DAY,  '09:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 39 DAY,  'Vaccinated', NULL, (CURDATE() - INTERVAL 38 DAY) + INTERVAL 10 HOUR, NOW() - INTERVAL 40 DAY),
(6,  1, 2, 1, CURDATE() + INTERVAL 4 DAY,   '10:00:00', 'Morning slot preferred.', 'Pending', NULL, NULL, 'Pending', NULL, NULL, NOW() - INTERVAL 1 DAY),
-- Inaya (child 3) : four visits done, 9-month visit overdue and not booked
(7,  2, 3, 2, CURDATE() - INTERVAL 280 DAY, '12:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 281 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 280 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 282 DAY),
(8,  2, 3, 2, CURDATE() - INTERVAL 238 DAY, '12:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 240 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 238 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 242 DAY),
(9,  2, 3, 2, CURDATE() - INTERVAL 210 DAY, '12:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 212 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 210 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 214 DAY),
(10, 2, 3, 2, CURDATE() - INTERVAL 182 DAY, '12:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 184 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 182 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 186 DAY),
-- Rayan (child 4) : 6-week visit partly done (Rota-1 missed), next visit tomorrow
(11, 3, 4, 3, CURDATE() - INTERVAL 33 DAY,  '16:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 35 DAY,  'Partially Vaccinated', 'Rotavirus vaccine was out of stock on the day.', (CURDATE() - INTERVAL 33 DAY) + INTERVAL 16 HOUR, NOW() - INTERVAL 36 DAY),
(12, 3, 4, 3, CURDATE() + INTERVAL 1 DAY,   '10:00:00', NULL, 'Approved', 'Rota-1 catch-up dose added.', NOW() - INTERVAL 1 DAY, 'Pending', NULL, NULL, NOW() - INTERVAL 2 DAY),
-- Mahnoor (child 5) : five visits done, one rejected + one missed + new request
(13, 4, 5, 3, CURDATE() - INTERVAL 470 DAY, '11:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 471 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 470 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 472 DAY),
(14, 4, 5, 3, CURDATE() - INTERVAL 428 DAY, '11:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 430 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 428 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 432 DAY),
(15, 4, 5, 3, CURDATE() - INTERVAL 400 DAY, '11:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 402 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 400 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 404 DAY),
(16, 4, 5, 3, CURDATE() - INTERVAL 372 DAY, '11:30:00', NULL, 'Approved', NULL, NOW() - INTERVAL 374 DAY, 'Vaccinated', NULL, (CURDATE() - INTERVAL 372 DAY) + INTERVAL 11 HOUR, NOW() - INTERVAL 376 DAY),
(17, 4, 5, 3, CURDATE() - INTERVAL 200 DAY, '12:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 202 DAY, 'Vaccinated', 'TCV given from last available stock.', (CURDATE() - INTERVAL 200 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 204 DAY),
(18, 4, 5, 2, CURDATE() - INTERVAL 18 DAY,  '12:00:00', NULL, 'Rejected', 'The clinic is closed on the selected date. Please choose another date.', NOW() - INTERVAL 19 DAY, 'Pending', NULL, NULL, NOW() - INTERVAL 20 DAY),
(19, 4, 5, 1, CURDATE() - INTERVAL 15 DAY,  '12:00:00', NULL, 'Approved', NULL, NOW() - INTERVAL 17 DAY, 'Not Vaccinated', 'Child had high fever. Advised to return after recovery.', (CURDATE() - INTERVAL 15 DAY) + INTERVAL 12 HOUR, NOW() - INTERVAL 18 DAY),
(20, 4, 5, 1, CURDATE() + INTERVAL 2 DAY,   '12:30:00', 'Fever is gone now.', 'Pending', NULL, NULL, 'Pending', NULL, NULL, NOW() - INTERVAL 6 HOUR);

-- Vaccines inside each appointment (with per-vaccine result)
INSERT INTO `appointment_vaccines` (`appointment_id`, `vaccine_id`, `status`, `given_at`) VALUES
(1, 1, 'Vaccinated', (CURDATE() - INTERVAL 110 DAY) + INTERVAL 10 HOUR),
(1, 2, 'Vaccinated', (CURDATE() - INTERVAL 110 DAY) + INTERVAL 10 HOUR),
(1, 3, 'Vaccinated', (CURDATE() - INTERVAL 110 DAY) + INTERVAL 10 HOUR),
(2, 4, 'Vaccinated', (CURDATE() - INTERVAL 68 DAY) + INTERVAL 11 HOUR),
(2, 5, 'Vaccinated', (CURDATE() - INTERVAL 68 DAY) + INTERVAL 11 HOUR),
(2, 6, 'Vaccinated', (CURDATE() - INTERVAL 68 DAY) + INTERVAL 11 HOUR),
(2, 7, 'Vaccinated', (CURDATE() - INTERVAL 68 DAY) + INTERVAL 11 HOUR),
(3, 8, 'Vaccinated', (CURDATE() - INTERVAL 40 DAY) + INTERVAL 11 HOUR),
(3, 9, 'Vaccinated', (CURDATE() - INTERVAL 40 DAY) + INTERVAL 11 HOUR),
(3, 10, 'Vaccinated', (CURDATE() - INTERVAL 40 DAY) + INTERVAL 11 HOUR),
(3, 11, 'Vaccinated', (CURDATE() - INTERVAL 40 DAY) + INTERVAL 11 HOUR),
(4, 12, 'Pending', NULL),
(4, 13, 'Pending', NULL),
(4, 14, 'Pending', NULL),
(4, 15, 'Pending', NULL),
(5, 1, 'Vaccinated', (CURDATE() - INTERVAL 38 DAY) + INTERVAL 10 HOUR),
(5, 2, 'Vaccinated', (CURDATE() - INTERVAL 38 DAY) + INTERVAL 10 HOUR),
(5, 3, 'Vaccinated', (CURDATE() - INTERVAL 38 DAY) + INTERVAL 10 HOUR),
(6, 4, 'Pending', NULL),
(6, 5, 'Pending', NULL),
(6, 6, 'Pending', NULL),
(6, 7, 'Pending', NULL),
(7, 1, 'Vaccinated', (CURDATE() - INTERVAL 280 DAY) + INTERVAL 12 HOUR),
(7, 2, 'Vaccinated', (CURDATE() - INTERVAL 280 DAY) + INTERVAL 12 HOUR),
(7, 3, 'Vaccinated', (CURDATE() - INTERVAL 280 DAY) + INTERVAL 12 HOUR),
(8, 4, 'Vaccinated', (CURDATE() - INTERVAL 238 DAY) + INTERVAL 12 HOUR),
(8, 5, 'Vaccinated', (CURDATE() - INTERVAL 238 DAY) + INTERVAL 12 HOUR),
(8, 6, 'Vaccinated', (CURDATE() - INTERVAL 238 DAY) + INTERVAL 12 HOUR),
(8, 7, 'Vaccinated', (CURDATE() - INTERVAL 238 DAY) + INTERVAL 12 HOUR),
(9, 8, 'Vaccinated', (CURDATE() - INTERVAL 210 DAY) + INTERVAL 12 HOUR),
(9, 9, 'Vaccinated', (CURDATE() - INTERVAL 210 DAY) + INTERVAL 12 HOUR),
(9, 10, 'Vaccinated', (CURDATE() - INTERVAL 210 DAY) + INTERVAL 12 HOUR),
(9, 11, 'Vaccinated', (CURDATE() - INTERVAL 210 DAY) + INTERVAL 12 HOUR),
(10, 12, 'Vaccinated', (CURDATE() - INTERVAL 182 DAY) + INTERVAL 12 HOUR),
(10, 13, 'Vaccinated', (CURDATE() - INTERVAL 182 DAY) + INTERVAL 12 HOUR),
(10, 14, 'Vaccinated', (CURDATE() - INTERVAL 182 DAY) + INTERVAL 12 HOUR),
(10, 15, 'Vaccinated', (CURDATE() - INTERVAL 182 DAY) + INTERVAL 12 HOUR),
(11, 4, 'Vaccinated', (CURDATE() - INTERVAL 33 DAY) + INTERVAL 16 HOUR),
(11, 5, 'Vaccinated', (CURDATE() - INTERVAL 33 DAY) + INTERVAL 16 HOUR),
(11, 6, 'Vaccinated', (CURDATE() - INTERVAL 33 DAY) + INTERVAL 16 HOUR),
(11, 7, 'Not Vaccinated', NULL),
(12, 7, 'Pending', NULL),
(12, 8, 'Pending', NULL),
(12, 9, 'Pending', NULL),
(12, 10, 'Pending', NULL),
(12, 11, 'Pending', NULL),
(13, 1, 'Vaccinated', (CURDATE() - INTERVAL 470 DAY) + INTERVAL 11 HOUR),
(13, 2, 'Vaccinated', (CURDATE() - INTERVAL 470 DAY) + INTERVAL 11 HOUR),
(13, 3, 'Vaccinated', (CURDATE() - INTERVAL 470 DAY) + INTERVAL 11 HOUR),
(14, 4, 'Vaccinated', (CURDATE() - INTERVAL 428 DAY) + INTERVAL 11 HOUR),
(14, 5, 'Vaccinated', (CURDATE() - INTERVAL 428 DAY) + INTERVAL 11 HOUR),
(14, 6, 'Vaccinated', (CURDATE() - INTERVAL 428 DAY) + INTERVAL 11 HOUR),
(14, 7, 'Vaccinated', (CURDATE() - INTERVAL 428 DAY) + INTERVAL 11 HOUR),
(15, 8, 'Vaccinated', (CURDATE() - INTERVAL 400 DAY) + INTERVAL 11 HOUR),
(15, 9, 'Vaccinated', (CURDATE() - INTERVAL 400 DAY) + INTERVAL 11 HOUR),
(15, 10, 'Vaccinated', (CURDATE() - INTERVAL 400 DAY) + INTERVAL 11 HOUR),
(15, 11, 'Vaccinated', (CURDATE() - INTERVAL 400 DAY) + INTERVAL 11 HOUR),
(16, 12, 'Vaccinated', (CURDATE() - INTERVAL 372 DAY) + INTERVAL 11 HOUR),
(16, 13, 'Vaccinated', (CURDATE() - INTERVAL 372 DAY) + INTERVAL 11 HOUR),
(16, 14, 'Vaccinated', (CURDATE() - INTERVAL 372 DAY) + INTERVAL 11 HOUR),
(16, 15, 'Vaccinated', (CURDATE() - INTERVAL 372 DAY) + INTERVAL 11 HOUR),
(17, 16, 'Vaccinated', (CURDATE() - INTERVAL 200 DAY) + INTERVAL 12 HOUR),
(17, 17, 'Vaccinated', (CURDATE() - INTERVAL 200 DAY) + INTERVAL 12 HOUR),
(17, 18, 'Vaccinated', (CURDATE() - INTERVAL 200 DAY) + INTERVAL 12 HOUR),
(18, 19, 'Pending', NULL),
(19, 19, 'Not Vaccinated', NULL),
(20, 19, 'Pending', NULL);

-- Rayan's birth doses were given before joining (recorded by the parent)
INSERT INTO `vaccination_history` (`child_id`, `vaccine_id`, `given_on`, `place`) VALUES
(4, 1, CURDATE() - INTERVAL 74 DAY, 'Maternity home, Clifton'),
(4, 2, CURDATE() - INTERVAL 74 DAY, 'Maternity home, Clifton'),
(4, 3, CURDATE() - INTERVAL 74 DAY, 'Maternity home, Clifton');

INSERT INTO `notifications` (`user_role`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
('admin', 0, 'New booking request', 'Usman Raza requested a visit for Mahnoor Raza at Crescent Children''s Hospital.', 'admin/requests.php', 0, NOW() - INTERVAL 6 HOUR),
('admin', 0, 'New booking request', 'Ayesha Khan requested a visit for Zayan Khan at Crescent Children''s Hospital.', 'admin/requests.php', 0, NOW() - INTERVAL 1 DAY),
('admin', 0, 'Hospital registration', 'Kotri Mother & Child Centre registered and is waiting for approval.', 'admin/hospitals.php?status=Pending', 0, NOW() - INTERVAL 1 DAY),
('parent', 1, 'Booking approved', 'Hania Khan''s visit at Crescent Children''s Hospital is confirmed for today at 11:30 AM.', 'parent/bookings.php', 0, NOW() - INTERVAL 3 DAY),
('parent', 1, 'Vaccination recorded', 'Hania Khan received 4 vaccines at Crescent Children''s Hospital.', 'parent/report.php?child=1', 1, NOW() - INTERVAL 40 DAY),
('parent', 3, 'Booking approved', 'Rayan Tariq''s visit at Seaview Medical Centre is confirmed. Rota-1 catch-up dose added.', 'parent/bookings.php', 0, NOW() - INTERVAL 1 DAY),
('parent', 4, 'Booking rejected', 'The clinic is closed on the selected date. Please choose another date.', 'parent/bookings.php', 1, NOW() - INTERVAL 19 DAY),
('hospital', 1, 'New appointment', 'Hania Khan is booked for today at 11:30 AM (4 vaccines).', 'hospital/appointments.php', 0, NOW() - INTERVAL 3 DAY),
('hospital', 3, 'New appointment', 'Rayan Tariq is booked for tomorrow at 10:00 AM (5 vaccines).', 'hospital/appointments.php?view=upcoming', 0, NOW() - INTERVAL 1 DAY);

INSERT INTO `contact_messages` (`name`, `email`, `subject`, `message`, `is_read`, `created_at`) VALUES
('Farah Siddiqui', 'farah.s@example.com', 'Can I add twins?', 'I have twin daughters. Do I need to add them separately, and can I book both in the same visit?', 0, NOW() - INTERVAL 2 DAY),
('Dr. Kamran Ali', 'kamran.ali@example.com', 'Registering our clinic', 'We run a small paediatric clinic in Malir. What documents do you need before approving our registration?', 1, NOW() - INTERVAL 9 DAY);
