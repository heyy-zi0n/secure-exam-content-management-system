<?php
/**
 * FCIT Secure Examination CMS - Global System Constants
 * Faculty of Computing and Information Technology, Lagos State University
 */

// Application Information
define('APP_NAME', 'LASU FCIT Secure Exam CMS');
define('APP_SHORT_NAME', 'LASU Exam CMS');
define('FACULTY_NAME', 'Faculty of Computing and Information Technology');
define('INSTITUTION_NAME', 'Lagos State University');
define('APP_VERSION', '1.0.0');

// Base URL & Paths
define('BASE_URL', 'http://localhost/lasu_exam_cms');
define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', ROOT_PATH . '/logs');

define('UPLOAD_PATH_TEMP', STORAGE_PATH . '/temporary');
define('UPLOAD_PATH_ENCRYPTED', STORAGE_PATH . '/encrypted');
define('UPLOAD_PATH_ARCHIVE', STORAGE_PATH . '/archive');

// Security & Session Constants
define('SESSION_LIFETIME', 1800); // 30 minutes inactivity limit
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Constraints
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20 MB Limit
define('ALLOWED_EXTENSIONS', ['pdf', 'docx']);
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

// FCIT Departmental Architecture
define('FCIT_DEPARTMENTS', [
    'CSC' => 'Computer Science',
    'SWE' => 'Software Engineering',
    'CYB' => 'Cyber Security',
    'DAT' => 'Data Science',
    'ICT' => 'Information and Communication Technology'
]);

// Academic Levels
define('ACADEMIC_LEVELS', ['100', '200', '300', '400', '500']);

// Examination Workflow States
define('WORKFLOW_STATES', [
    'DRAFT'                    => 'Draft',
    'SUBMITTED'                => 'Submitted',
    'UNDER_REVIEW'             => 'Under Review',
    'CORRECTION_REQUESTED'     => 'Correction Requested',
    'RESUBMITTED'              => 'Re-Submitted',
    'APPROVED'                 => 'Approved',
    'BLIND_LOCKDOWN_ACTIVATED' => 'Blind Lockdown Activated',
    'READY_FOR_PRINTING'       => 'Ready for Printing',
    'PRINTING_QUEUE'           => 'Printing Queue',
    'PRINTED'                  => 'Printed',
    'ARCHIVED'                 => 'Archived',
    'REJECTED'                 => 'Rejected'
]);