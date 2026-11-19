/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: vaccination_db
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `vaccination_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `vaccination_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `vaccination_db`;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt hash (password_hash)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES
(1,'System Administrator','admin@teekacard.pk','$2y$10$uctFu04orS/N9e8ZuKadC.dUz9c9iyuK6qpnFbGKc1bBgPhK4J1Km','2026-09-17 11:28:27',NULL);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_vaccines`
--

DROP TABLE IF EXISTS `appointment_vaccines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_vaccines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `vaccine_id` int(10) unsigned NOT NULL,
  `status` enum('Pending','Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  `given_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appt_vaccine` (`appointment_id`,`vaccine_id`),
  KEY `idx_av_status` (`status`),
  KEY `fk_av_vaccine` (`vaccine_id`),
  CONSTRAINT `fk_av_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_av_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_vaccines`
--

LOCK TABLES `appointment_vaccines` WRITE;
/*!40000 ALTER TABLE `appointment_vaccines` DISABLE KEYS */;
INSERT INTO `appointment_vaccines` VALUES
(1,1,1,'Vaccinated','2026-05-30 10:00:00'),
(2,1,2,'Vaccinated','2026-05-30 10:00:00'),
(3,1,3,'Vaccinated','2026-05-30 10:00:00'),
(4,2,4,'Vaccinated','2026-07-11 11:00:00'),
(5,2,5,'Vaccinated','2026-07-11 11:00:00'),
(6,2,6,'Vaccinated','2026-07-11 11:00:00'),
(7,2,7,'Vaccinated','2026-07-11 11:00:00'),
(8,3,8,'Vaccinated','2026-08-08 11:00:00'),
(9,3,9,'Vaccinated','2026-08-08 11:00:00'),
(10,3,10,'Vaccinated','2026-08-08 11:00:00'),
(11,3,11,'Vaccinated','2026-08-08 11:00:00'),
(12,4,12,'Pending',NULL),
(13,4,13,'Pending',NULL),
(14,4,14,'Pending',NULL),
(15,4,15,'Pending',NULL),
(16,5,1,'Vaccinated','2026-08-10 10:00:00'),
(17,5,2,'Vaccinated','2026-08-10 10:00:00'),
(18,5,3,'Vaccinated','2026-08-10 10:00:00'),
(19,6,4,'Pending',NULL),
(20,6,5,'Pending',NULL),
(21,6,6,'Pending',NULL),
(22,6,7,'Pending',NULL),
(23,7,1,'Vaccinated','2025-12-11 12:00:00'),
(24,7,2,'Vaccinated','2025-12-11 12:00:00'),
(25,7,3,'Vaccinated','2025-12-11 12:00:00'),
(26,8,4,'Vaccinated','2026-01-22 12:00:00'),
(27,8,5,'Vaccinated','2026-01-22 12:00:00'),
(28,8,6,'Vaccinated','2026-01-22 12:00:00'),
(29,8,7,'Vaccinated','2026-01-22 12:00:00'),
(30,9,8,'Vaccinated','2026-02-19 12:00:00'),
(31,9,9,'Vaccinated','2026-02-19 12:00:00'),
(32,9,10,'Vaccinated','2026-02-19 12:00:00'),
(33,9,11,'Vaccinated','2026-02-19 12:00:00'),
(34,10,12,'Vaccinated','2026-03-19 12:00:00'),
(35,10,13,'Vaccinated','2026-03-19 12:00:00'),
(36,10,14,'Vaccinated','2026-03-19 12:00:00'),
(37,10,15,'Vaccinated','2026-03-19 12:00:00'),
(38,11,4,'Vaccinated','2026-08-15 16:00:00'),
(39,11,5,'Vaccinated','2026-08-15 16:00:00'),
(40,11,6,'Vaccinated','2026-08-15 16:00:00'),
(41,11,7,'Not Vaccinated',NULL),
(42,12,7,'Pending',NULL),
(43,12,8,'Pending',NULL),
(44,12,9,'Pending',NULL),
(45,12,10,'Pending',NULL),
(46,12,11,'Pending',NULL),
(47,13,1,'Vaccinated','2025-06-04 11:00:00'),
(48,13,2,'Vaccinated','2025-06-04 11:00:00'),
(49,13,3,'Vaccinated','2025-06-04 11:00:00'),
(50,14,4,'Vaccinated','2025-07-16 11:00:00'),
(51,14,5,'Vaccinated','2025-07-16 11:00:00'),
(52,14,6,'Vaccinated','2025-07-16 11:00:00'),
(53,14,7,'Vaccinated','2025-07-16 11:00:00'),
(54,15,8,'Vaccinated','2025-08-13 11:00:00'),
(55,15,9,'Vaccinated','2025-08-13 11:00:00'),
(56,15,10,'Vaccinated','2025-08-13 11:00:00'),
(57,15,11,'Vaccinated','2025-08-13 11:00:00'),
(58,16,12,'Vaccinated','2025-09-10 11:00:00'),
(59,16,13,'Vaccinated','2025-09-10 11:00:00'),
(60,16,14,'Vaccinated','2025-09-10 11:00:00'),
(61,16,15,'Vaccinated','2025-09-10 11:00:00'),
(62,17,16,'Vaccinated','2026-03-01 12:00:00'),
(63,17,17,'Vaccinated','2026-03-01 12:00:00'),
(64,17,18,'Vaccinated','2026-03-01 12:00:00'),
(65,18,19,'Pending',NULL),
(66,19,19,'Not Vaccinated',NULL),
(67,20,19,'Pending',NULL);
/*!40000 ALTER TABLE `appointment_vaccines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL,
  `child_id` int(10) unsigned NOT NULL,
  `hospital_id` int(10) unsigned NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `parent_note` varchar(255) DEFAULT NULL,
  `request_status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_remark` varchar(255) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `vaccination_status` enum('Pending','Vaccinated','Partially Vaccinated','Not Vaccinated') NOT NULL DEFAULT 'Pending',
  `hospital_remark` varchar(255) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_appt_date` (`appointment_date`),
  KEY `idx_appt_request` (`request_status`),
  KEY `idx_appt_vacc` (`vaccination_status`),
  KEY `fk_appt_parent` (`parent_id`),
  KEY `fk_appt_child` (`child_id`),
  KEY `fk_appt_hospital` (`hospital_id`),
  CONSTRAINT `fk_appt_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  CONSTRAINT `fk_appt_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES
(1,1,1,1,'2026-05-30','10:00:00',NULL,'Approved',NULL,'2026-05-29 11:28:27','Vaccinated','Birth doses given.','2026-05-30 10:00:00','2026-05-28 11:28:27',NULL),
(2,1,1,1,'2026-07-11','10:30:00',NULL,'Approved',NULL,'2026-07-09 11:28:27','Vaccinated',NULL,'2026-07-11 11:00:00','2026-07-07 11:28:27',NULL),
(3,1,1,1,'2026-08-08','11:00:00',NULL,'Approved',NULL,'2026-08-06 11:28:27','Vaccinated',NULL,'2026-08-08 11:00:00','2026-08-04 11:28:27',NULL),
(4,1,1,1,'2026-09-17','11:30:00','She had a mild cold last week.','Approved','Please bring the EPI card.','2026-09-14 11:28:27','Pending',NULL,NULL,'2026-09-12 11:28:27',NULL),
(5,1,2,1,'2026-08-10','09:30:00',NULL,'Approved',NULL,'2026-08-09 11:28:27','Vaccinated',NULL,'2026-08-10 10:00:00','2026-08-08 11:28:27',NULL),
(6,1,2,1,'2026-09-21','10:00:00','Morning slot preferred.','Pending',NULL,NULL,'Pending',NULL,NULL,'2026-09-16 11:28:27',NULL),
(7,2,3,2,'2025-12-11','12:00:00',NULL,'Approved',NULL,'2025-12-10 11:28:27','Vaccinated',NULL,'2025-12-11 12:00:00','2025-12-09 11:28:27',NULL),
(8,2,3,2,'2026-01-22','12:00:00',NULL,'Approved',NULL,'2026-01-20 11:28:27','Vaccinated',NULL,'2026-01-22 12:00:00','2026-01-18 11:28:27',NULL),
(9,2,3,2,'2026-02-19','12:30:00',NULL,'Approved',NULL,'2026-02-17 11:28:27','Vaccinated',NULL,'2026-02-19 12:00:00','2026-02-15 11:28:27',NULL),
(10,2,3,2,'2026-03-19','12:30:00',NULL,'Approved',NULL,'2026-03-17 11:28:27','Vaccinated',NULL,'2026-03-19 12:00:00','2026-03-15 11:28:27',NULL),
(11,3,4,3,'2026-08-15','16:00:00',NULL,'Approved',NULL,'2026-08-13 11:28:27','Partially Vaccinated','Rotavirus vaccine was out of stock on the day.','2026-08-15 16:00:00','2026-08-12 11:28:27',NULL),
(12,3,4,3,'2026-09-18','10:00:00',NULL,'Approved','Rota-1 catch-up dose added.','2026-09-16 11:28:27','Pending',NULL,NULL,'2026-09-15 11:28:27',NULL),
(13,4,5,3,'2025-06-04','11:00:00',NULL,'Approved',NULL,'2025-06-03 11:28:27','Vaccinated',NULL,'2025-06-04 11:00:00','2025-06-02 11:28:27',NULL),
(14,4,5,3,'2025-07-16','11:00:00',NULL,'Approved',NULL,'2025-07-14 11:28:27','Vaccinated',NULL,'2025-07-16 11:00:00','2025-07-12 11:28:27',NULL),
(15,4,5,3,'2025-08-13','11:30:00',NULL,'Approved',NULL,'2025-08-11 11:28:27','Vaccinated',NULL,'2025-08-13 11:00:00','2025-08-09 11:28:27',NULL),
(16,4,5,3,'2025-09-10','11:30:00',NULL,'Approved',NULL,'2025-09-08 11:28:27','Vaccinated',NULL,'2025-09-10 11:00:00','2025-09-06 11:28:27',NULL),
(17,4,5,3,'2026-03-01','12:00:00',NULL,'Approved',NULL,'2026-02-27 11:28:27','Vaccinated','TCV given from last available stock.','2026-03-01 12:00:00','2026-02-25 11:28:27',NULL),
(18,4,5,2,'2026-08-30','12:00:00',NULL,'Rejected','The clinic is closed on the selected date. Please choose another date.','2026-08-29 11:28:27','Pending',NULL,NULL,'2026-08-28 11:28:27',NULL),
(19,4,5,1,'2026-09-02','12:00:00',NULL,'Approved',NULL,'2026-08-31 11:28:27','Not Vaccinated','Child had high fever. Advised to return after recovery.','2026-09-02 12:00:00','2026-08-30 11:28:27',NULL),
(20,4,5,1,'2026-09-19','12:30:00','Fever is gone now.','Pending',NULL,NULL,'Pending',NULL,NULL,'2026-09-17 05:28:27',NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `children`
--

DROP TABLE IF EXISTS `children`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `children` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `birth_weight` decimal(4,2) DEFAULT NULL COMMENT 'kg',
  `notes` varchar(255) DEFAULT NULL COMMENT 'allergies / medical notes',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_child_parent` (`parent_id`),
  CONSTRAINT `fk_child_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `children`
--

LOCK TABLES `children` WRITE;
/*!40000 ALTER TABLE `children` DISABLE KEYS */;
INSERT INTO `children` VALUES
(1,1,'Hania Khan','2026-05-30','Female','O+',3.10,NULL,'2026-09-17 11:28:27',NULL),
(2,1,'Zayan Khan','2026-08-10','Male','B+',2.90,'Mild jaundice at birth, resolved','2026-09-17 11:28:27',NULL),
(3,2,'Inaya Bilal','2025-12-11','Female','A+',3.30,NULL,'2026-09-17 11:28:27',NULL),
(4,3,'Rayan Tariq','2026-07-04','Male',NULL,3.00,NULL,'2026-09-17 11:28:27',NULL),
(5,4,'Mahnoor Raza','2025-06-04','Female','AB+',2.80,'Allergic to eggs','2026-09-17 11:28:27',NULL);
/*!40000 ALTER TABLE `children` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
INSERT INTO `contact_messages` VALUES
(1,'Farah Siddiqui','farah.s@example.com','Can I add twins?','I have twin daughters. Do I need to add them separately, and can I book both in the same visit?',0,'2026-09-15 11:28:27'),
(2,'Dr. Kamran Ali','kamran.ali@example.com','Registering our clinic','We run a small paediatric clinic in Malir. What documents do you need before approving our registration?',1,'2026-09-08 11:28:27');
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hospitals`
--

DROP TABLE IF EXISTS `hospitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hospitals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `city` varchar(60) NOT NULL,
  `address` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL COMMENT 'Area / landmark / map link',
  `timings` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Pending','Active','Inactive') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hospital_email` (`email`),
  KEY `idx_hospital_city` (`city`),
  KEY `idx_hospital_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hospitals`
--

LOCK TABLES `hospitals` WRITE;
/*!40000 ALTER TABLE `hospitals` DISABLE KEYS */;
INSERT INTO `hospitals` VALUES
(1,'Crescent Children\'s Hospital','hospital@teekacard.pk','021-34980011','Karachi','Plot 21, Block 13-D, Gulshan-e-Iqbal','Near Maskan Chowrangi','Mon to Sat, 9:00 AM to 5:00 PM','$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO','Active','2025-05-05 11:28:27',NULL),
(2,'Nazimabad Family Clinic','nazimabad@example.com','021-36620044','Karachi','B-12, Block H, North Nazimabad','Opposite Hydari Market','Mon to Sat, 10:00 AM to 6:00 PM','$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO','Active','2025-05-05 11:28:27',NULL),
(3,'Seaview Medical Centre','seaview@example.com','021-35870077','Karachi','7-C, Block 5, Clifton','Near Abdullah Shah Ghazi Road','Daily, 8:00 AM to 8:00 PM','$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO','Active','2025-05-05 11:28:27',NULL),
(4,'Mall Road Health Centre','mallroad@example.com','042-37320099','Lahore','41 Mall Road, near Regal Chowk','Regal Chowk','Mon to Fri, 9:00 AM to 4:00 PM','$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO','Active','2026-08-18 11:28:27',NULL),
(5,'Kotri Mother & Child Centre','kotri.mcc@example.com','022-38710022','Hyderabad','Main Kotri Road, Unit 4','Near Kotri Barrage','Mon to Sat, 9:00 AM to 3:00 PM','$2y$10$mrXK9WwkhPSV.AL7dAa6.O05TviqyIHMmbZUz5WPYjaIYsN.7cXhO','Pending','2026-09-16 11:28:27',NULL);
/*!40000 ALTER TABLE `hospitals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_role` enum('admin','parent','hospital') NOT NULL,
  `user_id` int(10) unsigned NOT NULL DEFAULT 0,
  `title` varchar(150) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notify_user` (`user_role`,`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES
(1,'admin',0,'New booking request','Usman Raza requested a visit for Mahnoor Raza at Crescent Children\'s Hospital.','admin/requests.php',0,'2026-09-17 05:28:27'),
(2,'admin',0,'New booking request','Ayesha Khan requested a visit for Zayan Khan at Crescent Children\'s Hospital.','admin/requests.php',0,'2026-09-16 11:28:27'),
(3,'admin',0,'Hospital registration','Kotri Mother & Child Centre registered and is waiting for approval.','admin/hospitals.php?status=Pending',0,'2026-09-16 11:28:27'),
(4,'parent',1,'Booking approved','Hania Khan\'s visit at Crescent Children\'s Hospital is confirmed for today at 11:30 AM.','parent/bookings.php',0,'2026-09-14 11:28:27'),
(5,'parent',1,'Vaccination recorded','Hania Khan received 4 vaccines at Crescent Children\'s Hospital.','parent/report.php?child=1',1,'2026-08-08 11:28:27'),
(6,'parent',3,'Booking approved','Rayan Tariq\'s visit at Seaview Medical Centre is confirmed. Rota-1 catch-up dose added.','parent/bookings.php',0,'2026-09-16 11:28:27'),
(7,'parent',4,'Booking rejected','The clinic is closed on the selected date. Please choose another date.','parent/bookings.php',1,'2026-08-29 11:28:27'),
(8,'hospital',1,'New appointment','Hania Khan is booked for today at 11:30 AM (4 vaccines).','hospital/appointments.php',0,'2026-09-14 11:28:27'),
(9,'hospital',3,'New appointment','Rayan Tariq is booked for tomorrow at 10:00 AM (5 vaccines).','hospital/appointments.php?view=upcoming',0,'2026-09-16 11:28:27');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parents`
--

DROP TABLE IF EXISTS `parents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `cnic` varchar(15) DEFAULT NULL,
  `city` varchar(60) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Active','Blocked') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parent_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parents`
--

LOCK TABLES `parents` WRITE;
/*!40000 ALTER TABLE `parents` DISABLE KEYS */;
INSERT INTO `parents` VALUES
(1,'Ayesha Khan','parent@teekacard.pk','0300-1234567','42101-1234567-2','Karachi','House 14, Block 13-D, Gulshan-e-Iqbal','$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2','Active','2026-05-28 11:28:27',NULL),
(2,'Bilal Ahmed','bilal.ahmed@example.com','0321-1234567','42201-7654321-1','Karachi','Flat 3B, Block H, North Nazimabad','$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2','Active','2025-12-09 11:28:27',NULL),
(3,'Sana Tariq','sana.tariq@example.com','0333-1234567',NULL,'Karachi','Street 5, Block 2, Clifton','$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2','Active','2026-08-08 11:28:27',NULL),
(4,'Usman Raza','usman.raza@example.com','0345-1234567','42301-1112223-5','Karachi','House 88, Sector 11-A, North Karachi','$2y$10$zCmePRSo.uTh2tVbicGeE.4vQ9J5CeHsB7lFEG9Ut8z8Yv2d/IZk2','Active','2025-06-02 11:28:27',NULL);
/*!40000 ALTER TABLE `parents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vaccination_history`
--

DROP TABLE IF EXISTS `vaccination_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vaccination_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `child_id` int(10) unsigned NOT NULL,
  `vaccine_id` int(10) unsigned NOT NULL,
  `given_on` date NOT NULL,
  `place` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_history_dose` (`child_id`,`vaccine_id`),
  KEY `fk_hist_vaccine` (`vaccine_id`),
  CONSTRAINT `fk_hist_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vaccination_history`
--

LOCK TABLES `vaccination_history` WRITE;
/*!40000 ALTER TABLE `vaccination_history` DISABLE KEYS */;
INSERT INTO `vaccination_history` VALUES
(1,4,1,'2026-07-05','Maternity home, Clifton','2026-09-17 11:28:27'),
(2,4,2,'2026-07-05','Maternity home, Clifton','2026-09-17 11:28:27'),
(3,4,3,'2026-07-05','Maternity home, Clifton','2026-09-17 11:28:27');
/*!40000 ALTER TABLE `vaccination_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vaccines`
--

DROP TABLE IF EXISTS `vaccines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `vaccines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) NOT NULL,
  `protects_against` varchar(255) NOT NULL,
  `age_days` int(10) unsigned NOT NULL DEFAULT 0,
  `age_label` varchar(30) NOT NULL,
  `route` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Available','Unavailable') NOT NULL DEFAULT 'Available',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vaccine_short` (`short_name`),
  KEY `idx_vaccine_age` (`age_days`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vaccines`
--

LOCK TABLES `vaccines` WRITE;
/*!40000 ALTER TABLE `vaccines` DISABLE KEYS */;
INSERT INTO `vaccines` VALUES
(1,'BCG','BCG','Childhood tuberculosis',0,'At birth','Injection (left upper arm)','Given as soon as possible after birth. A small scar at the injection site is normal.','Available','2026-09-17 11:28:27',NULL),
(2,'Oral Polio Vaccine (birth dose)','OPV-0','Poliomyelitis',0,'At birth','Oral drops','Birth dose of oral polio drops.','Available','2026-09-17 11:28:27',NULL),
(3,'Hepatitis B (birth dose)','HepB-0','Hepatitis B',0,'At birth','Injection (thigh)','Best given within 24 hours of birth.','Available','2026-09-17 11:28:27',NULL),
(4,'Oral Polio Vaccine (dose 1)','OPV-1','Poliomyelitis',42,'6 weeks','Oral drops',NULL,'Available','2026-09-17 11:28:27',NULL),
(5,'Pentavalent (dose 1)','Penta-1','Diphtheria, pertussis, tetanus, hepatitis B, Hib',42,'6 weeks','Injection (thigh)','Five-in-one vaccine.','Available','2026-09-17 11:28:27',NULL),
(6,'Pneumococcal conjugate (dose 1)','PCV-1','Pneumonia and meningitis (pneumococcal)',42,'6 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(7,'Rotavirus (dose 1)','Rota-1','Severe diarrhoea caused by rotavirus',42,'6 weeks','Oral drops',NULL,'Available','2026-09-17 11:28:27',NULL),
(8,'Oral Polio Vaccine (dose 2)','OPV-2','Poliomyelitis',70,'10 weeks','Oral drops',NULL,'Available','2026-09-17 11:28:27',NULL),
(9,'Pentavalent (dose 2)','Penta-2','Diphtheria, pertussis, tetanus, hepatitis B, Hib',70,'10 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(10,'Pneumococcal conjugate (dose 2)','PCV-2','Pneumonia and meningitis (pneumococcal)',70,'10 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(11,'Rotavirus (dose 2)','Rota-2','Severe diarrhoea caused by rotavirus',70,'10 weeks','Oral drops',NULL,'Available','2026-09-17 11:28:27',NULL),
(12,'Oral Polio Vaccine (dose 3)','OPV-3','Poliomyelitis',98,'14 weeks','Oral drops',NULL,'Available','2026-09-17 11:28:27',NULL),
(13,'Pentavalent (dose 3)','Penta-3','Diphtheria, pertussis, tetanus, hepatitis B, Hib',98,'14 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(14,'Pneumococcal conjugate (dose 3)','PCV-3','Pneumonia and meningitis (pneumococcal)',98,'14 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(15,'Inactivated Polio Vaccine (dose 1)','IPV-1','Poliomyelitis',98,'14 weeks','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(16,'Measles-Rubella (dose 1)','MR-1','Measles and rubella',270,'9 months','Injection (upper arm)',NULL,'Available','2026-09-17 11:28:27',NULL),
(17,'Typhoid Conjugate Vaccine','TCV','Typhoid fever',270,'9 months','Injection (thigh)','Single dose.','Unavailable','2026-09-17 11:28:27',NULL),
(18,'Inactivated Polio Vaccine (dose 2)','IPV-2','Poliomyelitis',270,'9 months','Injection (thigh)',NULL,'Available','2026-09-17 11:28:27',NULL),
(19,'Measles-Rubella (dose 2)','MR-2','Measles and rubella',450,'15 months','Injection (upper arm)','Completes the routine schedule.','Available','2026-09-17 11:28:27',NULL);
/*!40000 ALTER TABLE `vaccines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'vaccination_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
