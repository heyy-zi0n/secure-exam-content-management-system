<?php
$pageTitle = "Department Overview";
$breadcrumbs = ['HOD Workspace' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('hod');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Check delegation status
$delegation = checkDelegation($dept);

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $deadline = $_POST['deadline'] ?? '';
        $maxSize = (int)($_POST['max_size'] ?? 20971520);
        $fileTypes = sanitizeInput($_POST['file_types'] ?? 'pdf,docx');
        
        $setStmt = $db->prepare("
            UPDATE system_settings 
            SET submission_deadline = :deadline, 
                max_upload_size = :max_size, 
                allowed_file_types = :file_types 
            WHERE department_code = :dept
        ");
        $setStmt->execute([
            ':deadline'   => $deadline,
            ':max_size'   => $maxSize,
            ':file_types' => $fileTypes,
            ':dept'       => $dept
        ]);
        
        logAudit('Settings Updated', "Updated department settings. Deadline: $deadline, Max Size: $maxSize");
        $success = "Department system settings updated successfully.";
    }
}

// Handle Emergency Unlock Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'emergency_unlock') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $paperId = (int)($_POST['paper_id'] ?? 0);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if (empty($reason)) {
            $error = "An emergency unlock reason is mandatory for auditing purposes.";
            logSecurityEvent('EMERGENCY_UNLOCK_FAILED', "HOD attempted unlock of Paper ID $paperId without reason.", 'medium');
        } else {
            try {
                // Execute helper unlock
                emergencyUnlockPaper($paperId, $reason, $user['id']);
                $success = "Emergency unlock executed successfully! Paper is now returned to corrections state.";
            } catch (Exception $e) {
                $error = "Unlock failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch Department Metrics
$totalCourses = $db->query("SELECT COUNT(*) FROM courses WHERE department_code = '$dept'")->fetchColumn();
$totalLecs = $db->query("SELECT COUNT(DISTINCT lecturer_id) FROM lecturer_course_assignments lca JOIN courses c ON lca.course_id = c.id WHERE c.department_code = '$dept'")->fetchColumn();
$totalSubmissions = $db->query("SELECT COUNT(*) FROM examination_papers WHERE department_code = '$dept'")->fetchColumn();
$totalApproved = $db->query("SELECT COUNT(*) FROM examination_papers WHERE department_code = '$dept' AND status IN ('Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed')")->fetchColumn();
$totalPending = $db->query("SELECT COUNT(*) FROM examination_papers WHERE department_code = '$dept' AND status IN ('Submitted', 'Re-Submitted', 'Under Review')")->fetchColumn();

// Fetch System Settings
$settingsStmt = $db->prepare("SELECT * FROM system_settings WHERE department_code = :dept LIMIT 1");
$settingsStmt->execute([':dept' => $dept]);
$settings = $settingsStmt->fetch();

// Fetch Locked Papers for Emergency Unlock Dropdown
$lockedPapersStmt = $db->prepare("
    SELECT ep.id, c.course_code, c.course_title 
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    WHERE ep.department_code = :dept AND ep.status = 'Blind Lockdown Activated'
");
$lockedPapersStmt->execute([':dept' => $dept]);
$lockedPapers = $lockedPapersStmt->fetchAll();

// Fetch Recent Audits for this Department
$auditStmt = $db->prepare("
    SELECT * FROM audit_logs 
    WHERE department_code = :dept 
    ORDER BY created_at DESC LIMIT 5
");
$auditStmt->execute([':dept' => $dept]);
$recentAudits = $auditStmt->fetchAll();
?>

<div class="space-y-6">
    
    <!-- HOD Greeting -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white"><?= htmlspecialchars(FCIT_DEPARTMENTS[$dept] ?? $dept) ?> Department</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Workspace for Head of Department (HOD)</p>
        </div>
        
        <?php if ($delegation): ?>
            <div class="flex items-center gap-2 text-xs font-bold px-3 py-1.5 rounded-lg bg-amber-50 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse">
                <span>🤝 Delegated HOD active</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alert banners -->
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

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Department Courses</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $totalCourses ?></span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Active Lecturers</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $totalLecs ?></span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Submitted Papers</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $totalSubmissions ?></span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Approved Papers</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 block mt-1"><?= $totalApproved ?></span>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pending Review</span>
            <span class="text-2xl font-black text-blue-600 dark:text-blue-400 block mt-1"><?= $totalPending ?></span>
        </div>
    </div>

    <!-- Main Section Splits -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left: Settings & Emergency Unlock -->
        <div class="lg:col-span-7 space-y-6">
            
            <!-- HOD System Settings -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Department System Rules</h3>
                
                <form action="<?= url('dashboard/hod/index.php') ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Submission Deadline</label>
                        <input type="date" name="deadline" required value="<?= $settings['submission_deadline'] ?? '' ?>"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Max Upload File Size (Bytes)</label>
                            <input type="number" name="max_size" required value="<?= $settings['max_upload_size'] ?? 20971520 ?>"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <span class="text-[10px] text-slate-400 mt-1 block">Default: 20971520 (20 Megabytes)</span>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Allowed File Types (Comma Separated)</label>
                            <input type="text" name="file_types" required value="<?= htmlspecialchars($settings['allowed_file_types'] ?? 'pdf,docx') ?>"
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <span class="text-[10px] text-slate-400 mt-1 block">Allowed: pdf, docx, doc</span>
                        </div>
                    </div>

                    <button type="submit" 
                            class="py-2.5 px-4 rounded-lg font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm">
                        Save System Rules
                    </button>
                </form>
            </div>

            <!-- Emergency Unlock Panel -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-rose-200 dark:border-rose-900/40 shadow-sm space-y-4 bg-gradient-to-br from-white to-rose-50/10 dark:from-slate-800 dark:to-rose-950/5">
                <div class="flex items-center gap-2 text-rose-600 dark:text-rose-450">
                    <span class="text-xl">🚨</span>
                    <h3 class="text-sm font-bold uppercase tracking-wider">Emergency Paper Unlock Protocol</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Once a paper enters the Blind Lockdown state, all modifications are prohibited. If an emergency correction is needed, the HOD can override the lockdown to return it to the Lecturer correction cycle. All unlocks are logged as critical security events.
                </p>

                <?php if (empty($lockedPapers)): ?>
                    <p class="text-xs text-slate-400 py-2 font-medium">There are currently no papers under Blind Lockdown in this department.</p>
                <?php else: ?>
                    <form action="<?= url('dashboard/hod/index.php') ?>" method="POST" class="space-y-4 text-xs"
                          onsubmit="return confirm('CRITICAL WARNING: You are activating the emergency unlock override. This action will be audited. Proceed?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="emergency_unlock">
                        
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Locked Paper</label>
                            <select name="paper_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">-- Choose Locked Course Paper --</option>
                                <?php foreach ($lockedPapers as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['course_code']) ?> - <?= htmlspecialchars($p['course_title']) ?> (ID: <?= $p['id'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Mandatory Override Audit Reason</label>
                            <input type="text" name="reason" required placeholder="State exact reason for bypass (e.g. Question syllabus misalignment)..."
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>

                        <button type="submit" 
                                class="py-2 px-4 rounded-lg font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-sm">
                            Execute Emergency Unlock
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: Recent Audits Feed -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Department Audit Log</h3>
                
                <div class="flow-root">
                    <ul class="-mb-8">
                        <?php if (empty($recentAudits)): ?>
                            <li class="text-xs text-slate-400 py-4 text-center">No recent activity.</li>
                        <?php else: ?>
                            <?php foreach ($recentAudits as $idx => $act): 
                                $isLast = ($idx === count($recentAudits) - 1);
                            ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if (!$isLast): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-100 dark:bg-slate-700" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs">
                                                    🏢
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0 pt-1.5">
                                                <p class="text-xs font-bold text-slate-900 dark:text-white leading-tight">
                                                    <?= htmlspecialchars($act['action']) ?>
                                                </p>
                                                <p class="text-[10px] text-slate-500 mt-0.5">
                                                    <?= htmlspecialchars($act['description']) ?>
                                                </p>
                                                <span class="text-[9px] text-slate-400 mt-1 block">
                                                    <?= date('d M Y, H:i', strtotime($act['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>