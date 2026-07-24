-- ================================================================================
-- SECURE EXAMINATION CONTENT MANAGEMENT SYSTEM
-- INSTITUTION: LAGOS STATE UNIVERSITY (LASU) - FACULTY OF COMPUTING & IT (FCIT)
-- PHASE 1 DDL SCHEMA: CORE ORGANIZATIONAL & ACADEMIC INFRASTRUCTURE
-- TARGET RDBMS: MYSQL 8.0+ / INNODB ENGINE
-- CHARACTER SET: UTF8MB4 / COLLATE: UTF8MB4_UNICODE_CI
-- ================================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `academic_sessions`;
DROP TABLE IF EXISTS `levels`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `departments`;

-- --------------------------------------------------------------------------------
-- 1. TABLE: departments
-- --------------------------------------------------------------------------------
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(10) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_by` INT UNSIGNED NULL DEFAULT NULL,
  `delete_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `pk_departments` PRIMARY KEY (`id`),
  CONSTRAINT `uk_departments_code` UNIQUE (`code`),
  CONSTRAINT `uk_departments_name` UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_departments_deleted_at` ON `departments` (`deleted_at`);


-- --------------------------------------------------------------------------------
-- 2. TABLE: roles
-- --------------------------------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_code` VARCHAR(30) NOT NULL,
  `role_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `pk_roles` PRIMARY KEY (`id`),
  CONSTRAINT `uk_roles_role_code` UNIQUE (`role_code`),
  CONSTRAINT `uk_roles_role_name` UNIQUE (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------------------------------
-- 3. TABLE: levels
-- --------------------------------------------------------------------------------
CREATE TABLE `levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_code` VARCHAR(10) NOT NULL,
  `level_name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `pk_levels` PRIMARY KEY (`id`),
  CONSTRAINT `uk_levels_level_code` UNIQUE (`level_code`),
  CONSTRAINT `uk_levels_level_name` UNIQUE (`level_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------------------------------
-- 4. TABLE: academic_sessions
-- --------------------------------------------------------------------------------
CREATE TABLE `academic_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_name` VARCHAR(20) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `pk_academic_sessions` PRIMARY KEY (`id`),
  CONSTRAINT `uk_academic_sessions_session_name` UNIQUE (`session_name`),
  CONSTRAINT `chk_academic_sessions_date_range` CHECK (`end_date` > `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_academic_sessions_is_current` ON `academic_sessions` (`is_current`);


-- --------------------------------------------------------------------------------
-- 5. TABLE: semesters
-- --------------------------------------------------------------------------------
CREATE TABLE `semesters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_session_id` INT UNSIGNED NOT NULL,
  `semester_name` ENUM('Harmattan', 'Rain', 'First', 'Second') NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `pk_semesters` PRIMARY KEY (`id`),
  CONSTRAINT `uk_semesters_session_semester` UNIQUE (`academic_session_id`, `semester_name`),
  CONSTRAINT `chk_semesters_date_range` CHECK (`end_date` > `start_date`),
  CONSTRAINT `fk_semesters_academic_sessions_academic_session_id` 
    FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_semesters_academic_session_id` ON `semesters` (`academic_session_id`);
CREATE INDEX `idx_semesters_is_active` ON `semesters` (`is_active`);

SET FOREIGN_KEY_CHECKS = 1;


-- ================================================================================
-- PHASE 1 SEED DATA / INSERT STATEMENTS
-- ================================================================================

-- 1. Insert Departments (FCIT)
INSERT INTO `departments` (`code`, `name`) VALUES
('CSC', 'Computer Science'),
('DAT', 'Data Science'),
('ICT', 'Information and Communication Technology'),
('CYB', 'Cyber Security'),
('SWE', 'Software Engineering')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Insert Default Roles
INSERT INTO `roles` (`role_code`, `role_name`, `description`) VALUES
('ADMIN', 'System Administrator', 'Full system management and configuration access'),
('HOD', 'Head of Department', 'Departmental oversight, lecturer allocations, and moderation workflow approval'),
('EXAM_OFFICER', 'Exam Officer', 'Manages examination schedules and secure print requests'),
('MODERATOR', 'Moderator', 'Reviews and vets assigned examination papers by level and department'),
('LECTURER', 'Lecturer', 'Uploads examination papers, marking guides, and revises papers based on feedback')
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`);

-- 3. Insert Academic Levels
INSERT INTO `levels` (`level_code`, `level_name`, `description`) VALUES
('100', '100 Level', 'First Year Undergraduate'),
('200', '200 Level', 'Second Year Undergraduate'),
('300', '300 Level', 'Third Year Undergraduate'),
('400', '400 Level', 'Fourth Year Undergraduate / Final Year')
ON DUPLICATE KEY UPDATE `level_name` = VALUES(`level_name`);

-- 4. Insert Academic Sessions (15 sessions: 2025/2026 – 2039/2040)
INSERT INTO `academic_sessions` (`session_name`, `start_date`, `end_date`, `is_current`) VALUES
('2025/2026', '2025-10-01', '2026-07-31', 1),
('2026/2027', '2026-10-01', '2027-07-31', 0),
('2027/2028', '2027-10-01', '2028-07-31', 0),
('2028/2029', '2028-10-01', '2029-07-31', 0),
('2029/2030', '2029-10-01', '2030-07-31', 0),
('2030/2031', '2030-10-01', '2031-07-31', 0),
('2031/2032', '2031-10-01', '2032-07-31', 0),
('2032/2033', '2032-10-01', '2033-07-31', 0),
('2033/2034', '2033-10-01', '2034-07-31', 0),
('2034/2035', '2034-10-01', '2035-07-31', 0),
('2035/2036', '2035-10-01', '2036-07-31', 0),
('2036/2037', '2036-10-01', '2037-07-31', 0),
('2037/2038', '2037-10-01', '2038-07-31', 0),
('2038/2039', '2038-10-01', '2039-07-31', 0),
('2039/2040', '2039-10-01', '2040-07-31', 0)
ON DUPLICATE KEY UPDATE `is_current` = VALUES(`is_current`);

-- 5. Insert Semesters for all Academic Sessions
-- Semesters are per session: First (Oct 1 – Feb 28/29) and Second (Mar 15 – Jul 31)
INSERT INTO `semesters` (`academic_session_id`, `semester_name`, `is_active`, `start_date`, `end_date`) VALUES
-- 2025/2026 (id 1)
(1, 'First', 1, '2025-10-01', '2026-02-28'),
(1, 'Second', 0, '2026-03-15', '2026-07-31'),
-- 2026/2027 (id 2)
(2, 'First', 0, '2026-10-01', '2027-02-28'),
(2, 'Second', 0, '2027-03-15', '2027-07-31'),
-- 2027/2028 (id 3)
(3, 'First', 0, '2027-10-01', '2028-02-28'),
(3, 'Second', 0, '2028-03-15', '2028-07-31'),
-- 2028/2029 (id 4)
(4, 'First', 0, '2028-10-01', '2029-02-28'),
(4, 'Second', 0, '2029-03-15', '2029-07-31'),
-- 2029/2030 (id 5)
(5, 'First', 0, '2029-10-01', '2030-02-28'),
(5, 'Second', 0, '2030-03-15', '2030-07-31'),
-- 2030/2031 (id 6)
(6, 'First', 0, '2030-10-01', '2031-02-28'),
(6, 'Second', 0, '2031-03-15', '2031-07-31'),
-- 2031/2032 (id 7)
(7, 'First', 0, '2031-10-01', '2032-02-28'),
(7, 'Second', 0, '2032-03-15', '2032-07-31'),
-- 2032/2033 (id 8)
(8, 'First', 0, '2032-10-01', '2033-02-28'),
(8, 'Second', 0, '2033-03-15', '2033-07-31'),
-- 2033/2034 (id 9)
(9, 'First', 0, '2033-10-01', '2034-02-28'),
(9, 'Second', 0, '2034-03-15', '2034-07-31'),
-- 2034/2035 (id 10)
(10, 'First', 0, '2034-10-01', '2035-02-28'),
(10, 'Second', 0, '2035-03-15', '2035-07-31'),
-- 2035/2036 (id 11)
(11, 'First', 0, '2035-10-01', '2036-02-28'),
(11, 'Second', 0, '2036-03-15', '2036-07-31'),
-- 2036/2037 (id 12)
(12, 'First', 0, '2036-10-01', '2037-02-28'),
(12, 'Second', 0, '2037-03-15', '2037-07-31'),
-- 2037/2038 (id 13)
(13, 'First', 0, '2037-10-01', '2038-02-28'),
(13, 'Second', 0, '2038-03-15', '2038-07-31'),
-- 2038/2039 (id 14)
(14, 'First', 0, '2038-10-01', '2039-02-28'),
(14, 'Second', 0, '2039-03-15', '2039-07-31'),
-- 2039/2040 (id 15)
(15, 'First', 0, '2039-10-01', '2040-02-28'),
(15, 'Second', 0, '2040-03-15', '2040-07-31')
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);
