-- ================================================================================
-- SECURE EXAMINATION CONTENT MANAGEMENT SYSTEM
-- INSTITUTION: LAGOS STATE UNIVERSITY (LASU) - FACULTY OF COMPUTING & IT (FCIT)
-- VERSION: v0.5 - PHASE 2 DDL SCHEMA: USERS & AUTHENTICATION DATABASE
-- TARGET RDBMS: MYSQL 8.0+ / INNODB ENGINE
-- CHARACTER SET: UTF8MB4 / COLLATE: UTF8MB4_UNICODE_CI
-- ================================================================================
-- PREREQUISITE: Phase 1 tables must already exist:
--   departments, roles, academic_sessions, semesters, levels
-- DO NOT modify or recreate Phase 1 tables.
-- ================================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `login_history`;
DROP TABLE IF EXISTS `users`;

-- ================================================================================
-- TABLE 1: users
-- Central identity repository for all system actors.
-- Supports: System Administrator, HOD, Exam Officer, Moderator, Lecturer
-- Soft Delete: YES (deleted_at, deleted_by, delete_reason)
-- ================================================================================
CREATE TABLE `users` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id`               VARCHAR(50) NOT NULL,
  `first_name`             VARCHAR(100) NOT NULL,
  `middle_name`            VARCHAR(100) NULL DEFAULT NULL,
  `last_name`              VARCHAR(100) NOT NULL,
  `email`                  VARCHAR(150) NOT NULL,
  `phone_number`           VARCHAR(20) NULL DEFAULT NULL,
  `password_hash`          VARCHAR(255) NOT NULL,
  `role_id`                INT UNSIGNED NOT NULL,
  `department_id`          INT UNSIGNED NULL DEFAULT NULL,
  `account_status`         ENUM('Active', 'Inactive', 'Suspended', 'Locked') NOT NULL DEFAULT 'Active',
  `email_verified_at`      TIMESTAMP NULL DEFAULT NULL,
  `last_login_at`          TIMESTAMP NULL DEFAULT NULL,
  `failed_login_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `account_locked_until`   TIMESTAMP NULL DEFAULT NULL,
  `profile_photo_path`     VARCHAR(255) NULL DEFAULT NULL,
  `remember_token`         VARCHAR(100) NULL DEFAULT NULL,
  `force_password_change`  TINYINT(1) NOT NULL DEFAULT 1,
  `deleted_at`             TIMESTAMP NULL DEFAULT NULL,
  `deleted_by`             INT UNSIGNED NULL DEFAULT NULL,
  `delete_reason`          VARCHAR(255) NULL DEFAULT NULL,
  `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `pk_users`
    PRIMARY KEY (`id`),

  CONSTRAINT `uk_users_staff_id`
    UNIQUE (`staff_id`),

  CONSTRAINT `uk_users_email`
    UNIQUE (`email`),

  CONSTRAINT `chk_users_failed_attempts`
    CHECK (`failed_login_attempts` >= 0 AND `failed_login_attempts` <= 10),

  CONSTRAINT `fk_users_roles_role_id`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_users_departments_department_id`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for users
CREATE INDEX `idx_users_role_id`           ON `users` (`role_id`);
CREATE INDEX `idx_users_department_id`     ON `users` (`department_id`);
CREATE INDEX `idx_users_account_status`    ON `users` (`account_status`);
CREATE INDEX `idx_users_email_verified`    ON `users` (`email_verified_at`);
CREATE INDEX `idx_users_deleted_at`        ON `users` (`deleted_at`);
CREATE INDEX `idx_users_last_login`        ON `users` (`last_login_at`);


-- ================================================================================
-- TABLE 2: login_history
-- Dedicated, isolated authentication event log.
-- Tracks all login and logout events across all roles.
-- High-volume: uses BIGINT primary key.
-- ================================================================================
CREATE TABLE `login_history` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NULL DEFAULT NULL,
  `attempted_identifier` VARCHAR(150) NOT NULL,
  `login_status`      ENUM(
                        'Successful_Login',
                        'Failed_Password',
                        'User_NotFound',
                        'Account_Locked',
                        'Account_Inactive',
                        'Account_Suspended'
                      ) NOT NULL,
  `failure_reason`    VARCHAR(255) NULL DEFAULT NULL,
  `session_identifier` VARCHAR(128) NULL DEFAULT NULL,
  `ip_address`        VARCHAR(45) NOT NULL,
  `browser`           VARCHAR(150) NULL DEFAULT NULL,
  `device`            VARCHAR(50) NULL DEFAULT NULL,
  `operating_system`  VARCHAR(100) NULL DEFAULT NULL,
  `login_timestamp`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_timestamp`  TIMESTAMP NULL DEFAULT NULL,

  CONSTRAINT `pk_login_history`
    PRIMARY KEY (`id`),

  CONSTRAINT `fk_login_history_users_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for login_history
CREATE INDEX `idx_login_history_user_id`       ON `login_history` (`user_id`);
CREATE INDEX `idx_login_history_ip_status`     ON `login_history` (`ip_address`, `login_status`);
CREATE INDEX `idx_login_history_login_time`    ON `login_history` (`login_timestamp`);
CREATE INDEX `idx_login_history_status`        ON `login_history` (`login_status`);


-- ================================================================================
-- TABLE 3: password_reset_tokens
-- Secure single-use token management for password recovery.
-- Tokens are hashed; plain text is NEVER stored.
-- ================================================================================
CREATE TABLE `password_reset_tokens` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `token_hash`  VARCHAR(255) NOT NULL,
  `expires_at`  TIMESTAMP NOT NULL,
  `used_at`     TIMESTAMP NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `pk_password_reset_tokens`
    PRIMARY KEY (`id`),

  CONSTRAINT `uk_password_reset_tokens_token_hash`
    UNIQUE (`token_hash`),

  CONSTRAINT `fk_password_reset_tokens_users_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for password_reset_tokens
CREATE INDEX `idx_prt_user_id`     ON `password_reset_tokens` (`user_id`);
CREATE INDEX `idx_prt_expires_at`  ON `password_reset_tokens` (`expires_at`);


-- ================================================================================
-- TABLE 4: user_sessions
-- Tracks active authenticated sessions per user.
-- Enables forced logouts, concurrent session control, and session auditing.
-- ================================================================================
CREATE TABLE `user_sessions` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`      VARCHAR(128) NOT NULL,
  `user_id`         INT UNSIGNED NOT NULL,
  `ip_address`      VARCHAR(45) NOT NULL,
  `browser`         VARCHAR(150) NULL DEFAULT NULL,
  `device`          VARCHAR(50) NULL DEFAULT NULL,
  `session_status`  ENUM('Active', 'Expired', 'Logged_Out', 'Revoked') NOT NULL DEFAULT 'Active',
  `login_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity`   TIMESTAMP NULL DEFAULT NULL,
  `expires_at`      TIMESTAMP NULL DEFAULT NULL,

  CONSTRAINT `pk_user_sessions`
    PRIMARY KEY (`id`),

  CONSTRAINT `uk_user_sessions_session_id`
    UNIQUE (`session_id`),

  CONSTRAINT `fk_user_sessions_users_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for user_sessions
CREATE INDEX `idx_user_sessions_user_id`       ON `user_sessions` (`user_id`);
CREATE INDEX `idx_user_sessions_status`        ON `user_sessions` (`session_status`);
CREATE INDEX `idx_user_sessions_expires_at`    ON `user_sessions` (`expires_at`);

SET FOREIGN_KEY_CHECKS = 1;


-- ================================================================================
-- PHASE 2 SEED DATA: SAMPLE INSERT STATEMENTS (5 System Users)
-- Password: "Password@2025" hashed with Argon2id / bcrypt ($2y$12$...)
-- All sample hashes below are bcrypt cost=12 for "Password@2025"
-- ================================================================================

-- 1. System Administrator (No department assigned)
INSERT INTO `users` (
  `staff_id`, `first_name`, `middle_name`, `last_name`, `email`,
  `phone_number`, `password_hash`, `role_id`, `department_id`,
  `account_status`, `email_verified_at`, `force_password_change`
) VALUES (
  'LASU-ADM-001',
  'Oluwaseun',
  NULL,
  'Adeyemi',
  'sys.admin@lasu.edu.ng',
  '08012345678',
  '$2y$12$6eO7p5QRNBfkKspgPz.8LOY2BnZRkCPJqEk7lVa3S8ZXzLFzFsTy6',
  1,    -- role_id = 1 (ADMIN)
  NULL, -- System Admin has no department
  'Active',
  NOW(),
  0     -- No forced password change for seeded admin
);

-- 2. Head of Department (Computer Science - id=1)
INSERT INTO `users` (
  `staff_id`, `first_name`, `middle_name`, `last_name`, `email`,
  `phone_number`, `password_hash`, `role_id`, `department_id`,
  `account_status`, `email_verified_at`, `force_password_change`
) VALUES (
  'LASU-HOD-001',
  'Adewale',
  'Tunde',
  'Okafor',
  'hod.csc@lasu.edu.ng',
  '08023456789',
  '$2y$12$6eO7p5QRNBfkKspgPz.8LOY2BnZRkCPJqEk7lVa3S8ZXzLFzFsTy6',
  2,    -- role_id = 2 (HOD)
  1,    -- department_id = 1 (Computer Science)
  'Active',
  NOW(),
  1
);

-- 3. Exam Officer (Data Science - id=2)
INSERT INTO `users` (
  `staff_id`, `first_name`, `middle_name`, `last_name`, `email`,
  `phone_number`, `password_hash`, `role_id`, `department_id`,
  `account_status`, `email_verified_at`, `force_password_change`
) VALUES (
  'LASU-EXO-001',
  'Ngozi',
  'Chisom',
  'Eze',
  'exam.officer.dat@lasu.edu.ng',
  '08034567890',
  '$2y$12$6eO7p5QRNBfkKspgPz.8LOY2BnZRkCPJqEk7lVa3S8ZXzLFzFsTy6',
  3,    -- role_id = 3 (EXAM_OFFICER)
  2,    -- department_id = 2 (Data Science)
  'Active',
  NOW(),
  1
);

-- 4. Moderator (Cyber Security - id=4)
INSERT INTO `users` (
  `staff_id`, `first_name`, `middle_name`, `last_name`, `email`,
  `phone_number`, `password_hash`, `role_id`, `department_id`,
  `account_status`, `email_verified_at`, `force_password_change`
) VALUES (
  'LASU-MOD-001',
  'Babatunde',
  NULL,
  'Fashola',
  'moderator.cyb@lasu.edu.ng',
  '08045678901',
  '$2y$12$6eO7p5QRNBfkKspgPz.8LOY2BnZRkCPJqEk7lVa3S8ZXzLFzFsTy6',
  4,    -- role_id = 4 (MODERATOR)
  4,    -- department_id = 4 (Cyber Security)
  'Active',
  NOW(),
  1
);

-- 5. Lecturer (Software Engineering - id=5, home department)
INSERT INTO `users` (
  `staff_id`, `first_name`, `middle_name`, `last_name`, `email`,
  `phone_number`, `password_hash`, `role_id`, `department_id`,
  `account_status`, `email_verified_at`, `force_password_change`
) VALUES (
  'LASU-LEC-001',
  'Amaka',
  'Grace',
  'Nwosu',
  'lecturer.swe@lasu.edu.ng',
  '08056789012',
  '$2y$12$6eO7p5QRNBfkKspgPz.8LOY2BnZRkCPJqEk7lVa3S8ZXzLFzFsTy6',
  5,    -- role_id = 5 (LECTURER)
  5,    -- department_id = 5 (Software Engineering)
  'Active',
  NOW(),
  1
);
