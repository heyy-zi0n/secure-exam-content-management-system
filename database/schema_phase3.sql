-- ================================================================================
-- SECURE EXAMINATION CONTENT MANAGEMENT SYSTEM
-- INSTITUTION: LAGOS STATE UNIVERSITY (LASU) - FACULTY OF COMPUTING & IT (FCIT)
-- VERSION: v0.6 - ACADEMIC MANAGEMENT DATABASE
-- TARGET RDBMS: MYSQL 8.0+ / INNODB ENGINE
-- CHARACTER SET: UTF8MB4 / COLLATE: UTF8MB4_UNICODE_CI
-- ================================================================================
-- PREREQUISITES - The following Phase 1 & 2 tables must already exist:
--   departments, roles, academic_sessions, semesters, levels, users
-- DO NOT modify or recreate any existing tables.
-- ================================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `moderator_assignments`;
DROP TABLE IF EXISTS `lecturer_course_assignments`;
DROP TABLE IF EXISTS `courses`;

-- ================================================================================
-- TABLE 1: courses
-- Master repository of all FCIT academic courses.
-- Each course belongs to one department, one level, and one semester type.
-- Soft Delete: NO (inactive courses use status = 'Inactive')
-- ================================================================================
CREATE TABLE `courses` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_code`          VARCHAR(15) NOT NULL,
  `course_title`         VARCHAR(200) NOT NULL,
  `course_unit`          TINYINT UNSIGNED NOT NULL,
  `department_id`        INT UNSIGNED NOT NULL,
  `level_id`             INT UNSIGNED NOT NULL,
  `semester_id`          INT UNSIGNED NOT NULL,
  `academic_session_id`  INT UNSIGNED NULL DEFAULT NULL,
  `status`               ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `description`          TEXT NULL DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT `pk_courses`
    PRIMARY KEY (`id`),

  CONSTRAINT `uk_courses_course_code`
    UNIQUE (`course_code`),

  CONSTRAINT `chk_courses_unit_range`
    CHECK (`course_unit` >= 1 AND `course_unit` <= 6),

  CONSTRAINT `fk_courses_departments_department_id`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_courses_levels_level_id`
    FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_courses_semesters_semester_id`
    FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_courses_academic_sessions_academic_session_id`
    FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for courses
CREATE INDEX `idx_courses_department_id`       ON `courses` (`department_id`);
CREATE INDEX `idx_courses_level_id`            ON `courses` (`level_id`);
CREATE INDEX `idx_courses_semester_id`         ON `courses` (`semester_id`);
CREATE INDEX `idx_courses_academic_session_id` ON `courses` (`academic_session_id`);
CREATE INDEX `idx_courses_status`              ON `courses` (`status`);


-- ================================================================================
-- TABLE 2: lecturer_course_assignments
-- Many-to-many junction table: Lecturers <-> Courses.
-- Supports cross-departmental teaching without duplicating lecturer records.
-- A lecturer may be assigned courses from any FCIT department per session.
-- ================================================================================
CREATE TABLE `lecturer_course_assignments` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lecturer_id`          INT UNSIGNED NOT NULL,
  `course_id`            INT UNSIGNED NOT NULL,
  `academic_session_id`  INT UNSIGNED NOT NULL,
  `assignment_status`    ENUM('Active', 'Transferred', 'Revoked') NOT NULL DEFAULT 'Active',
  `assigned_by`          INT UNSIGNED NOT NULL,
  `assigned_date`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes`                TEXT NULL DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL,

  CONSTRAINT `pk_lecturer_course_assignments`
    PRIMARY KEY (`id`),

  -- A lecturer can only be assigned the same course once per academic session
  CONSTRAINT `uk_lca_lecturer_course_session`
    UNIQUE (`lecturer_id`, `course_id`, `academic_session_id`),

  CONSTRAINT `fk_lca_users_lecturer_id`
    FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_lca_courses_course_id`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_lca_academic_sessions_academic_session_id`
    FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_lca_users_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for lecturer_course_assignments
CREATE INDEX `idx_lca_lecturer_id`          ON `lecturer_course_assignments` (`lecturer_id`);
CREATE INDEX `idx_lca_course_id`            ON `lecturer_course_assignments` (`course_id`);
CREATE INDEX `idx_lca_academic_session_id`  ON `lecturer_course_assignments` (`academic_session_id`);
CREATE INDEX `idx_lca_assignment_status`    ON `lecturer_course_assignments` (`assignment_status`);
CREATE INDEX `idx_lca_assigned_by`          ON `lecturer_course_assignments` (`assigned_by`);


-- ================================================================================
-- TABLE 3: moderator_assignments
-- Maps moderators to courses, department, and level per academic session.
-- Supports:
--   - One moderator moderating multiple courses
--   - Multiple moderators per course (if required)
--   - Department + level-scoped moderation assignments
-- ================================================================================
CREATE TABLE `moderator_assignments` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `moderator_id`         INT UNSIGNED NOT NULL,
  `course_id`            INT UNSIGNED NOT NULL,
  `academic_session_id`  INT UNSIGNED NOT NULL,
  `department_id`        INT UNSIGNED NOT NULL,
  `level_id`             INT UNSIGNED NOT NULL,
  `assignment_status`    ENUM('Active', 'Completed', 'Revoked') NOT NULL DEFAULT 'Active',
  `assigned_by`          INT UNSIGNED NOT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NULL DEFAULT NULL,

  CONSTRAINT `pk_moderator_assignments`
    PRIMARY KEY (`id`),

  -- One active moderator per course per session
  -- (Remove uk if multiple moderators per course are needed)
  CONSTRAINT `uk_ma_moderator_course_session`
    UNIQUE (`moderator_id`, `course_id`, `academic_session_id`),

  CONSTRAINT `fk_ma_users_moderator_id`
    FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_ma_courses_course_id`
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_ma_academic_sessions_academic_session_id`
    FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_ma_departments_department_id`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_ma_levels_level_id`
    FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,

  CONSTRAINT `fk_ma_users_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for moderator_assignments
CREATE INDEX `idx_ma_moderator_id`          ON `moderator_assignments` (`moderator_id`);
CREATE INDEX `idx_ma_course_id`             ON `moderator_assignments` (`course_id`);
CREATE INDEX `idx_ma_academic_session_id`   ON `moderator_assignments` (`academic_session_id`);
CREATE INDEX `idx_ma_department_id`         ON `moderator_assignments` (`department_id`);
CREATE INDEX `idx_ma_level_id`              ON `moderator_assignments` (`level_id`);
CREATE INDEX `idx_ma_assignment_status`     ON `moderator_assignments` (`assignment_status`);
CREATE INDEX `idx_ma_assigned_by`           ON `moderator_assignments` (`assigned_by`);

SET FOREIGN_KEY_CHECKS = 1;


-- ================================================================================
-- v0.6 SEED DATA
-- ================================================================================

-- -----------------------------------------------
-- SEED: 5 Sample Courses (across FCIT departments)
-- Semester ID 1 = Harmattan 2025/2026
-- Semester ID 2 = Rain 2025/2026
-- Level IDs: 1=100L, 2=200L, 3=300L, 4=400L
-- Department IDs: 1=CSC, 2=DAT, 3=ICT, 4=CYB, 5=SWE
-- -----------------------------------------------
INSERT INTO `courses`
  (`course_code`, `course_title`, `course_unit`, `department_id`, `level_id`, `semester_id`, `academic_session_id`, `status`, `description`)
VALUES
  ('CSC301', 'Data Structures and Algorithms',    3, 1, 3, 1, 1, 'Active', 'Fundamental data structures and algorithmic design for third year Computer Science students'),
  ('SWE401', 'Software Engineering Management',  3, 5, 4, 1, 1, 'Active', 'Software project management, quality assurance, and delivery for final year SWE students'),
  ('CYB301', 'Network Security Fundamentals',     3, 4, 3, 1, 1, 'Active', 'Principles of network security, attack vectors, and countermeasures for 300L Cyber Security students'),
  ('DAT201', 'Introduction to Data Analytics',   2, 2, 2, 2, 1, 'Active', 'Introductory module covering data analysis techniques and visualization tools for 200L Data Science students'),
  ('ICT101', 'Introduction to Information Tech', 2, 3, 1, 1, 1, 'Active', 'Foundational ICT concepts for 100L Information Technology students')
ON DUPLICATE KEY UPDATE `course_title` = VALUES(`course_title`);

-- -----------------------------------------------
-- SEED: Lecturer Course Assignments
-- user_id 5 = Amaka Nwosu (Lecturer, SWE home dept)
-- user_id 1 = Oluwaseun Adeyemi (Admin, assigns courses)
--
-- Scenario A: Lecturer assigned to multiple courses in home dept (SWE)
-- Scenario B: Same lecturer assigned to a course in a different dept (CYB)
-- -----------------------------------------------

-- Amaka Nwosu (SWE Lecturer) teaching SWE401 in her home department
INSERT INTO `lecturer_course_assignments`
  (`lecturer_id`, `course_id`, `academic_session_id`, `assignment_status`, `assigned_by`, `notes`)
VALUES
  (5, 2, 1, 'Active', 1, 'Primary course assignment — home department (Software Engineering)');

-- Amaka Nwosu (SWE Lecturer) also teaching CSC301 in Computer Science (cross-department)
INSERT INTO `lecturer_course_assignments`
  (`lecturer_id`, `course_id`, `academic_session_id`, `assignment_status`, `assigned_by`, `notes`)
VALUES
  (5, 1, 1, 'Active', 1, 'Cross-departmental assignment — Computer Science, approved by System Administrator');

-- Amaka Nwosu (SWE Lecturer) also teaching CYB301 in Cyber Security (cross-department)
INSERT INTO `lecturer_course_assignments`
  (`lecturer_id`, `course_id`, `academic_session_id`, `assignment_status`, `assigned_by`, `notes`)
VALUES
  (5, 3, 1, 'Active', 1, 'Cross-departmental assignment — Cyber Security, approved by System Administrator');

-- -----------------------------------------------
-- SEED: Moderator Assignments
-- user_id 4 = Babatunde Fashola (Moderator, CYB)
-- Assigned to moderate CSC301 (300L CSC) and SWE401 (400L SWE)
-- -----------------------------------------------

-- Moderator assigned to CSC301 (300L Computer Science)
INSERT INTO `moderator_assignments`
  (`moderator_id`, `course_id`, `academic_session_id`, `department_id`, `level_id`, `assignment_status`, `assigned_by`)
VALUES
  (4, 1, 1, 1, 3, 'Active', 1);

-- Moderator assigned to SWE401 (400L Software Engineering)
INSERT INTO `moderator_assignments`
  (`moderator_id`, `course_id`, `academic_session_id`, `department_id`, `level_id`, `assignment_status`, `assigned_by`)
VALUES
  (4, 2, 1, 5, 4, 'Active', 1);

-- Moderator assigned to CYB301 (300L Cyber Security — home dept)
INSERT INTO `moderator_assignments`
  (`moderator_id`, `course_id`, `academic_session_id`, `department_id`, `level_id`, `assignment_status`, `assigned_by`)
VALUES
  (4, 3, 1, 4, 3, 'Active', 1);
