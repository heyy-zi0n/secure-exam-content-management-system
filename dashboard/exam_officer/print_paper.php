<?php
/**
 * Secure Printing stream
 * Decrypts exam paper in-memory and outputs it directly to the browser print stream.
 * Logs print history and increments print counters.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

// Disable error display for binary output integrity
ini_set('display_errors', 0);
error_reporting(0);

if (!isLoggedIn()) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

requireRole('exam_officer');

$db = Database::getInstance();
$user = currentUser();
$paperId = (int)($_GET['id'] ?? 0);
$dept = $user['department_code'];

// Fetch paper details
$stmt = $db->prepare("
    SELECT ep.*, c.course_code
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    WHERE ep.id = :id AND ep.department_code = :dept
    LIMIT 1
");
$stmt->execute([':id' => $paperId, ':dept' => $dept]);
$paper = $stmt->fetch();

if (!$paper) {
    header("HTTP/1.1 404 Not Found");
    echo "Document not found.";
    exit;
}

// Enforce strict printing eligibility rule: 1 day before exam date
$examTimestamp = strtotime($paper['exam_date'] . ' 00:00:00');
$eligibleTimestamp = strtotime($paper['exam_date'] . ' -1 day 00:00:00');
if (time() < $eligibleTimestamp) {
    header("HTTP/1.1 403 Forbidden");
    echo "Security Restriction: Exam papers can only be printed 1 day before the exam date.";
    exit;
}

// Retrieve decrypted paper contents in-memory
$fileData = getDecryptedPaperContent($paperId);
if (!$fileData) {
    header("HTTP/1.1 404 Not Found");
    echo "Decryption failed. File data unavailable.";
    exit;
}

$db->beginTransaction();
try {
    // 1. Increment print count in print queue and update status
    $qStmt = $db->prepare("
        UPDATE print_queue 
        SET prints_count = prints_count + 1, 
            status = 'Printed', 
            last_printed_at = NOW()
        WHERE paper_id = :paper_id
    ");
    $qStmt->execute([':paper_id' => $paperId]);
    
    // 2. Update paper status to Printed
    $pStmt = $db->prepare("
        UPDATE examination_papers 
        SET status = 'Printed', 
            updated_at = NOW() 
        WHERE id = :id
    ");
    $pStmt->execute([':id' => $paperId]);
    
    // 3. Insert into print_logs
    $ip = getClientIp();
    $logStmt = $db->prepare("
        INSERT INTO print_logs (paper_id, printed_by, ip_address, status)
        VALUES (:paper_id, :printed_by, :ip, 'success')
    ");
    $logStmt->execute([
        ':paper_id' => $paperId,
        ':printed_by' => $user['id'],
        ':ip' => $ip
    ]);
    
    $db->commit();
    
    // 4. Write audit log
    logAudit('Paper Printed', "Printed examination paper for {$paper['course_code']} (ID: $paperId). Print IP: $ip");
    
} catch (Exception $e) {
    $db->rollBack();
    header("HTTP/1.1 500 Internal Server Error");
    echo "Database error during logging.";
    exit;
}

// Output headers & stream decrypted data
if (ob_get_length()) {
    ob_end_clean();
}

header("Content-Type: " . $fileData['mimetype']);
header("Content-Disposition: inline; filename=\"PRINT_" . $fileData['filename'] . "\"");
header("Content-Length: " . strlen($fileData['content']));
header("Cache-Control: private, max-age=0, must-revalidate");
header("Pragma: public");

echo $fileData['content'];
exit;
