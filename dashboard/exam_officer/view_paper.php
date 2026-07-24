<?php
/**
 * Secure Paper View
 * Allows Exam Officer to preview a decrypted exam paper directly in the browser.
 * The paper can only be accessed 1 day before the exam date (same rule as printing).
 * This page streams the file in‑memory without forcing a download.
 */

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('exam_officer');

$db = Database::getInstance();
$user = currentUser();
$paperId = (int)($_GET['id'] ?? 0);
$dept = $user['department_code'];

// ---------------------------------------------------------------------
// Validate request and fetch paper metadata
// ---------------------------------------------------------------------
$stmt = $db->prepare(
    "SELECT ep.*, c.course_code, c.course_title, ep.exam_date
     FROM examination_papers ep
     JOIN courses c ON ep.course_id = c.id
     WHERE ep.id = :id AND ep.department_code = :dept"
);
$stmt->execute([':id' => $paperId, ':dept' => $dept]);
$paper = $stmt->fetch();

if (!$paper) {
    header('HTTP/1.1 404 Not Found');
    echo 'Document not found.';
    exit;
}

// ---------------------------------------------------------------------
// Enforce 1‑day‑before‑exam eligibility (same rule used for printing)
// ---------------------------------------------------------------------
$examTimestamp = strtotime($paper['exam_date'] . ' 00:00:00');
$eligibleTimestamp = strtotime($paper['exam_date'] . ' -1 day 00:00:00');
if (time() < $eligibleTimestamp) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Security Restriction: Exam papers can only be viewed 1 day before the exam date.';
    exit;
}

// ---------------------------------------------------------------------
// Retrieve decrypted content (in‑memory) via helper
// ---------------------------------------------------------------------
$fileData = getDecryptedPaperContent($paperId);
if (!$fileData) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Unable to retrieve paper content.';
    exit;
}

// ---------------------------------------------------------------------
// Log the view action for audit purposes
// ---------------------------------------------------------------------
logAudit('Paper Viewed', "Exam Officer viewed paper {$paper['course_code']} (ID: $paperId)");

// ---------------------------------------------------------------------
// Stream file to browser inline (PDF or DOCX)
// ---------------------------------------------------------------------
if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: ' . $fileData['mimetype']);
header('Content-Disposition: inline; filename="' . $fileData['filename'] . '"');
header('Content-Length: ' . strlen($fileData['content']));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $fileData['content'];
exit;
?>
