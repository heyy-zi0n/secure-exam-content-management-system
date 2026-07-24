<?php
/**
 * Secure PDF/DOCX Document Stream Decrypter
 * Authenticates user, decrypts file key from DB, and streams content in-memory.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/workflow_helper.php';

// Disable error display for streaming clean binary data
ini_set('display_errors', 0);
error_reporting(0);

if (!isLoggedIn()) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

$db = Database::getInstance();
$user = currentUser();
$paperId = (int)($_GET['id'] ?? 0);

// Fetch paper details
$stmt = $db->prepare("
    SELECT ep.*, c.course_code, c.level
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    WHERE ep.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $paperId]);
$paper = $stmt->fetch();

if (!$paper) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

// Enforce strict authorization checks
$authSuccess = false;
$role = $user['role'];
$userDept = $user['department_code'];
$paperDept = $paper['department_code'];

if ($role === 'admin') {
    $authSuccess = true;
} elseif ($role === 'hod') {
    if ($userDept === $paperDept) {
        $authSuccess = true;
    }
} elseif ($role === 'exam_officer') {
    $approvedStates = ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived'];
    if ($userDept === $paperDept && in_array($paper['status'], $approvedStates)) {
        $authSuccess = true;
    }
} elseif ($role === 'moderator') {
    $modCheck = $db->prepare("
        SELECT id FROM moderator_level_assignments 
        WHERE moderator_id = :mod_id AND department_code = :dept AND level = :level AND academic_session_id = :sess_id
        LIMIT 1
    ");
    $modCheck->execute([
        ':mod_id'  => $user['id'],
        ':dept'    => $paperDept,
        ':level'   => $paper['level'],
        ':sess_id' => $paper['academic_session_id']
    ]);
    if ($modCheck->fetch()) {
        $authSuccess = true;
    }
} elseif ($role === 'lecturer') {
    if ((int)$paper['created_by'] === (int)$user['id']) {
        $lockedStates = ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived'];
        if (!in_array($paper['status'], $lockedStates)) {
            $authSuccess = true;
        }
    }
}

if (!$authSuccess) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// Retrieve decrypted file data
$fileData = getDecryptedPaperContent($paperId);
if (!$fileData) {
    header("HTTP/1.1 404 Not Found");
    exit;
}

// Clear any output buffer to ensure binary file is clean
if (ob_get_length()) {
    ob_end_clean();
}

// Stream PDF/DOCX
header("Content-Type: " . $fileData['mimetype']);
header("Content-Disposition: inline; filename=\"" . $fileData['filename'] . "\"");
header("Content-Length: " . strlen($fileData['content']));
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");

echo $fileData['content'];
exit;
