<?php
/**
 * FCIT Secure Examination CMS - v0.7.1
 * Secure Examination Document Management Helper
 *
 * Responsibilities:
 *  - Paper version creation (with version uniqueness enforced by DB)
 *  - Upload validation (extension / MIME / size / PHP upload-error / ownership / duplicate hash)
 *  - Secure storage (storage/examinations/{drafts,submitted,approved,archive})
 *  - Enterprise canonical filename generation
 *  - SHA-256 fingerprinting
 *  - Audit-log generation for every upload / delete / replace
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security_helper.php';
require_once __DIR__ . '/workflow_helper.php';

/**
 * Ensure examination storage directories exist; return the proper bucket for a status.
 */
function examStorageBucket(string $status): string {
    switch ($status) {
        case 'Submitted': return EXAM_STORAGE_PATH_SUBMIT;
        case 'Approved':  return EXAM_STORAGE_PATH_APPRV;
        case 'Archived':  return EXAM_STORAGE_PATH_ARCH;
        case 'Draft':
        case 'Returned':
        case 'Rejected':
        default:          return EXAM_STORAGE_PATH_DRAFT;
    }
}

/**
 * Build an enterprise canonical filename:
 *   CSC401_2025-2026_FirstSemester_FinalExam_V2_QUESTION.docx
 */
function generateExamFilename(
    string $courseCode,
    string $academicSession,
    string $semester,
    string $examinationType,
    int    $version,
    string $fileType,
    string $extension
): string {
    $slugify = static fn (string $s): string => (string)preg_replace(
        '/[^A-Za-z0-9]+/', '', ucwords(str_replace(['/', '-'], ' ', $s))
    );

    $typeAbbr = strtoupper((string)preg_replace('/[^A-Z]/', '',
        str_replace(['Question Paper','Marking Scheme','Practical Resources','Additional Instructions'],
                    ['QUESTION','MARKING','PRACTICAL','INSTRUCTIONS'], $fileType)));
    if ($typeAbbr === '') $typeAbbr = 'DOC';

    $examAbbr = $slugify($examinationType);

    $base = sprintf(
        '%s_%s_%s_%s_V%d_%s',
        strtoupper($slugify($courseCode)),
        str_replace('/', '-', trim($academicSession)),
        $slugify($semester),
        $examAbbr,
        $version,
        $typeAbbr
    );

    return $base . '.' . strtolower(ltrim($extension, '.'));
}

/**
 * Validates a single $_FILES entry against enterprise constraints.
 * Returns [ 'valid' => bool, 'error' => string, 'file' => ['ext','mime','size','sha256','tmp_name','name'] ]
 */
function validateExamUpload(array $fileEntry): array {
    $phpErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL    => 'The upload was only partially completed.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];

    if (!isset($fileEntry['error']) || !is_int($fileEntry['error'])) {
        return ['valid' => false, 'error' => 'Invalid file entry structure.', 'file' => null];
    }
    if ($fileEntry['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'error' => $phpErrors[$fileEntry['error']] ?? "Upload error #{$fileEntry['error']}",
            'file'  => null
        ];
    }
    if (!is_uploaded_file($fileEntry['tmp_name'] ?? '')) {
        return ['valid' => false, 'error' => 'Attack detected: not a valid HTTP-uploaded file.', 'file' => null];
    }

    $size = (int)($fileEntry['size'] ?? 0);
    if ($size <= 0) return ['valid' => false, 'error' => 'Uploaded file is empty.', 'file' => null];
    if ($size > MAX_FILE_SIZE) {
        $mb = round(MAX_FILE_SIZE / 1024 / 1024, 1);
        return ['valid' => false, 'error' => "File too large. Maximum {$mb} MB.", 'file' => null];
    }

    $origName = (string)($fileEntry['name'] ?? '');
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [
            'valid' => false,
            'error' => 'Disallowed file extension. Only ' . implode(', ', ALLOWED_EXTENSIONS) . ' permitted.',
            'file'  => null
        ];
    }

    // Real MIME detection via finfo (not trusted $_FILES['type'])
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = (string)$finfo->file($fileEntry['tmp_name']);
    $clientMime = (string)($fileEntry['type'] ?? '');

    $allowed = ALLOWED_MIME_TYPES;
    $mimeOk = in_array($realMime, $allowed, true) || in_array($clientMime, $allowed, true);
    // Docx/Zip share MIME; cross-check extension + magic bytes
    if (!$mimeOk) {
        // Secondary sanity: DOCX/ZIP begin with PK\x03\x04; PDF with %PDF-
        $head = (string)@file_get_contents($fileEntry['tmp_name'], false, null, 0, 8);
        if ($ext === 'pdf'  && str_starts_with($head, '%PDF-'))        $mimeOk = true;
        if (in_array($ext, ['docx','zip'], true) && str_starts_with($head, 'PK')) $mimeOk = true;
    }
    if (!$mimeOk) {
        return ['valid' => false, 'error' => "Disallowed MIME type (detected: $realMime).", 'file' => null];
    }

    $sha = hash_file('sha256', $fileEntry['tmp_name']);
    if ($sha === false || strlen($sha) !== 64) {
        return ['valid' => false, 'error' => 'Unable to compute file fingerprint.', 'file' => null];
    }

    return [
        'valid' => true,
        'error' => '',
        'file'  => [
            'tmp_name'   => $fileEntry['tmp_name'],
            'name'       => $origName,
            'ext'        => $ext,
            'mime'       => $realMime,
            'size'       => $size,
            'sha256'     => $sha,
        ]
    ];
}

/**
 * Look up contextual fields for a paper so filenames can be generated.
 */
function getPaperContext(int $examinationPaperId): array|false {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT ep.id AS paper_id,
               ep.current_version,
               ep.submission_status,
               ep.examination_type,
               c.course_code,
               c.course_title,
               s.session_name,
               sem.semester_name
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        JOIN academic_sessions s ON ep.academic_session_id = s.id
        JOIN semesters sem ON ep.semester_id = sem.id
        WHERE ep.id = ?
        LIMIT 1
    ");
    $stmt->execute([$examinationPaperId]);
    return $stmt->fetch() ?: false;
}

/**
 * Verify a user owns (or is authorised for) a paper.
 * Lecturers => must own the paper; HOD/Exam Officer/Moderator/Admin => paper within their department.
 */
function userCanAccessPaper(int $examinationPaperId, array $user): bool {
    $db = Database::getInstance();
    $role = $user['role_code'] ?? ($user['role'] ?? '');
    $stmt = $db->prepare("
        SELECT ep.lecturer_id, c.department_id, u.department_id AS user_dept_id
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        LEFT JOIN users u ON u.id = ?
        WHERE ep.id = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $examinationPaperId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if (in_array($role, ['SYSTEM_ADMIN','ADMIN','admin'], true)) return true;
    if ((int)$row['lecturer_id'] === (int)$user['id'])           return true;

    // Cross-departmental access for HOD/Exam Officer/Moderator
    if (in_array($role, ['HOD','EXAM_OFFICER','MODERATOR','moderator','hod','exam_officer'], true)) {
        return (int)$row['department_id'] === (int)($row['user_dept_id'] ?? 0);
    }
    return false;
}

/**
 * Retrieve (or create) a paper_version row for a given paper and version.
 * Status, if provided, is applied.
 */
function upsertPaperVersion(
    int $examinationPaperId,
    int $version,
    int $createdBy,
    string $submissionStatus = 'Draft',
    ?string $changeNotes = null
): int {
    $db = Database::getInstance();
    $select = $db->prepare("
        SELECT id FROM paper_versions
        WHERE examination_paper_id = ? AND version_number = ? LIMIT 1
    ");
    $select->execute([$examinationPaperId, $version]);
    $id = (int)$select->fetchColumn();
    if ($id > 0) {
        $up = $db->prepare("
            UPDATE paper_versions
            SET submission_status = ?,
                created_by        = COALESCE(NULLIF(?, 0), created_by),
                change_notes      = COALESCE(?, change_notes)
            WHERE id = ?
        ");
        $up->execute([$submissionStatus, $createdBy, $changeNotes, $id]);
        return $id;
    }

    $ins = $db->prepare("
        INSERT INTO paper_versions
        (examination_paper_id, version_number, created_by, submission_status, change_notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$examinationPaperId, $version, $createdBy, $submissionStatus, $changeNotes]);
    return (int)$db->lastInsertId();
}

/**
 * Upload one document to a paper version (replace=false) OR replace one of same type (replace=true).
 * Enforces duplicate-hash rejection per (version + type) UNIQUE key.
 * Returns array: [ 'ok' => bool, 'file_id' => int|null, 'error' => string ]
 */
function uploadPaperDocument(
    int    $examinationPaperId,
    int    $version,
    string $fileType,
    array  $validatedFile,
    int    $uploadedBy,
    bool   $replace = false,
    ?string $changeNotes = null
): array {
    $db = Database::getInstance();

    if (!in_array($fileType, PAPER_FILE_TYPES, true)) {
        return ['ok' => false, 'file_id' => null, 'error' => 'Invalid document category.'];
    }
    if (!($validatedFile['valid'] ?? false) || empty($validatedFile['file'])) {
        return ['ok' => false, 'file_id' => null, 'error' => $validatedFile['error'] ?? 'File failed validation.'];
    }
    $file = $validatedFile['file'];

    $context = getPaperContext($examinationPaperId);
    if ($context === false) {
        return ['ok' => false, 'file_id' => null, 'error' => 'Examination paper not found.'];
    }

    $db->beginTransaction();
    try {
        // Ensure the paper_version row exists
        $versionId = upsertPaperVersion(
            $examinationPaperId,
            $version,
            $uploadedBy,
            (string)($context['submission_status'] ?? 'Draft'),
            $changeNotes
        );

        // Duplicate hash check within same version
        $dupStmt = $db->prepare("
            SELECT id FROM paper_files
            WHERE paper_version_id = ? AND file_type = ? AND sha256_hash = ? LIMIT 1
        ");
        $dupStmt->execute([$versionId, $fileType, $file['sha256']]);
        $existingFileId = (int)$dupStmt->fetchColumn();
        if ($existingFileId > 0 && !$replace) {
            $db->rollBack();
            return ['ok' => false, 'file_id' => $existingFileId, 'error' => 'Duplicate upload rejected: identical file already exists for this version and category.'];
        }

        // Build storage location & filename
        $status = (string)($context['submission_status'] ?? 'Draft');
        $bucket = examStorageBucket($status);
        if (!is_dir($bucket)) {
            @mkdir($bucket, 0777, true);
            if (!file_exists(EXAM_STORAGE_PATH . '/.htaccess')) {
                @file_put_contents(EXAM_STORAGE_PATH . '/.htaccess', "Require all denied\nOptions -Indexes\n");
            }
        }

        $generatedName = generateExamFilename(
            (string)$context['course_code'],
            (string)$context['session_name'],
            (string)$context['semester_name'],
            (string)$context['examination_type'],
            $version,
            $fileType,
            $file['ext']
        );
        // Resolve collisions by appending short hash suffix
        $baseName = pathinfo($generatedName, PATHINFO_FILENAME);
        $ext      = $file['ext'];
        $finalGen = $generatedName;
        $i = 0;
        while (file_exists($bucket . '/' . $finalGen) && $i < 50) {
            $suffix   = substr($file['sha256'], $i * 2, 6);
            $finalGen = "{$baseName}_{$suffix}.{$ext}";
            $i++;
        }
        $storagePath = $bucket . '/' . $finalGen;

        // Move (copy-on-write safe, with chmod)
        if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
            throw new Exception('Failed to move uploaded file to secure storage.');
        }
        @chmod($storagePath, 0640);

        if ($replace) {
            // Delete previous file(s) of this type for the same version
            $delStmt = $db->prepare("
                SELECT id, storage_path FROM paper_files
                WHERE paper_version_id = ? AND file_type = ?
            ");
            $delStmt->execute([$versionId, $fileType]);
            foreach ($delStmt->fetchAll() as $old) {
                if (file_exists($old['storage_path'])) @unlink($old['storage_path']);
                $db->prepare("DELETE FROM paper_files WHERE id = ?")->execute([$old['id']]);
                logAudit('Document Replaced', "Replaced $fileType for paper #$examinationPaperId v$version (file_id: {$old['id']}). New fingerprint: " . substr($file['sha256'], 0, 12));
            }
        }

        $insFile = $db->prepare("
            INSERT INTO paper_files
            (paper_version_id, file_type, original_filename, generated_filename, file_extension,
             mime_type, file_size, sha256_hash, storage_path, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insFile->execute([
            $versionId,
            $fileType,
            $file['name'],
            $finalGen,
            $ext,
            $file['mime'],
            $file['size'],
            $file['sha256'],
            $storagePath,
            $uploadedBy,
        ]);
        $fileId = (int)$db->lastInsertId();

        $db->commit();

        logAudit($replace ? 'Document Replaced' : 'Document Uploaded',
            "Uploaded $fileType for paper #$examinationPaperId v$version. " .
            "Original: {$file['name']} → Generated: $finalGen | Fingerprint: " . substr($file['sha256'], 0, 16));

        return ['ok' => true, 'file_id' => $fileId, 'error' => ''];
    } catch (Throwable $e) {
        try { $db->rollBack(); } catch (Throwable) {}
        if (isset($storagePath) && file_exists($storagePath)) @unlink($storagePath);
        return ['ok' => false, 'file_id' => null, 'error' => $e->getMessage()];
    }
}

/**
 * List every paper version + its files for a given paper id (latest first).
 */
function getPaperVersionsWithFiles(int $examinationPaperId): array {
    $db = Database::getInstance();
    $vStmt = $db->prepare("
        SELECT pv.*, CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS creator_name
        FROM paper_versions pv
        LEFT JOIN users u ON pv.created_by = u.id
        WHERE pv.examination_paper_id = ?
        ORDER BY pv.version_number DESC
    ");
    $vStmt->execute([$examinationPaperId]);
    $versions = $vStmt->fetchAll();

    $fStmt = $db->prepare("
        SELECT pf.*, CONCAT_WS(' ', up.first_name, up.middle_name, up.last_name) AS uploader_name
        FROM paper_files pf
        LEFT JOIN users up ON pf.uploaded_by = up.id
        WHERE pf.paper_version_id = ?
        ORDER BY FIELD(pf.file_type, 'Question Paper','Marking Scheme','Practical Resources','Additional Instructions'), pf.uploaded_at ASC
    ");
    foreach ($versions as &$v) {
        $fStmt->execute([$v['id']]);
        $v['files'] = $fStmt->fetchAll();
    }
    return $versions;
}

/**
 * Delete a paper_file by id.  Allowed only when the owning paper version's status is Draft/Returned.
 */
function deletePaperFile(int $fileId, int $userId): array {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT pf.id, pf.storage_path, pf.file_type, pf.generated_filename,
               pv.submission_status, pv.examination_paper_id, pv.version_number,
               ep.lecturer_id
        FROM paper_files pf
        JOIN paper_versions pv ON pf.paper_version_id = pv.id
        JOIN examination_papers ep ON pv.examination_paper_id = ep.id
        WHERE pf.id = ? LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'Document not found.'];

    if ((int)$row['lecturer_id'] !== $userId && !(($GLOBALS['_SESSION_'] ?? [])['role_code'] ?? '') === 'SYSTEM_ADMIN') {
        // Re-check user access via the explicit helper
        $user = currentUser();
        if (!userCanAccessPaper((int)$row['examination_paper_id'], $user ?: ['id' => $userId, 'role_code' => ''])) {
            return ['ok' => false, 'error' => 'Access denied: you are not the owner of this document.'];
        }
        if ((int)$row['lecturer_id'] !== (int)($user['id'] ?? $userId)) {
            return ['ok' => false, 'error' => 'Only the owning lecturer may delete a document.'];
        }
    }
    if (!in_array($row['submission_status'], ['Draft', 'Returned'], true)) {
        return ['ok' => false, 'error' => "Document cannot be deleted while status is {$row['submission_status']}."];
    }

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM paper_files WHERE id = ?")->execute([$fileId]);
        if (file_exists($row['storage_path'])) @unlink($row['storage_path']);
        $db->commit();

        logAudit('Document Deleted',
            "Deleted {$row['file_type']} [{$row['generated_filename']}] from paper #{$row['examination_paper_id']} v{$row['version_number']}.");

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        try { $db->rollBack(); } catch (Throwable) {}
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Retrieve file details with ownership/auth check; returns row or null.
 */
function getAuthorisedPaperFile(int $fileId, array $user): array|null {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT pf.*, pv.examination_paper_id, pv.submission_status AS version_status
        FROM paper_files pf
        JOIN paper_versions pv ON pf.paper_version_id = pv.id
        WHERE pf.id = ? LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $row = $stmt->fetch() ?: null;
    if (!$row) return null;
    if (!userCanAccessPaper((int)$row['examination_paper_id'], $user)) return null;
    return $row;
}

/**
 * Move a file between status buckets (e.g. Draft → Submitted → Approved → Archive)
 */
function relocateFileToStatusBucket(int $fileId, string $newStatus): bool {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM paper_files WHERE id = ? LIMIT 1");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file) return false;

    $targetBucket = examStorageBucket($newStatus);
    if (!is_dir($targetBucket)) @mkdir($targetBucket, 0777, true);
    $targetPath = $targetBucket . '/' . $file['generated_filename'];
    if (realpath($file['storage_path']) === realpath($targetPath)) return true;

    if (!@rename($file['storage_path'], $targetPath)) {
        // Fallback to copy + unlink for cross-device moves
        if (!@copy($file['storage_path'], $targetPath)) return false;
        @unlink($file['storage_path']);
    }
    $db->prepare("UPDATE paper_files SET storage_path = ? WHERE id = ?")
       ->execute([$targetPath, $fileId]);
    return true;
}
