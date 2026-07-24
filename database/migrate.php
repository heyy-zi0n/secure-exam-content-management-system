<?php
/**
 * Database Migration and Seeder Script
 * Run this script to set up/reset the database tables and insert initial seed data.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

echo "<pre>\n=== FCIT Exam CMS Database Migration ===\n\n";

try {
    $db = Database::getInstance();
    
    // Disable Foreign Key Checks for Dropping Tables
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // List of tables to drop
    $tables = [
        'system_settings',
        'hod_delegations',
        'security_events',
        'archived_papers',
        'print_logs',
        'print_queue',
        'audit_logs',
        'announcements',
        'notifications',
        'blind_lockdowns',
        'approval_records',
        'review_comments',
        'paper_versions',
        'examination_papers',
        'moderator_level_assignments',
        'lecturer_course_assignments',
        'courses',
        'semesters',
        'academic_sessions',
        'users',
        'departments'
    ];
    
    foreach ($tables as $table) {
        $db->exec("DROP TABLE IF EXISTS `$table` CASCADE");
        echo "[x] Dropped table (if existed): $table\n";
    }
    
    echo "\nCreating database tables...\n";
    
    // 1. Departments
    $db->exec("
        CREATE TABLE `departments` (
            `code` VARCHAR(10) PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: departments\n";
    
    // 2. Users
    $db->exec("
        CREATE TABLE `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `staff_id` VARCHAR(30) NOT NULL UNIQUE,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'hod', 'exam_officer', 'lecturer', 'moderator') NOT NULL DEFAULT 'lecturer',
            `department_code` VARCHAR(10) NULL,
            `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            `last_login` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: users\n";
    
    // 3. Academic Sessions
    $db->exec("
        CREATE TABLE `academic_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(20) NOT NULL,
            `status` ENUM('active', 'archived', 'closed') DEFAULT 'closed',
            `department_code` VARCHAR(10) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: academic_sessions\n";
    
    // 4. Semesters
    $db->exec("
        CREATE TABLE `semesters` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` ENUM('First Semester', 'Second Semester') NOT NULL,
            `status` ENUM('open', 'closed') DEFAULT 'closed',
            `department_code` VARCHAR(10) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: semesters\n";
    
    // 5. Courses
    $db->exec("
        CREATE TABLE `courses` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_code` VARCHAR(15) NOT NULL UNIQUE,
            `course_title` VARCHAR(150) NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `level` ENUM('100', '200', '300', '400', '500') NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: courses\n";
    
    // 6. Lecturer Course Assignments
    $db->exec("
        CREATE TABLE `lecturer_course_assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `lecturer_id` INT NOT NULL,
            `course_id` INT NOT NULL,
            `academic_session_id` INT NOT NULL,
            `semester_id` INT NOT NULL,
            `assigned_by` INT NOT NULL,
            `assignment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            UNIQUE KEY (`lecturer_id`, `course_id`, `academic_session_id`, `semester_id`),
            FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: lecturer_course_assignments\n";
    
    // 7. Moderator Level Assignments
    $db->exec("
        CREATE TABLE `moderator_level_assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `moderator_id` INT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `level` ENUM('100', '200', '300', '400', '500') NOT NULL,
            `academic_session_id` INT NOT NULL,
            `assigned_by` INT NOT NULL,
            `assignment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (`moderator_id`, `department_code`, `level`, `academic_session_id`),
            FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE,
            FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: moderator_level_assignments\n";
    
    // 8. Examination Papers
    $db->exec("
        CREATE TABLE `examination_papers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `course_id` INT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `academic_session_id` INT NOT NULL,
            `semester_id` INT NOT NULL,
            `level` ENUM('100', '200', '300', '400', '500') NOT NULL,
            `exam_date` DATE NOT NULL,
            `duration` VARCHAR(50) NOT NULL,
            `num_questions` INT NOT NULL,
            `instructions` TEXT NOT NULL,
            `assigned_moderator_id` INT NOT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
            `current_version_number` INT DEFAULT 1,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE,
            FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`assigned_moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: examination_papers\n";
    
    // 9. Paper Versions
    $db->exec("
        CREATE TABLE `paper_versions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL,
            `version_number` INT NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `file_hash` VARCHAR(64) NOT NULL,
            `file_size` INT NOT NULL,
            `marking_guide_path` VARCHAR(255) NULL,
            `marking_guide_orig` VARCHAR(255) NULL,
            `supporting_path` VARCHAR(255) NULL,
            `supporting_orig` VARCHAR(255) NULL,
            `reason_for_revision` TEXT NULL,
            `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `uploaded_by` INT NOT NULL,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: paper_versions\n";
    
    // 10. Review Comments
    $db->exec("
        CREATE TABLE `review_comments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL,
            `version_id` INT NOT NULL,
            `moderator_id` INT NOT NULL,
            `comment_type` ENUM('general', 'inline') DEFAULT 'general',
            `section_reference` VARCHAR(100) NULL,
            `comment_text` TEXT NOT NULL,
            `parent_id` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`version_id`) REFERENCES `paper_versions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`parent_id`) REFERENCES `review_comments`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: review_comments\n";
    
    // 11. Approval Records
    $db->exec("
        CREATE TABLE `approval_records` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL UNIQUE,
            `moderator_id` INT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `approval_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `approval_id` VARCHAR(50) NOT NULL UNIQUE,
            `approval_hash` VARCHAR(64) NOT NULL,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: approval_records\n";
    
    // 12. Blind Lockdowns
    $db->exec("
        CREATE TABLE `blind_lockdowns` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL UNIQUE,
            `lockdown_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `locked_by` INT NULL,
            `encryption_status` ENUM('pending', 'encrypted', 'failed') DEFAULT 'pending',
            `encryption_key_ref` VARCHAR(100) NULL,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`locked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: blind_lockdowns\n";
    
    // 13. Notifications
    $db->exec("
        CREATE TABLE `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: notifications\n";
    
    // 14. Announcements
    $db->exec("
        CREATE TABLE `announcements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `content` TEXT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE,
            FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: announcements\n";
    
    // 15. Audit Logs
    $db->exec("
        CREATE TABLE `audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `staff_id` VARCHAR(50) NULL,
            `role` VARCHAR(50) NULL,
            `department_code` VARCHAR(10) NULL,
            `action` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `ip_address` VARCHAR(45) NULL,
            `browser` VARCHAR(255) NULL,
            `device` VARCHAR(100) NULL,
            `os` VARCHAR(100) NULL,
            `session_id` VARCHAR(100) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: audit_logs\n";
    
    // 16. Print Queue
    $db->exec("
        CREATE TABLE `print_queue` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL UNIQUE,
            `status` ENUM('Ready', 'Queued', 'Printing', 'Printed') DEFAULT 'Ready',
            `queued_by` INT NULL,
            `queued_at` TIMESTAMP NULL,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`queued_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: print_queue\n";
    
    // 17. Print Logs
    $db->exec("
        CREATE TABLE `print_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `course_code` VARCHAR(15) NOT NULL,
            `level` VARCHAR(10) NOT NULL,
            `printer` VARCHAR(100) DEFAULT 'LASU FCIT Network Printer',
            `num_copies` INT NOT NULL,
            `ip_address` VARCHAR(45) NULL,
            `browser` VARCHAR(255) NULL,
            `status` VARCHAR(50) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: print_logs\n";
    
    // 18. Archived Papers
    $db->exec("
        CREATE TABLE `archived_papers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `paper_id` INT NOT NULL UNIQUE,
            `academic_session` VARCHAR(20) NOT NULL,
            `semester` VARCHAR(20) NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `course_code` VARCHAR(15) NOT NULL,
            `level` VARCHAR(10) NOT NULL,
            `archive_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `print_date` TIMESTAMP NULL,
            `approval_date` TIMESTAMP NULL,
            `version_number` INT NOT NULL,
            FOREIGN KEY (`paper_id`) REFERENCES `examination_papers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: archived_papers\n";
    
    // 19. Security Events
    $db->exec("
        CREATE TABLE `security_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `event_type` VARCHAR(100) NOT NULL,
            `description` TEXT NULL,
            `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
            `user_id` INT NULL,
            `ip_address` VARCHAR(45) NULL,
            `browser` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: security_events\n";
    
    // 20. HOD Delegations
    $db->exec("
        CREATE TABLE `hod_delegations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `delegated_by` INT NOT NULL,
            `acting_officer_id` INT NOT NULL,
            `department_code` VARCHAR(10) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `reason` TEXT NULL,
            `status` ENUM('Active', 'Expired', 'Revoked') DEFAULT 'Active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`delegated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`acting_officer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: hod_delegations\n";
    
    // 21. System Settings
    $db->exec("
        CREATE TABLE `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `department_code` VARCHAR(10) NOT NULL UNIQUE,
            `faculty_name` VARCHAR(255) DEFAULT 'Faculty of Computing and Information Technology',
            `department_name` VARCHAR(255) NOT NULL,
            `active_session_id` INT NULL,
            `active_semester_id` INT NULL,
            `submission_deadline` DATE NULL,
            `allowed_file_types` VARCHAR(255) DEFAULT 'pdf,docx',
            `max_upload_size` INT DEFAULT 20971520,
            `printing_window_hours` INT DEFAULT 24,
            `announcement_settings` TEXT NULL,
            FOREIGN KEY (`department_code`) REFERENCES `departments`(`code`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[+] Created table: system_settings\n";
    
    // Re-enable Foreign Key Checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "\n=== Seeding Initial Data ===\n";
    
    // Seed Departments
    $departments = [
        ['CSC', 'Computer Science'],
        ['SWE', 'Software Engineering'],
        ['CYB', 'Cyber Security'],
        ['DAT', 'Data Science'],
        ['ICT', 'Information and Communication Technology']
    ];
    
    $depStmt = $db->prepare("INSERT INTO departments (code, name) VALUES (?, ?)");
    foreach ($departments as $dept) {
        $depStmt->execute($dept);
        echo "[+] Seeded Department: {$dept[0]} - {$dept[1]}\n";
    }
    
    // Seed academic sessions and semesters for each department
    $sessionStmt = $db->prepare("INSERT INTO academic_sessions (name, status, department_code) VALUES (?, ?, ?)");
    $semesterStmt = $db->prepare("INSERT INTO semesters (name, status, department_code) VALUES (?, ?, ?)");
    $settingsStmt = $db->prepare("
        INSERT INTO system_settings 
        (department_code, department_name, active_session_id, active_semester_id, submission_deadline) 
        VALUES (:dept, :name, :session, :semester, :deadline)
    ");
    
    foreach ($departments as $dept) {
        $code = $dept[0];
        $name = $dept[1];
        
        // Session
        $sessionStmt->execute(['2025/2026', 'active', $code]);
        $sessionId = $db->lastInsertId();
        
        // Semester
        $semesterStmt->execute(['First Semester', 'open', $code]);
        $semesterId = $db->lastInsertId();
        
        // Settings
        $deadline = date('Y-m-d', strtotime('+30 days'));
        $settingsStmt->execute([
            ':dept' => $code,
            ':name' => $name,
            ':session' => $sessionId,
            ':semester' => $semesterId,
            ':deadline' => $deadline
        ]);
        
        echo "[+] Seeded Sessions & settings for department $code\n";
    }
    
    // Seed Users
    $defaultPassword = password_hash('Password123!', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $users = [
        // System Admin
        [
            'staff_id' => 'FCIT/ADM/001',
            'full_name' => 'System Administrator',
            'email' => 'admin@lasu.edu.ng',
            'password' => $defaultPassword,
            'role' => 'admin',
            'department_code' => 'CSC',
            'status' => 'active'
        ]
    ];
    
    // Add HOD, Exam Officer, Lecturer, Moderator for each of the 5 departments
    foreach ($departments as $dept) {
        $code = $dept[0];
        $lcCode = strtolower($code);
        
        $users[] = [
            'staff_id' => "FCIT/HOD/$code",
            'full_name' => "Prof. HOD $code",
            'email' => "$lcCode.hod@lasu.edu.ng",
            'password' => $defaultPassword,
            'role' => 'hod',
            'department_code' => $code,
            'status' => 'active'
        ];
        
        $users[] = [
            'staff_id' => "FCIT/EO/$code",
            'full_name' => "Mr. Exam Officer $code",
            'email' => "$lcCode.officer@lasu.edu.ng",
            'password' => $defaultPassword,
            'role' => 'exam_officer',
            'department_code' => $code,
            'status' => 'active'
        ];
        
        $users[] = [
            'staff_id' => "FCIT/LEC/$code",
            'full_name' => "Dr. Lecturer $code",
            'email' => "$lcCode.lecturer@lasu.edu.ng",
            'password' => $defaultPassword,
            'role' => 'lecturer',
            'department_code' => $code,
            'status' => 'active'
        ];
        
        $users[] = [
            'staff_id' => "FCIT/MOD/$code",
            'full_name' => "Prof. Moderator $code",
            'email' => "$lcCode.moderator@lasu.edu.ng",
            'password' => $defaultPassword,
            'role' => 'moderator',
            'department_code' => $code,
            'status' => 'active'
        ];
    }
    
    $userStmt = $db->prepare("
        INSERT INTO users (staff_id, full_name, email, password, role, department_code, status) 
        VALUES (:staff_id, :full_name, :email, :password, :role, :department_code, :status)
    ");
    
    foreach ($users as $user) {
        $userStmt->execute($user);
        echo "[+] Seeded User: {$user['staff_id']} ({$user['role']} - {$user['department_code']})\n";
    }
    
    // Seed Courses
    $courses = [
        ['CSC', 'CSC101', 'Introduction to Computer Science', '100'],
        ['CSC', 'CSC221', 'Introduction to Artificial Intelligence', '300'],
        ['CSC', 'CSC410', 'Compiler Construction', '400'],
        
        ['SWE', 'SWE203', 'Software Requirements Engineering', '200'],
        ['SWE', 'SWE312', 'Software Architecture & Design', '300'],
        
        ['CYB', 'CYB301', 'Introduction to Cryptography', '300'],
        ['CYB', 'CYB402', 'Penetration Testing & Vulnerability Analysis', '400'],
        
        ['DAT', 'DAT210', 'Data Wrangling & Visualization', '200'],
        ['DAT', 'DAT302', 'Statistical Machine Learning', '300'],
        
        ['ICT', 'ICT102', 'Introduction to Information Systems', '100'],
        ['ICT', 'ICT408', 'Telecommunication & Networks', '400']
    ];
    
    $courseStmt = $db->prepare("
        INSERT INTO courses (department_code, course_code, course_title, level) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($courses as $c) {
        $courseStmt->execute($c);
        echo "[+] Seeded Course: {$c[1]} - {$c[2]}\n";
    }
    
    // Seed Lecturer Course Assignments
    // We assign Lecturer CSC to CSC101, CSC221, and Lecturer SWE to SWE203.
    // We also assign Lecturer CSC (home CSC) to SWE203 (cross-department SWE) to verify cross-department teaching!
    
    $lecCscId = $db->query("SELECT id FROM users WHERE email = 'csc.lecturer@lasu.edu.ng'")->fetchColumn();
    $lecSweId = $db->query("SELECT id FROM users WHERE email = 'swe.lecturer@lasu.edu.ng'")->fetchColumn();
    $hodCscId = $db->query("SELECT id FROM users WHERE email = 'csc.hod@lasu.edu.ng'")->fetchColumn();
    $hodSweId = $db->query("SELECT id FROM users WHERE email = 'swe.hod@lasu.edu.ng'")->fetchColumn();
    
    $cIdCsc101 = $db->query("SELECT id FROM courses WHERE course_code = 'CSC101'")->fetchColumn();
    $cIdCsc221 = $db->query("SELECT id FROM courses WHERE course_code = 'CSC221'")->fetchColumn();
    $cIdSwe203 = $db->query("SELECT id FROM courses WHERE course_code = 'SWE203'")->fetchColumn();
    
    $sessCscId = $db->query("SELECT id FROM academic_sessions WHERE department_code = 'CSC'")->fetchColumn();
    $sessSweId = $db->query("SELECT id FROM academic_sessions WHERE department_code = 'SWE'")->fetchColumn();
    
    $semCscId = $db->query("SELECT id FROM semesters WHERE department_code = 'CSC'")->fetchColumn();
    $semSweId = $db->query("SELECT id FROM semesters WHERE department_code = 'SWE'")->fetchColumn();
    
    $assignStmt = $db->prepare("
        INSERT INTO lecturer_course_assignments 
        (lecturer_id, course_id, academic_session_id, semester_id, assigned_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Assign CSC101 and CSC221 to Lecturer CSC
    $assignStmt->execute([$lecCscId, $cIdCsc101, $sessCscId, $semCscId, $hodCscId]);
    $assignStmt->execute([$lecCscId, $cIdCsc221, $sessCscId, $semCscId, $hodCscId]);
    echo "[+] Assigned CSC101 and CSC221 to csc.lecturer@lasu.edu.ng\n";
    
    // Assign SWE203 to Lecturer CSC (Cross-Department) - assigned by SWE HOD since SWE owns SWE203
    $assignStmt->execute([$lecCscId, $cIdSwe203, $sessSweId, $semSweId, $hodSweId]);
    echo "[+] Cross-Assigned SWE203 (SWE) to csc.lecturer@lasu.edu.ng (CSC)\n";
    
    // Seed Moderator Level Assignments
    // Moderator CSC is assigned to 100, 200, 300, 400, 500 levels in CSC
    $modCscId = $db->query("SELECT id FROM users WHERE email = 'csc.moderator@lasu.edu.ng'")->fetchColumn();
    $modSweId = $db->query("SELECT id FROM users WHERE email = 'swe.moderator@lasu.edu.ng'")->fetchColumn();
    
    $modAssignStmt = $db->prepare("
        INSERT INTO moderator_level_assignments 
        (moderator_id, department_code, level, academic_session_id, assigned_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach (['100', '200', '300', '400', '500'] as $lvl) {
        $modAssignStmt->execute([$modCscId, 'CSC', $lvl, $sessCscId, $hodCscId]);
    }
    echo "[+] Assigned csc.moderator@lasu.edu.ng to all academic levels in CSC department\n";
    
    foreach (['100', '200', '300', '400', '500'] as $lvl) {
        $modAssignStmt->execute([$modSweId, 'SWE', $lvl, $sessSweId, $hodSweId]);
    }
    echo "[+] Assigned swe.moderator@lasu.edu.ng to all academic levels in SWE department\n";
    
    echo "\n=== Migration Completed Successfully! ===\n";
    
} catch (Exception $e) {
    echo "\n[-] Migration Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "</pre>";
