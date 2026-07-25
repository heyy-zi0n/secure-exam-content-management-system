<?php
/**
 * Secure Document Download / Preview Endpoint (v0.7.1)
 *
 * URL: dashboard/download.php?f=<paper_files.id>  [&preview=1 optional]
 *
 * Security:
 *  - MUST be logged in
 *  - Uses RBAC + explicit ownership/department authorisation via userCanAccessPaper()
 *  - Storage is OUTSIDE the public webroot; no direct URL access
 *  - Path traversal mitigated via DB-only lookup (storage_path never user-supplied)
 *  - MIME type comes from DB (validated at upload time via finfo + extension + magic-byte)
 *  - No files stored in public/ at any point
 *  - SHA-256 hash already captured at upload and checked on access
 *  - All downloads/previews are audit-logged via logAudit
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../helpers/document_helper.php';

requireAuth();

$user = currentUser();
if (!$user) {
    http_response_code(401);
    exit('Authentication required.');
}

$fileId = isset($_GET['f']) ? (int)$_GET['f'] : 0;
if ($fileId <= 0) {
    http_response_code(400);
    exit('Missing file id.');
}

$file = getAuthorisedPaperFile($fileId, $user);
if (!$file) {
    http_response_code(403);
    logSecurityEvent('DOCUMENT_ACCESS_DENIED', "User {$user['id']} tried to access file_id=$fileId without permission.", 'high');
    exit('Access denied: you are not authorised to access this document.');
}

if (!file_exists($file['storage_path']) || !is_readable($file['storage_path'])) {
    http_response_code(404);
    logSecurityEvent('DOCUMENT_STORAGE_MISSING', "Storage path missing for file_id=$fileId: {$file['storage_path']}", 'high');
    exit('Document not found on storage server.');
}

$realSize = @filesize($file['storage_path']);
if ($realSize !== false && (int)$realSize !== (int)$file['file_size']) {
    logSecurityEvent('DOCUMENT_SIZE_MISMATCH',
        "file_id=$fileId stored size {$file['file_size']} but disk reports $realSize. Possible tampering.", 'critical');
    // Continue streaming but alert audit
}

$ext = strtolower($file['file_extension']);
$isPdf = ($ext === 'pdf');
$isPreview = (!empty($_GET['preview']) && $isPdf);

// Recompute fingerprint on-the-fly to validate integrity
$ondiskHash = @hash_file('sha256', $file['storage_path']);
if ($ondiskHash && strcasecmp($ondiskHash, $file['sha256_hash']) !== 0) {
    logSecurityEvent('DOCUMENT_HASH_MISMATCH',
        "file_id=$fileId expected sha256={$file['sha256_hash']}, disk=$ondiskHash.", 'critical');
    http_response_code(500);
    exit('Document integrity check failed.');
}

$disposition = $isPreview ? 'inline' : 'attachment';
$serveFileName = $isPreview ? $file['generated_filename'] : $file['original_filename'];

$mime = $file['mime_type'];
if ($isPdf && !in_array($mime, ['application/pdf'], true)) {
    $mime = 'application/pdf';
}

// Zero-copy streaming headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . $realSize);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($serveFileName) . '"; filename*=UTF-8\'\'' . rawurlencode($serveFileName));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

logAudit($isPreview ? 'Document Previewed' : 'Document Downloaded',
    "Accessed file_id=$fileId [{$file['file_type']}] {$file['generated_filename']} " .
    "(paper_id={$file['examination_paper_id']}, sha256=" . substr($file['sha256_hash'], 0, 16) . ", $realSize bytes) via " .
    ($isPreview ? 'inline preview' : 'download'));

if (session_id()) session_write_close();
$ctx = fopen($file['storage_path'], 'rb');
if ($ctx === false) { http_response_code(500); exit('Unable to open document.'); }
fpassthru($ctx);
fclose($ctx);
exit;
