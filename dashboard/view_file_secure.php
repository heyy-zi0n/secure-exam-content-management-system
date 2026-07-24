<?php
/**
 * Secure Document Viewer Shell
 * Renders the protected preview container with pointer intercepts and dynamic watermark overlays.
 */

$noAuthRequired = false; // Requires active login
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../helpers/workflow_helper.php';

requireAuth();

$db = Database::getInstance();
$user = currentUser();
$paperId = (int)($_GET['id'] ?? 0);

// Fetch paper details
$stmt = $db->prepare("
    SELECT ep.*, c.course_code, c.course_title, c.level, pv.original_filename
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN paper_versions pv ON ep.id = pv.paper_id
    WHERE ep.id = :id
    ORDER BY pv.version_number DESC
    LIMIT 1
");
$stmt->execute([':id' => $paperId]);
$paper = $stmt->fetch();

if (!$paper) {
    die("Error: Document not found.");
}

// 1. Enforce strict authorization checks
$authSuccess = false;
$role = $user['role'];
$userDept = $user['department_code'];
$paperDept = $paper['department_code'];

if ($role === 'admin') {
    $authSuccess = true;
} elseif ($role === 'hod') {
    // HODs can only view papers belonging to their department
    if ($userDept === $paperDept) {
        $authSuccess = true;
    }
} elseif ($role === 'exam_officer') {
    // Exam Officers can only view approved papers in their department
    $approvedStates = ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived'];
    if ($userDept === $paperDept && in_array($paper['status'], $approvedStates)) {
        $authSuccess = true;
    }
} elseif ($role === 'moderator') {
    // Moderators can only view papers inside their department that match their assigned levels
    // Let's check moderator level assignments
    $modCheck = $db->prepare("
        SELECT id FROM moderator_level_assignments 
        WHERE moderator_id = :mod_id AND department_code = :dept AND level = :level AND status = 'active'
        LIMIT 1
    ");
    // Wait, moderator assignments might have status active, let's check table definition. It has status active? No, we didn't add status, we had unique key and academic_session. Let's just check department, level, and session.
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
    // Lecturers can only view papers they created
    if ((int)$paper['created_by'] === (int)$user['id']) {
        // Lecturer viewing permissions are revoked during Blind Lockdown!
        $lockedStates = ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived'];
        if (!in_array($paper['status'], $lockedStates)) {
            $authSuccess = true;
        }
    }
}

if (!$authSuccess) {
    logSecurityEvent('UNAUTHORIZED_VIEW_ATTEMPT', "User ID {$user['id']} attempted to view Paper ID $paperId", 'high');
    die("Access Denied: You do not have permissions to view this document.");
}

// Generate watermark text dynamically
$watermarkText = sprintf(
    "CONFIDENTIAL - LASU FCIT | %s (%s) | Dept: %s | Course: %s | Date: %s | IP: %s | Session: %s",
    $user['full_name'],
    strtoupper($role),
    $userDept,
    $paper['course_code'],
    date('Y-m-d H:i:s'),
    getClientIp(),
    session_id()
);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <title>Secure Viewer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Transparent click-jacking overlay blocking clicks */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            background: transparent;
            pointer-events: auto; /* Block interaction with elements below */
        }
        
        /* Repeated diagonal watermark overlay */
        .watermark-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            pointer-events: none; /* Let overlay catch clicks, not this */
            opacity: 0.15;
            overflow: hidden;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-content: space-around;
        }

        .watermark-text {
            font-size: 11px;
            font-family: 'Courier New', monospace;
            font-weight: 800;
            color: #0f172a;
            transform: rotate(-30deg);
            white-space: nowrap;
            padding: 80px;
            user-select: none;
        }
    </style>
</head>
<body class="h-full bg-slate-900 overflow-hidden relative select-none">

    <!-- Transparent barrier overlay that captures all clicks -->
    <div class="overlay" id="barrier"></div>

    <!-- Repeating Watermark Overlay -->
    <div class="watermark-layer">
        <?php for ($i = 0; $i < 12; $i++): ?>
            <div class="watermark-text"><?= htmlspecialchars($watermarkText) ?></div>
        <?php endfor; ?>
    </div>

    <!-- Document Embed (Streams the Decrypted File safely) -->
    <div class="w-full h-full">
        <?php
        // Determine file stream source url
        $streamUrl = url('dashboard/serve_pdf.php?id=' . $paperId);
        ?>
        <object data="<?= $streamUrl ?>" type="application/pdf" class="w-full h-full z-0">
            <embed src="<?= $streamUrl ?>" type="application/pdf" class="w-full h-full" />
        </object>
    </div>

    <!-- Secure Deterrent Scripts -->
    <script>
        // Disable Right Click inside this iframe/window
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            return false;
        });

        // Intercept hotkeys
        document.addEventListener('keydown', (e) => {
            // Check for Ctrl/Cmd combinations
            const ctrlOrCmd = e.ctrlKey || e.metaKey;
            
            if (ctrlOrCmd) {
                switch(e.key.toLowerCase()) {
                    case 's': // Save
                    case 'p': // Print
                    case 'c': // Copy
                    case 'a': // Select All
                    case 'u': // View Source
                        e.preventDefault();
                        e.stopPropagation();
                        alert("Security restriction: Printing, downloading, selecting and copying text is disabled.");
                        return false;
                }
            }

            // Intercept Function Keys
            if (e.key === 'F12') {
                e.preventDefault();
                return false;
            }
        });

        // Intercept mouse drag selections
        document.addEventListener('selectstart', (e) => {
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>
