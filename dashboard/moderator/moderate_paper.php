<?php
$pageTitle = "Moderate Paper";
$breadcrumbs = ['Vetting Center' => 'dashboard/moderator/index.php', 'Pending Papers' => 'dashboard/moderator/vetting.php', 'Moderate Paper' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('moderator');

$db = Database::getInstance();
$user = currentUser();
$modId = $user['id'];
$paperId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Fetch paper details and make sure it exists
$stmt = $db->prepare("
    SELECT ep.*, c.course_code, c.course_title, c.level, u.full_name as lecturer_name, s.name as session_name, sem.name as semester_name, setts.submission_deadline
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN users u ON ep.created_by = u.id
    JOIN system_settings setts ON ep.department_code = setts.department_code
    JOIN academic_sessions s ON ep.academic_session_id = s.id
    JOIN semesters sem ON ep.semester_id = sem.id
    WHERE ep.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $paperId]);
$paper = $stmt->fetch();

if (!$paper) {
    echo "<div class='p-6 bg-red-100 text-red-800 rounded-xl'>Examination paper not found.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Enforce moderator authority check: does the level & department assign to this moderator?
$modCheck = $db->prepare("
    SELECT id FROM moderator_level_assignments 
    WHERE moderator_id = :mod_id AND department_code = :dept AND level = :level AND academic_session_id = :sess_id
    LIMIT 1
");
$modCheck->execute([
    ':mod_id'  => $modId,
    ':dept'    => $paper['department_code'],
    ':level'   => $paper['level'],
    ':sess_id' => $paper['academic_session_id']
]);
if (!$modCheck->fetch()) {
    logSecurityEvent('UNAUTHORIZED_ACCESS_ATTEMPT', "Moderator ID $modId attempted unauthorized vetting of paper ID $paperId", 'high');
    echo "<div class='p-6 bg-red-100 text-red-800 rounded-xl'>Access Denied: You are not assigned to moderate this department level.</div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$isLocked = in_array($paper['status'], ['Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed', 'Archived']);

// Process Vetting Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } elseif ($isLocked) {
        $error = "This paper has already been approved or locked down. Actions are disabled.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'request_correction') {
            $commentText = sanitizeInput($_POST['comment_text'] ?? '');
            if (empty($commentText)) {
                $error = "Please provide feedback detail stating what corrections are needed.";
            } else {
                $db->beginTransaction();
                try {
                    // Fetch latest version ID
                    $verStmt = $db->prepare("SELECT id FROM paper_versions WHERE paper_id = :paper_id ORDER BY version_number DESC LIMIT 1");
                    $verStmt->execute([':paper_id' => $paperId]);
                    $verId = $verStmt->fetchColumn();
                    
                    // Insert general comment
                    $cmtInsert = $db->prepare("
                        INSERT INTO review_comments (paper_id, version_id, moderator_id, comment_type, comment_text)
                        VALUES (:paper_id, :version_id, :moderator_id, 'general', :comment_text)
                    ");
                    $cmtInsert->execute([
                        ':paper_id' => $paperId,
                        ':version_id' => $verId,
                        ':moderator_id' => $modId,
                        ':comment_text' => $commentText
                    ]);
                    
                    // Update status
                    $upStatus = $db->prepare("
                        UPDATE examination_papers 
                        SET status = 'Correction Requested', updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $upStatus->execute([':id' => $paperId]);
                    
                    $db->commit();
                    
                    logAudit('Correction Requested', "Moderator returned paper for {$paper['course_code']} (ID: $paperId) for corrections.");
                    
                    // Notify Lecturer
                    sendNotification(
                        $paper['created_by'], 
                        'Correction Requested', 
                        "Moderator has returned {$paper['course_code']} for corrections. Comments: " . substr($commentText, 0, 80) . "..."
                    );
                    
                    flash('info', 'Paper sent back to the lecturer with correction requests.');
                    redirect('dashboard/moderator/vetting.php');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } 
        
        elseif ($action === 'approve_paper') {
            try {
                // Call approval workflow helper
                $certId = approvePaper($paperId, $modId);
                
                flash('success', "Paper approved successfully! Digital Approval Certificate generated: <strong>$certId</strong>. Blind Lockdown is now active.");
                redirect('dashboard/moderator/vetting.php');
            } catch (Exception $e) {
                $error = "Approval process failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch comments thread
$commentsStmt = $db->prepare("
    SELECT rc.*, u.full_name as author_name, u.role as author_role
    FROM review_comments rc
    JOIN users u ON rc.moderator_id = u.id
    WHERE rc.paper_id = :paper_id
    ORDER BY rc.created_at ASC
");
$commentsStmt->execute([':paper_id' => $paperId]);
$comments = $commentsStmt->fetchAll();

// Fetch version logs
$verStmt = $db->prepare("
    SELECT pv.*, u.full_name as uploader_name 
    FROM paper_versions pv
    JOIN users u ON pv.uploaded_by = u.id
    WHERE pv.paper_id = :paper_id
    ORDER BY pv.version_number DESC
");
$verStmt->execute([':paper_id' => $paperId]);
$versions = $verStmt->fetchAll();
?>

<div class="space-y-6">
    
    <!-- Header Block -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold uppercase bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-800">
                    <?= htmlspecialchars($paper['course_code']) ?>
                </span>
                <span class="text-xs text-slate-400">|</span>
                <span class="text-xs text-slate-500 font-medium"><?= htmlspecialchars($paper['session_name']) ?> - <?= htmlspecialchars($paper['semester_name']) ?></span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1"><?= htmlspecialchars($paper['course_title']) ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Submitted by lecturer: <span class="font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($paper['lecturer_name']) ?></span></p>
        </div>
        
        <div>
            <span class="inline-flex items-center px-4 py-2 rounded-full text-xs font-extrabold uppercase tracking-wider bg-blue-100 text-blue-855 dark:bg-blue-950/40 dark:text-blue-300">
                <?= $paper['status'] ?>
            </span>
        </div>
    </div>

    <!-- Error Alerts -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-955/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 text-sm font-semibold">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Main Workspace Split Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Protected View Frame -->
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700 mb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Secure Document Preview</h3>
                    <span class="text-xs text-slate-400 font-mono">v<?= $paper['current_version_number'] ?></span>
                </div>
                
                <!-- Frame Container -->
                <div class="w-full h-[500px] border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-slate-100 relative">
                    <iframe src="<?= url('dashboard/view_file_secure.php?id=' . $paperId) ?>" class="w-full h-full border-none z-0" scrolling="no"></iframe>
                </div>
            </div>
            
            <!-- Metadata & Instructions -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div>
                    <span class="text-slate-400 block font-bold uppercase">Exam Date</span>
                    <span class="font-semibold text-slate-900 dark:text-white"><?= date('d M, Y', strtotime($paper['exam_date'])) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-bold uppercase">Duration</span>
                    <span class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['duration']) ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-bold uppercase">Questions</span>
                    <span class="font-semibold text-slate-900 dark:text-white"><?= $paper['num_questions'] ?></span>
                </div>
                <div>
                    <span class="text-slate-400 block font-bold uppercase">Academic Level</span>
                    <span class="font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($paper['level']) ?> Level</span>
                </div>
                <div class="col-span-2 md:col-span-4 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <span class="text-slate-400 block font-bold uppercase mb-0.5">Lecturer Instructions</span>
                    <p class="text-slate-700 dark:text-slate-350 bg-slate-50 dark:bg-slate-900/30 p-2.5 rounded-lg border border-slate-200/50 dark:border-slate-700 font-mono leading-normal"><?= nl2br(htmlspecialchars($paper['instructions'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Right: Actions, Feedback Comments & Versions -->
        <div class="lg:col-span-5 space-y-6">
            
            <!-- Vetting Decision Panel -->
            <?php if (!$isLocked): ?>
                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-6">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-100 dark:border-slate-700">Moderator Decision</h3>
                    
                    <!-- Direct Approval Button -->
                    <form action="<?= url('dashboard/moderator/moderate_paper.php?id=' . $paperId) ?>" method="POST" 
                          onsubmit="return confirm('WARNING: Approving this paper will issue a Digital Approval Certificate and activate the Blind Lockdown protocol. The lecturer will lose all viewing and editing access. Proceed?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve_paper">
                        <button type="submit" 
                                class="w-full py-3 px-4 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-md flex items-center justify-center gap-2">
                            <span>✅</span>
                            <span>Approve Questions & Lockdown</span>
                        </button>
                    </form>

                    <div class="relative flex py-2 items-center">
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-700"></div>
                        <span class="flex-shrink mx-4 text-slate-400 text-[10px] font-bold uppercase">Or Return For Correction</span>
                        <div class="flex-grow border-t border-slate-200 dark:border-slate-700"></div>
                    </div>

                    <!-- Return with Comments -->
                    <form action="<?= url('dashboard/moderator/moderate_paper.php?id=' . $paperId) ?>" method="POST" class="space-y-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="request_correction">
                        
                        <div>
                            <label class="block text-[10px] font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Correction Guidelines / Notes</label>
                            <textarea name="comment_text" rows="3" required placeholder="Outline exactly what adjustments are required (e.g. Typo in question 2, syllabus misalignment)..."
                                      class="w-full p-3 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                        </div>

                        <button type="submit" 
                                class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-sm flex items-center justify-center gap-1.5">
                            <span>⚠️</span>
                            <span>Request Corrections</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Feedback Feed -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col h-[350px]">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Moderation Feed</h3>
                
                <div class="flex-1 overflow-y-auto py-3 space-y-3 pr-1">
                    <?php if (empty($comments)): ?>
                        <p class="text-xs text-slate-400 text-center py-16">No comment messages exchanged yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): 
                            $isMod = ($comment['author_role'] === 'moderator');
                            $bgColor = $isMod ? 'bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700' : 'bg-blue-50/50 dark:bg-brand-950/10 border border-blue-100 dark:border-brand-900/30';
                        ?>
                            <div class="p-3 rounded-lg <?= $bgColor ?> space-y-1 max-w-[88%] <?= $isMod ? 'ml-auto' : 'mr-auto' ?>">
                                <div class="flex items-center justify-between gap-4 text-[9px]">
                                    <span class="font-extrabold text-slate-950 dark:text-white"><?= htmlspecialchars($comment['author_name']) ?></span>
                                    <span class="text-slate-400"><?= date('d M, H:i', strtotime($comment['created_at'])) ?></span>
                                </div>
                                <p class="text-[11px] text-slate-700 dark:text-slate-300 leading-normal"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Moderator Sub-frame Reply -->
                <?php if (!$isLocked && !empty($comments)): ?>
                    <form action="<?= url('dashboard/moderator/moderate_paper.php?id=' . $paperId) ?>" method="POST" class="pt-2 border-t border-slate-150 dark:border-slate-700 flex gap-2">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="request_correction">
                        <input type="text" name="comment_text" required placeholder="Type reply message..." 
                               class="flex-1 px-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-brand-500">
                        <button type="submit" class="px-3 py-1.5 text-xs font-bold text-white bg-slate-700 hover:bg-slate-800 rounded-lg transition-colors">
                            Send
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Version History -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-3">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider pb-1 border-b border-slate-100 dark:border-slate-700">Audit Version Logs</h3>
                
                <div class="space-y-2">
                    <?php foreach ($versions as $ver): ?>
                        <div class="p-3 rounded-lg border border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/10 text-xs space-y-1">
                            <div class="flex justify-between items-center font-bold">
                                <span>Version v<?= $ver['version_number'] ?></span>
                                <span class="font-mono text-[10px] text-slate-400"><?= round($ver['file_size'] / 1024, 1) ?> KB</span>
                            </div>
                            <p class="text-slate-500 dark:text-slate-400 italic">"<?= htmlspecialchars($ver['reason_for_revision'] ?? 'Initial Upload') ?>"</p>
                            <div class="flex justify-between text-[9px] text-slate-400 pt-1 border-t border-slate-150 dark:border-slate-750">
                                <span>Uploaded: <?= date('d M Y, H:i', strtotime($ver['upload_date'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
