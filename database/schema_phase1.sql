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

-- 4. Insert Current Academic Session
INSERT INTO `academic_sessions` (`session_name`, `start_date`, `end_date`, `is_current`) VALUES
('2025/2026', '2025-10-01', '2026-07-31', 1)
ON DUPLICATE KEY UPDATE `is_current` = VALUES(`is_current`);

-- 5. Insert Semesters for Current Session (2025/2026)
INSERT INTO `semesters` (`academic_session_id`, `semester_name`, `is_active`, `start_date`, `end_date`) VALUES
(1, 'Harmattan', 1, '2025-10-01', '2026-02-28'),
(1, 'Rain', 0, '2026-03-15', '2026-07-31')
ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);
