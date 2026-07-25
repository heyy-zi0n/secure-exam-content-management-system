-- ============================================================
-- v0.7 - Examination Workflow Management (Phase 1)
-- Table: examination_papers
-- ============================================================

CREATE TABLE IF NOT EXISTS `examination_papers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT UNSIGNED NOT NULL,
    `lecturer_id` INT UNSIGNED NOT NULL,
    `academic_session_id` INT UNSIGNED NOT NULL,
    `semester_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `level_id` INT UNSIGNED NOT NULL,
    `examination_type` ENUM('Mid Semester Test', 'Continuous Assessment', 'Practical', 'Final Examination', 'Supplementary Examination') NOT NULL DEFAULT 'Final Examination',
    `paper_title` VARCHAR(255) NOT NULL,
    `instructions` TEXT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 120,
    `total_marks` INT NOT NULL DEFAULT 100,
    `submission_status` ENUM('Draft', 'Submitted', 'Returned', 'Approved', 'Rejected') NOT NULL DEFAULT 'Draft',
    `current_version` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`) ON DELETE CASCADE,
    INDEX `idx_lecturer` (`lecturer_id`),
    INDEX `idx_course` (`course_id`),
    INDEX `idx_session` (`academic_session_id`),
    INDEX `idx_status` (`submission_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
