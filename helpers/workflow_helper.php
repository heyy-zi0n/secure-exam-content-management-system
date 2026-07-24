<?php
/**
 * Workflow and Security Integration Helper
 * Handles Auditing, Security Logging, Notifications, Encrypted Storage, and Approvals.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security_helper.php';

// Master Key for encrypting individual file keys
if (!defined('MASTER_ENCRYPTION_KEY')) {
    define('MASTER_ENCRYPTION_KEY', 'fcit_lasu_examination_master_key_2026_secure');
}

/**
 * Log a user action to the Audit Logs
 */
function logAudit(string $action, ?string $description = null): void {
    try {
        $db = Database::getInstance();
        $user = currentUser();
        
        $userId = $user['id'] ?? null;
        $staffId = $user['staff_id'] ?? null;
        $role = $user['role'] ?? null;
        $dept = $user['department_code'] ?? null;
        
        $ip = getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Detect OS, Browser and Device roughly
        $os = "Unknown OS";
        if (preg_match('/windows|win32/i', $userAgent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent)) $os = 'macOS';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/iphone|ipad/i', $userAgent)) $os = 'iOS';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';
        
        $browser = "Unknown Browser";
        if (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/msie|trident/i', $userAgent)) $browser = 'Internet Explorer';
        elseif (preg_match('/edge/i', $userAgent)) $browser = 'Edge';
        
        $device = "Desktop";
        if (preg_match('/mobile|phone|android|iphone/i', $userAgent)) $device = 'Mobile';
        elseif (preg_match('/ipad|tablet/i', $userAgent)) $device = 'Tablet';
        
        $sessionId = session_id();
        
        $stmt = $db->prepare("
            INSERT INTO audit_logs 
            (user_id, staff_id, role, department_code, action, description, ip_address, browser, device, os, session_id) 
            VALUES (:user_id, :staff_id, :role, :department_code, :action, :description, :ip_address, :browser, :device, :os, :session_id)
        ");
        
        $stmt->execute([
            ':user_id'         => $userId,
            ':staff_id'        => $staffId,
            ':role'            => $role,
            ':department_code' => $dept,
            ':action'          => $action,
            ':description'     => $description,
            ':ip_address'      => $ip,
            ':browser'         => $browser,
            ':device'          => $device,
            ':os'              => $os,
            ':session_id'      => $sessionId
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to write audit log: " . $e->getMessage());
    }
}

/**
 * Log a security alert / event
 */
function logSecurityEvent(string $eventType, ?string $description = null, string $severity = 'low'): void {
    try {
        $db = Database::getInstance();
        $user = currentUser();
        $userId = $user['id'] ?? null;
        $ip = getClientIp();
        $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $db->prepare("
            INSERT INTO security_events 
            (event_type, description, severity, user_id, ip_address, browser) 
            VALUES (:event_type, :description, :severity, :user_id, :ip_address, :browser)
        ");
        
        $stmt->execute([
            ':event_type'  => $eventType,
            ':description' => $description,
            ':severity'    => $severity,
            ':user_id'     => $userId,
            ':ip_address'  => $ip,
            ':browser'     => $browser
        ]);
    } catch (Exception $e) {
        error_log("Failed to write security event: " . $e->getMessage());
    }
}

/**
 * Send an in-app notification to a user
 */
function sendNotification(int $userId, string $type, string $message): void {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, message, is_read) 
            VALUES (:user_id, :type, :message, 0)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':type'    => $type,
            ':message' => $message
        ]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
    }
}

/**
 * Generate Digital Approval Certificate and trigger Blind Lockdown
 */
function approvePaper(int $paperId, int $moderatorId): string {
    $db = Database::getInstance();
    $db->beginTransaction();
    try {
        // Fetch paper and details
        $stmt = $db->prepare("
            SELECT ep.*, c.course_code, c.course_title, 
                   CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS lecturer_name, 
                   u.email AS lecturer_email, d.code AS department_code
            FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            JOIN departments d ON c.department_id = d.id
            JOIN users u ON ep.created_by = u.id
            WHERE ep.id = :id
        ");
        $stmt->execute([':id' => $paperId]);
        $paper = $stmt->fetch();
        
        if (!$paper) {
            throw new Exception("Examination paper not found.");
        }
        
        $dept = $paper['department_code'];
        $approvalId = 'APR-' . date('Y') . '-' . str_pad($paperId, 5, '0', STR_PAD_LEFT);
        $approvalHash = hash('sha256', $paperId . $moderatorId . time() . 'lasu_approval_salt');
        
        // Save approval record
        $appStmt = $db->prepare("
            INSERT INTO approval_records (paper_id, moderator_id, department_code, approval_id, approval_hash)
            VALUES (:paper_id, :moderator_id, :department_code, :approval_id, :approval_hash)
            ON DUPLICATE KEY UPDATE approval_id = VALUES(approval_id), approval_hash = VALUES(approval_hash)
        ");
        
        $appStmt->execute([
            ':paper_id'        => $paperId,
            ':moderator_id'    => $moderatorId,
            ':department_code' => $dept,
            ':approval_id'     => $approvalId,
            ':approval_hash'   => $approvalHash
        ]);
        
        // Update paper status
        $upStmt = $db->prepare("
            UPDATE examination_papers 
            SET status = 'Approved', updated_at = NOW() 
            WHERE id = :id
        ");
        $upStmt->execute([':id' => $paperId]);
        
        $db->commit();
        
        // Trigger Blind Lockdown immediately
        activateBlindLockdown($paperId);
        
        // Log Audit
        logAudit('Paper Approved', "Approved course {$paper['course_code']} (ID: $paperId). Approval Certificate: $approvalId");
        
        // Notify HOD, EO, and Lecturer
        // Find HOD and Exam Officer of the course department
        $staffStmt = $db->prepare("
            SELECT u.id, r.role_code AS role 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            JOIN departments d ON u.department_id = d.id
            WHERE d.code = :dept AND r.role_code IN ('HOD', 'EXAM_OFFICER')
        ");
        $staffStmt->execute([':dept' => $dept]);
        $staff = $staffStmt->fetchAll();
        
        foreach ($staff as $s) {
            sendNotification(
                $s['id'], 
                'Blind Lockdown Activated', 
                "Examination paper for {$paper['course_code']} has been approved and placed under Blind Lockdown."
            );
        }
        
        sendNotification(
            $paper['created_by'], 
            'Paper Approved', 
            "Your examination paper for {$paper['course_code']} has been approved by the moderator. Blind Lockdown is now active."
        );
        
        return $approvalId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Encrypt file using AES-256-CBC
 */
function encryptFileContents(string $sourcePath, string $destPath, string $key): bool {
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_bytes($ivLength);
    $data = file_get_contents($sourcePath);
    if ($data === false) return false;
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) return false;
    
    // Ensure destination directory exists
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    return file_put_contents($destPath, $iv . $encrypted) !== false;
}

/**
 * Decrypt file contents in-memory
 */
function decryptFileContents(string $sourcePath, string $key): string|false {
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);
    $data = @file_get_contents($sourcePath);
    if ($data === false) return false;
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Execute Blind Lockdown Protocol
 */
function activateBlindLockdown(int $paperId): void {
    $db = Database::getInstance();
    try {
        // Fetch latest version
        $stmt = $db->prepare("
            SELECT pv.*, ep.course_id, c.course_code
            FROM paper_versions pv
            JOIN examination_papers ep ON pv.paper_id = ep.id
            JOIN courses c ON ep.course_id = c.id
            WHERE pv.paper_id = :paper_id
            ORDER BY pv.version_number DESC
            LIMIT 1
        ");
        $stmt->execute([':paper_id' => $paperId]);
        $version = $stmt->fetch();
        
        if (!$version) {
            throw new Exception("No versions found for paper ID: $paperId");
        }
        
        $tempFilePath = UPLOAD_PATH_TEMP . '/' . $version['file_path'];
        if (!file_exists($tempFilePath)) {
            throw new Exception("Temp file does not exist: $tempFilePath");
        }
        
        // Generate random AES key for this paper
        $fileKey = bin2hex(random_bytes(32));
        
        // Encrypt the random key using the master key
        $encryptedKey = openssl_encrypt($fileKey, 'aes-256-ecb', MASTER_ENCRYPTION_KEY);
        
        // Encrypt the actual file
        $encryptedFileName = $version['file_hash'] . '.enc';
        $encryptedFilePath = UPLOAD_PATH_ENCRYPTED . '/' . $encryptedFileName;
        
        $success = encryptFileContents($tempFilePath, $encryptedFilePath, $fileKey);
        if (!$success) {
            throw new Exception("File encryption failed.");
        }
        
        // Save lockdown record
        $user = currentUser();
        $userId = $user['id'] ?? null;
        
        $lockStmt = $db->prepare("
            INSERT INTO blind_lockdowns (paper_id, locked_by, encryption_status, encryption_key_ref)
            VALUES (:paper_id, :locked_by, 'encrypted', :key_ref)
            ON DUPLICATE KEY UPDATE 
                locked_by = VALUES(locked_by),
                encryption_status = 'encrypted',
                encryption_key_ref = VALUES(encryption_key_ref),
                lockdown_time = NOW()
        ");
        $lockStmt->execute([
            ':paper_id'  => $paperId,
            ':locked_by' => $userId,
            ':key_ref'   => $encryptedKey
        ]);
        
        // Update paper status to Blind Lockdown Activated
        $upStmt = $db->prepare("
            UPDATE examination_papers 
            SET status = 'Blind Lockdown Activated', updated_at = NOW() 
            WHERE id = :id
        ");
        $upStmt->execute([':id' => $paperId]);
        
        // Auto-add to print queue
        $queueStmt = $db->prepare("
            INSERT INTO print_queue (paper_id, status)
            VALUES (:paper_id, 'Ready')
            ON DUPLICATE KEY UPDATE status = 'Ready'
        ");
        $queueStmt->execute([':paper_id' => $paperId]);
        
        // Log auditing & security
        logAudit('Blind Lockdown Activated', "Paper locked and encrypted for course {$version['course_code']} (ID: $paperId). Version: {$version['version_number']}");
        logSecurityEvent('BLIND_LOCKDOWN_ACTIVATED', "Paper ID: $paperId encrypted successfully.", 'medium');
        
    } catch (Exception $e) {
        logSecurityEvent('BLIND_LOCKDOWN_FAILED', "Encryption failed for Paper ID: $paperId. Error: " . $e->getMessage(), 'critical');
        throw $e;
    }
}

/**
 * Request Emergency Unlock (HOD Only)
 */
function emergencyUnlockPaper(int $paperId, string $reason, int $hodId): void {
    $db = Database::getInstance();
    try {
        // Fetch paper details
        $stmt = $db->prepare("
            SELECT ep.*, c.course_code 
            FROM examination_papers ep
            JOIN courses c ON ep.course_id = c.id
            WHERE ep.id = :id
        ");
        $stmt->execute([':id' => $paperId]);
        $paper = $stmt->fetch();
        
        if (!$paper) {
            throw new Exception("Paper not found.");
        }
        
        // Delete lockdown record
        $delStmt = $db->prepare("DELETE FROM blind_lockdowns WHERE paper_id = :paper_id");
        $delStmt->execute([':paper_id' => $paperId]);
        
        // Update status to Correction Requested
        $upStmt = $db->prepare("
            UPDATE examination_papers 
            SET status = 'Correction Requested', updated_at = NOW() 
            WHERE id = :id
        ");
        $upStmt->execute([':id' => $paperId]);
        
        // Remove from print queue
        $qStmt = $db->prepare("DELETE FROM print_queue WHERE paper_id = :paper_id");
        $qStmt->execute([':paper_id' => $paperId]);
        
        // Audit Logs
        logAudit('Emergency Unlock', "Paper unlocked for {$paper['course_code']} (ID: $paperId). Reason: $reason");
        logSecurityEvent('EMERGENCY_UNLOCK', "HOD (ID: $hodId) unlocked Paper ID: $paperId. Reason: $reason", 'high');
        
        // Notify Lecturer
        sendNotification(
            $paper['created_by'],
            'Emergency Unlock Activated',
            "Your HOD has activated an Emergency Unlock for {$paper['course_code']} to allow corrections. Reason: $reason"
        );
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Temporarily decrypt and retrieve paper content (For secure viewer or printing)
 */
function getDecryptedPaperContent(int $paperId): array|false {
    $db = Database::getInstance();
    try {
        // Get lockdown details
        $stmt = $db->prepare("
            SELECT bl.*, pv.file_path, pv.file_hash, pv.original_filename
            FROM blind_lockdowns bl
            JOIN paper_versions pv ON bl.paper_id = pv.paper_id
            WHERE bl.paper_id = :paper_id
            ORDER BY pv.version_number DESC
            LIMIT 1
        ");
        $stmt->execute([':paper_id' => $paperId]);
        $lockdown = $stmt->fetch();
        
        if (!$lockdown) {
            // Paper is not locked down yet (might still be in review), stream from temp storage
            $tempStmt = $db->prepare("
                SELECT pv.file_path, pv.original_filename
                FROM paper_versions pv
                WHERE pv.paper_id = :paper_id
                ORDER BY pv.version_number DESC
                LIMIT 1
            ");
            $tempStmt->execute([':paper_id' => $paperId]);
            $version = $tempStmt->fetch();
            if ($version) {
                $tempPath = UPLOAD_PATH_TEMP . '/' . $version['file_path'];
                if (file_exists($tempPath)) {
                    return [
                        'content' => file_get_contents($tempPath),
                        'filename' => $version['original_filename'],
                        'mimetype' => str_ends_with($version['original_filename'], '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];
                }
            }
            return false;
        }
        
        $encryptedFilePath = UPLOAD_PATH_ENCRYPTED . '/' . $lockdown['file_hash'] . '.enc';
        if (!file_exists($encryptedFilePath)) {
            return false;
        }
        
        // Decrypt the file key
        $encryptedKey = $lockdown['encryption_key_ref'];
        $fileKey = openssl_decrypt($encryptedKey, 'aes-256-ecb', MASTER_ENCRYPTION_KEY);
        
        // Decrypt file contents
        $content = decryptFileContents($encryptedFilePath, $fileKey);
        if ($content === false) {
            return false;
        }
        
        return [
            'content' => $content,
            'filename' => $lockdown['original_filename'],
            'mimetype' => str_ends_with($lockdown['original_filename'], '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check HOD Delegation status for a department
 */
function checkDelegation(string $departmentCode): ?array {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT hd.* FROM hod_delegations hd
            JOIN departments d ON hd.department_id = d.id
            WHERE d.code = :dept 
              AND hd.status = 'Active' 
              AND hd.start_date <= CURDATE() 
              AND hd.end_date >= CURDATE()
            LIMIT 1
        ");
        $stmt->execute([':dept' => $departmentCode]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}
