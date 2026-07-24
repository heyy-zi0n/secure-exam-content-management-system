<?php
$pageTitle = "Examination Paper Lifecycle";
$breadcrumbs = ['Lecturer Workspace' => 'dashboard/lecturer/index.php', 'Submissions' => 'dashboard/lecturer/submissions.php', 'Paper Details' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$paperId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Fetch paper details
$stmt = $db->prepare("
    SELECT ep.*, c.course_code, c.course_title, c.level, u.full_name as moderator_name, s.name as session_name, sem.name as semester_name, setts.submission_deadline
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN users u ON ep.assigned_moderator_id = u.id
    JOIN academic_sessions s ON ep.academic_session_id = s.id
    JOIN semesters sem ON ep.semester_id = sem.id
    JOIN system_settings setts ON ep.department_code = setts.department_code
    WHERE ep.id = :id AND ep.created_by = :lecturer_id
    LIMIT 1
");
$stmt->execute([
    ':id' => $paperId,
    ':lecturer_id' => $user['id']
]);
$paper = $stmt->fetch();

if (!$paper) {
    echo "<div class='p-6 bg-red-100 text-red-800 rounded-xl'>Paper not found or unauthorized access.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$isLocked = in_array($paper['status'], ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived']);

// Handle post of replies to comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } elseif ($isLocked) {
        $error = "Paper is locked down. Comments are closed.";
    } else {
        $commentText = sanitizeInput($_POST['comment_text'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if (empty($commentText)) {
            $error = "Comment text cannot be empty.";
        } else {
            // Get latest version ID for referencing
            $verStmt = $db->prepare("SELECT id FROM paper_versions WHERE paper_id = :paper_id ORDER BY version_number DESC LIMIT 1");
            $verStmt->execute([':paper_id' => $paperId]);
            $verId = $verStmt->fetchColumn();
            
            $cmtInsert = $db->prepare("
                INSERT INTO review_comments (paper_id, version_id, moderator_id, comment_type, comment_text, parent_id)
                VALUES (:paper_id, :version_id, :moderator_id, 'general', :comment_text, :parent_id)
            ");
            $cmtInsert->execute([
                ':paper_id' => $paperId,
                ':version_id' => $verId,
                ':moderator_id' => $user['id'], // Storing user ID as the sender
                ':comment_text' => $commentText,
                ':parent_id' => $parentId
            ]);
            
            logAudit('Comment Added', "Added reply to comment on paper ID $paperId");
            
            // Notify Moderator
            sendNotification($paper['assigned_moderator_id'], 'Correction Requested', "Lecturer replied to comments on {$paper['course_code']}");
            
            $success = "Comment posted successfully.";
        }
    }
}

// Handle upload of revision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_revision') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } elseif ($isLocked) {
        $error = "Paper is locked down. Revisions are disabled.";
    } elseif ($paper['status'] !== 'Correction Requested') {
        $error = "Revisions can only be submitted if correction is requested.";
    } else {
        $revisionReason = sanitizeInput($_POST['revision_reason'] ?? '');
        $paperFile = $_FILES['exam_paper'] ?? null;
        $guideFile = $_FILES['marking_guide'] ?? null;
        $supportFile = $_FILES['supporting_files'] ?? null;
        
        if (empty($revisionReason)) {
            $error = "Please state the reason for this revision.";
        } elseif (!$paperFile || $paperFile['error'] === UPLOAD_ERR_NO_FILE) {
            $error = "Please upload the revised examination paper.";
        } else {
            // Check deadline
            $isPastDeadline = ($paper['submission_deadline'] && strtotime(date('Y-m-d')) > strtotime($paper['submission_deadline']));
            
            $paperName = $paperFile['name'];
            $paperSize = $paperFile['size'];
            $paperTmp = $paperFile['tmp_name'];
            $paperExt = strtolower(pathinfo($paperName, PATHINFO_EXTENSION));
            $paperMime = mime_content_type($paperTmp);
            
            if (!in_array($paperExt, ALLOWED_EXTENSIONS) || !in_array($paperMime, ALLOWED_MIME_TYPES)) {
                $error = "Invalid file type. Only PDF and DOCX files are allowed.";
            } elseif ($paperSize > MAX_FILE_SIZE) {
                $error = "File size is too large (max 20MB).";
            } else {
                $paperRandomName = bin2hex(random_bytes(16)) . '.' . $paperExt;
                $paperPath = UPLOAD_PATH_TEMP . '/' . $paperRandomName;
                
                if (move_uploaded_file($paperTmp, $paperPath)) {
                    
                    // Handle guide
                    $guideRandomName = null;
                    $guideOrigName = null;
                    if ($guideFile && $guideFile['error'] === UPLOAD_ERR_OK) {
                        $guideExt = strtolower(pathinfo($guideFile['name'], PATHINFO_EXTENSION));
                        $guideMime = mime_content_type($guideFile['tmp_name']);
                        if (in_array($guideExt, ALLOWED_EXTENSIONS) && in_array($guideMime, ALLOWED_MIME_TYPES) && $guideFile['size'] <= MAX_FILE_SIZE) {
                            $guideRandomName = bin2hex(random_bytes(16)) . '.' . $guideExt;
                            move_uploaded_file($guideFile['tmp_name'], UPLOAD_PATH_TEMP . '/' . $guideRandomName);
                            $guideOrigName = $guideFile['name'];
                        }
                    }
                    
                    // Handle support files
                    $supportRandomName = null;
                    $supportOrigName = null;
                    if ($supportFile && $supportFile['error'] === UPLOAD_ERR_OK) {
                        $supportExt = strtolower(pathinfo($supportFile['name'], PATHINFO_EXTENSION));
                        $supportMime = mime_content_type($supportFile['tmp_name']);
                        if (in_array($supportExt, ALLOWED_EXTENSIONS) && in_array($supportMime, ALLOWED_MIME_TYPES) && $supportFile['size'] <= MAX_FILE_SIZE) {
                            $supportRandomName = bin2hex(random_bytes(16)) . '.' . $supportExt;
                            move_uploaded_file($supportFile['tmp_name'], UPLOAD_PATH_TEMP . '/' . $supportRandomName);
                            $supportOrigName = $supportFile['name'];
                        }
                    }
                    
                    $nextVerNum = $paper['current_version_number'] + 1;
                    $fileHash = hash_file('sha256', $paperPath);
                    
                    $db->beginTransaction();
                    try {
                        // Insert new version
                        $verStmt = $db->prepare("
                            INSERT INTO paper_versions 
                            (paper_id, version_number, file_path, original_filename, file_hash, file_size, marking_guide_path, marking_guide_orig, supporting_path, supporting_orig, reason_for_revision, uploaded_by)
                            VALUES (:paper_id, :version_number, :file_path, :original_filename, :file_hash, :file_size, :marking_guide_path, :marking_guide_orig, :supporting_path, :supporting_orig, :reason, :uploaded_by)
                        ");
                        $verStmt->execute([
                            ':paper_id' => $paperId,
                            ':version_number' => $nextVerNum,
                            ':file_path' => $paperRandomName,
                            ':original_filename' => $paperName,
                            ':file_hash' => $fileHash,
                            ':file_size' => $paperSize,
                            ':marking_guide_path' => $guideRandomName,
                            ':marking_guide_orig' => $guideOrigName,
                            ':supporting_path' => $supportRandomName,
                            ':supporting_orig' => $supportOrigName,
                            ':reason' => $revisionReason,
                            ':uploaded_by' => $user['id']
                        ]);
                        
                        // Update paper details
                        $upPaper = $db->prepare("
                            UPDATE examination_papers 
                            SET current_version_number = :ver, status = 'Re-Submitted', updated_at = NOW() 
                            WHERE id = :id
                        ");
                        $upPaper->execute([
                            ':ver' => $nextVerNum,
                            ':id' => $paperId
                        ]);
                        
                        $db->commit();
                        
                        $success = "Revised version v$nextVerNum uploaded and submitted for moderator review.";
                        logAudit('Revision Uploaded', "Uploaded version v$nextVerNum for {$paper['course_code']} (ID: $paperId)");
                        
                        // Notify moderator
                        sendNotification($paper['assigned_moderator_id'], 'Paper Re-Submitted', "A revised paper version v$nextVerNum for {$paper['course_code']} has been uploaded by the lecturer.");
                        
                        // Refresh paper data
                        $paper['status'] = 'Re-Submitted';
                        $paper['current_version_number'] = $nextVerNum;
                        $isLocked = false;
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        @unlink($paperPath);
                        if ($guideRandomName) @unlink(UPLOAD_PATH_TEMP . '/' . $guideRandomName);
                        if ($supportRandomName) @unlink(UPLOAD_PATH_TEMP . '/' . $supportRandomName);
                        $error = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error = "Failed to write file.";
                }
            }
        }
    }
}

// Fetch all versions
$verListStmt = $db->prepare("
    SELECT pv.*, u.full_name as uploader_name 
    FROM paper_versions pv
    JOIN users u ON pv.uploaded_by = u.id
    WHERE pv.paper_id = :paper_id
    ORDER BY pv.version_number DESC
");
$verListStmt->execute([':paper_id' => $paperId]);
$versions = $verListStmt->fetchAll();

// Fetch comments
$commentsStmt = $db->prepare("
    SELECT rc.*, u.full_name as author_name, u.role as author_role
    FROM review_comments rc
    JOIN users u ON rc.moderator_id = u.id
    WHERE rc.paper_id = :paper_id
    ORDER BY rc.created_at ASC
");
$commentsStmt->execute([':paper_id' => $paperId]);
$comments = $commentsStmt->fetchAll();

// Render status color mapping
$workflowStates = [
    'Draft' => 1,
    'Submitted' => 2,
    'Under Review' => 3,
    'Correction Requested' => 4,
    'Re-Submitted' => 5,
    'Approved' => 6,
    'Blind Lockdown Activated' => 7,
    'Ready for Printing' => 8,
    'Printing Queue' => 9,
    'Printed' => 10,
    'Archived' => 11
];
$currentStep = $workflowStates[$paper['status']] ?? 2;
?>

<div class="space-y-8">
    
    <!-- Header Block -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold uppercase bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 px-2 py-0.5 rounded border border-brand-200 dark:border-brand-800">
                    <?= htmlspecialchars($paper['course_code']) ?>
                </span>
                <span class="text-xs text-slate-400">|</span>
                <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars($paper['session_name']) ?> - <?= htmlspecialchars($paper['semester_name']) ?></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_title']) ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Assigned Moderator: <span class="font-bold"><?= htmlspecialchars($paper['moderator_name']) ?></span></p>
        </div>
        
        <div>
            <!-- Main Badge -->
            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-bold uppercase tracking-wider
                <?= $isLocked ? 'bg-brand-600 text-white' : ($paper['status'] === 'Correction Requested' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300') ?>">
                <?php if ($isLocked): ?>🔒<?php endif; ?> <?= $paper['status'] ?>
            </span>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 text-sm font-semibold">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Visual Progress Tracker -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
        <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Examination Paper Pipeline Status</h3>
        
        <!-- Workflow Timeline Grid -->
        <div class="relative flex items-center justify-between w-full overflow-x-auto py-4">
            <div class="absolute left-0 right-0 h-1 bg-slate-100 dark:bg-slate-700 top-1/2 -translate-y-1/2 z-0"></div>
            <!-- Active Fill -->
            <div class="absolute left-0 h-1 bg-brand-500 top-1/2 -translate-y-1/2 z-0 transition-all duration-500" style="width: <?= (($currentStep - 1) / 10) * 100 ?>%"></div>
            
            <?php 
            $steps = [
                1 => ['Draft', '📝'],
                2 => ['Submitted', '📤'],
                3 => ['In Review', '🔍'],
                4 => ['Correction', '⚠️'],
                6 => ['Approved', '✅'],
                7 => ['Lockdown', '🔒'],
                9 => ['Queue', '🖨️'],
                11 => ['Archived', '📂']
            ];
            foreach ($steps as $stepNum => $stepMeta): 
                $isPassed = $currentStep >= $stepNum;
                $isCurrent = $currentStep == $stepNum;
            ?>
                <div class="flex flex-col items-center z-10 text-center px-2 min-w-[70px]">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs border transition-all duration-300 
                        <?= $isCurrent ? 'bg-brand-600 text-white border-brand-600 scale-110 shadow-lg shadow-brand-500/30' : ($isPassed ? 'bg-brand-100 text-brand-700 border-brand-200 dark:bg-brand-950/40 dark:text-brand-300 dark:border-brand-800' : 'bg-white dark:bg-slate-800 text-slate-400 border-slate-200 dark:border-slate-700') ?>">
                        <?= $stepMeta[1] ?>
                    </div>
                    <span class="text-[10px] font-bold mt-2 whitespace-nowrap <?= $isCurrent ? 'text-brand-600 dark:text-brand-400' : ($isPassed ? 'text-slate-900 dark:text-white' : 'text-slate-400') ?>">
                        <?= $stepMeta[0] ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Side: Interactive Comments & Revision Uploads -->
        <div class="lg:col-span-6 space-y-6">
            
            <!-- Comment Section -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col h-[500px]">
                <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-700 pb-3 flex items-center justify-between">
                    <span>Vetting Moderation Feed</span>
                    <span class="text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full font-bold">
                        <?= count($comments) ?> comments
                    </span>
                </h3>
                
                <!-- Threaded Comments Timeline -->
                <div class="flex-1 overflow-y-auto py-4 space-y-4 pr-2">
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-20 text-slate-400">
                            <span class="text-3xl block mb-2">💬</span>
                            <p class="text-xs">No feedback has been recorded yet. Submissions are read-only until the moderator requests corrections.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): 
                            $isMod = ($comment['author_role'] === 'moderator');
                            $bgColor = $isMod ? 'bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700' : 'bg-blue-50/50 dark:bg-brand-950/10 border border-blue-100 dark:border-brand-900/30';
                            $roleLabel = $isMod ? 'Moderator' : 'Lecturer';
                        ?>
                            <div class="p-3.5 rounded-xl <?= $bgColor ?> space-y-2 max-w-[85%] <?= $isMod ? 'mr-auto' : 'ml-auto' ?>">
                                <div class="flex items-center justify-between gap-4 text-[10px]">
                                    <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($comment['author_name']) ?> <span class="font-normal text-slate-400">(<?= $roleLabel ?>)</span></span>
                                    <span class="text-slate-400"><?= date('d M, H:i', strtotime($comment['created_at'])) ?></span>
                                </div>
                                <p class="text-xs text-slate-800 dark:text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input box for replies -->
                <?php if (!$isLocked): ?>
                    <form action="<?= url('dashboard/lecturer/view_paper.php?id=' . $paperId) ?>" method="POST" class="pt-3 border-t border-slate-100 dark:border-slate-700 flex gap-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_comment">
                        <input type="text" name="comment_text" required placeholder="Type reply message to moderator..." 
                               class="flex-1 px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">
                            Reply
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Revision Upload Area -->
            <?php if ($paper['status'] === 'Correction Requested' && !$isLocked): ?>
                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-rose-200 dark:border-rose-900/40 shadow-sm space-y-4 bg-gradient-to-br from-white to-rose-50/10 dark:from-slate-800 dark:to-rose-950/5">
                    <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                        <span class="text-lg">⚠️</span>
                        <h3 class="text-base font-bold">Correction Requested</h3>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Please review the moderator's comments, make modifications to your examination document, and upload the revised version below.
                    </p>

                    <form action="<?= url('dashboard/lecturer/view_paper.php?id=' . $paperId) ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="upload_revision">
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Reason for Revision</label>
                            <input type="text" name="revision_reason" required placeholder="e.g. Addressed moderator review on question 3"
                                   class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Upload Revised Paper (PDF or DOCX)</label>
                            <input type="file" name="exam_paper" accept=".pdf,.docx" required
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-700 dark:file:bg-rose-950/40 dark:file:text-rose-300 hover:file:bg-rose-100 transition-all cursor-pointer border border-slate-300 dark:border-slate-600 p-2 rounded-lg bg-slate-50 dark:bg-slate-900">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">Marking Guide (Optional)</label>
                                <input type="file" name="marking_guide" accept=".pdf,.docx"
                                       class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-slate-100 hover:file:bg-slate-200 border border-slate-300 dark:border-slate-600 p-1 rounded bg-slate-50 dark:bg-slate-900">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase mb-1">Supporting Files (Optional)</label>
                                <input type="file" name="supporting_files" accept=".pdf,.docx"
                                       class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:bg-slate-100 hover:file:bg-slate-200 border border-slate-300 dark:border-slate-600 p-1 rounded bg-slate-50 dark:bg-slate-900">
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full py-2 px-4 rounded-lg text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-sm">
                            Submit Revision v<?= $paper['current_version_number'] + 1 ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right Side: Document Viewer Shell OR Locked Screen, and Version History -->
        <div class="lg:col-span-6 space-y-6">
            
            <!-- Document View Area -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Secure Document Viewer</h3>
                
                <?php if ($isLocked): ?>
                    <!-- Locked banner because of Blind Lockdown -->
                    <div class="p-8 text-center bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 space-y-4">
                        <div class="w-16 h-16 bg-brand-50 dark:bg-brand-950/40 border border-brand-200 dark:border-brand-800 text-brand-600 dark:text-brand-400 rounded-full flex items-center justify-center mx-auto text-2xl animate-pulse">
                            🔒
                        </div>
                        <h4 class="text-base font-bold text-slate-950 dark:text-white uppercase tracking-wider">Blind Lockdown™ Protocol Active</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto leading-relaxed">
                            Immediately after moderator approval, the Blind Lockdown protocol is activated. Lecturer viewing, download, and modification privileges are permanently revoked to preserve exam confidentiality.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Frame to load secure document stream page -->
                    <div class="space-y-4">
                        <p class="text-xs text-slate-400 flex items-center gap-1">
                            <span>🛡️</span> Protected Mode: Standard controls, print, save, and copying functions are disabled.
                        </p>
                        
                        <!-- Embed secure viewer helper page -->
                        <div class="w-full h-[350px] border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-slate-100 relative">
                            <!-- Click-jacking prevention overlay with dynamic diagonal watermark -->
                            <iframe src="<?= url('dashboard/view_file_secure.php?id=' . $paperId) ?>" class="w-full h-full border-none z-0" scrolling="no"></iframe>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Version History -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Document Version Archives</h3>
                
                <div class="space-y-3">
                    <?php foreach ($versions as $ver): ?>
                        <div class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/20 text-xs flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-slate-950 dark:text-white">Version v<?= $ver['version_number'] ?></span>
                                    <span class="text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 px-1.5 py-0.5 rounded font-mono"><?= round($ver['file_size'] / 1024, 1) ?> KB</span>
                                </div>
                                <p class="text-slate-600 dark:text-slate-400 font-medium italic">"<?= htmlspecialchars($ver['reason_for_revision'] ?? 'Initial Upload') ?>"</p>
                                <p class="text-[10px] text-slate-400">Uploaded by: <?= htmlspecialchars($ver['uploader_name']) ?> | <?= date('d M Y, H:i', strtotime($ver['upload_date'])) ?></p>
                                
                                <!-- Attachment check -->
                                <div class="flex flex-wrap gap-2 mt-2 pt-2 border-t border-slate-150 dark:border-slate-750">
                                    <span class="text-[10px] text-slate-500 flex items-center gap-1">
                                        📄 Paper: <?= htmlspecialchars(substr($ver['original_filename'], 0, 20)) ?>...
                                    </span>
                                    <?php if ($ver['marking_guide_orig']): ?>
                                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium">
                                            ✏️ Guide: <?= htmlspecialchars(substr($ver['marking_guide_orig'], 0, 15)) ?>...
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($ver['supporting_orig']): ?>
                                        <span class="text-[10px] text-blue-600 dark:text-blue-400 flex items-center gap-1 font-medium">
                                            📎 Support: <?= htmlspecialchars(substr($ver['supporting_orig'], 0, 15)) ?>...
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
