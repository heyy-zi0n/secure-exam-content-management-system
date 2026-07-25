<?php
/**
 * FCIT Secure Examination CMS - Main Configuration Loader
 */

// Load global constants
require_once __DIR__ . '/constants.php';

// Load global functions from helpers
require_once __DIR__ . '/../helpers/functions.php';

// Ensure storage and logging directories exist
$requiredFolders = [
    LOG_PATH, STORAGE_PATH, UPLOAD_PATH_TEMP, UPLOAD_PATH_ENCRYPTED, UPLOAD_PATH_ARCHIVE,
    EXAM_STORAGE_PATH, EXAM_STORAGE_PATH_DRAFT, EXAM_STORAGE_PATH_SUBMIT,
    EXAM_STORAGE_PATH_APPRV, EXAM_STORAGE_PATH_ARCH
];
foreach ($requiredFolders as $folder) {
    if (!is_dir($folder)) {
        @mkdir($folder, 0777, true);
    }
}
// Block direct web access via local .htaccess
if (!file_exists(EXAM_STORAGE_PATH . '/.htaccess')) {
    @file_put_contents(EXAM_STORAGE_PATH . '/.htaccess', "Require all denied\nOptions -Indexes\n");
}