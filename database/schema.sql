CREATE DATABASE IF NOT EXISTS `lasu_fcit_exam_cms` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `lasu_fcit_exam_cms`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Departments Table
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `code` VARCHAR(10) PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`code`, `name`) VALUES
('CSC', 'Computer Science'),
('ICT', 'Information and Communication Technology'),
('DAT', 'Data Science'),
('SWE', 'Software Engineering'),
('CYB', 'Cyber Security');

-- 2. Users Table
DROP TABLE IF EXISTS `users`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Activity / Audit Logs Table
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;