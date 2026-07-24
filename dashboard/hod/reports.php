<?php
$pageTitle = "Reports & Analytics";
$breadcrumbs = ['HOD Workspace' => 'dashboard/hod/index.php', 'Reports' => ''];

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/security_helper.php';
require_once __DIR__ . '/../../config/database.php';

// Auth checks before ANY output
requireAuth();
requireRole('hod');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];

// Handle CSV Exports
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    // Clear output buffer
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    
    if ($exportType === 'lecturer_activity') {
        header('Content-Disposition: attachment; filename=lecturer_activity_report_' . $dept . '_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Lecturer Name', 'Email', 'Course Code', 'Course Title', 'Submissions Count', 'Latest Upload', 'Paper Status']);
        
        $stmt = $db->prepare("
            SELECT u.full_name as lecturer_name, u.email as lecturer_email, c.course_code, c.course_title, 
                   COUNT(ep.id) as submission_count, MAX(ep.created_at) as latest_submission, ep.status
            FROM lecturer_course_assignments lca
            JOIN users u ON lca.lecturer_id = u.id
            JOIN courses c ON lca.course_id = c.id
            LEFT JOIN examination_papers ep ON c.id = ep.course_id AND ep.created_by = u.id
            WHERE c.department_code = :dept AND lca.status = 'active'
            GROUP BY u.id, c.id
        ");
        $stmt->execute([':dept' => $dept]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['lecturer_name'],
                $row['lecturer_email'],
                $row['course_code'],
                $row['course_title'],
                $row['submission_count'],
                $row['latest_submission'] ?? 'No submissions',
                $row['status'] ?? 'Not Started'
            ]);
        }
        fclose($output);
        exit;
    }
    
    if ($exportType === 'moderator_performance') {
        header('Content-Disposition: attachment; filename=moderator_performance_' . $dept . '_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Moderator Name', 'Email', 'Assigned Level', 'Approvals Issued', 'Review Feedback Comments Posted']);
        
        $stmt = $db->prepare("
            SELECT u.full_name as moderator_name, u.email as moderator_email, mla.level,
                   COUNT(DISTINCT ar.id) as approval_count,
                   COUNT(DISTINCT rc.id) as comment_count
            FROM moderator_level_assignments mla
            JOIN users u ON mla.moderator_id = u.id
            LEFT JOIN approval_records ar ON u.id = ar.moderator_id AND ar.department_code = mla.department_code
            LEFT JOIN review_comments rc ON u.id = rc.moderator_id AND rc.comment_type = 'general'
            WHERE mla.department_code = :dept
            GROUP BY u.id, mla.level
        ");
        $stmt->execute([':dept' => $dept]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['moderator_name'],
                $row['moderator_email'],
                $row['level'] . ' Level',
                $row['approval_count'],
                $row['comment_count']
            ]);
        }
        fclose($output);
        exit;
    }
    
    if ($exportType === 'audit_logs') {
        header('Content-Disposition: attachment; filename=department_audit_logs_' . $dept . '_' . date('Ymd') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date/Time', 'Staff ID', 'Role', 'Action', 'Details', 'IP Address', 'Browser', 'OS']);
        
        $stmt = $db->prepare("
            SELECT created_at, staff_id, role, action, description, ip_address, browser, os 
            FROM audit_logs 
            WHERE department_code = :dept 
            ORDER BY created_at DESC
        ");
        $stmt->execute([':dept' => $dept]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}

// Proceed to load header if not exporting
require_once __DIR__ . '/../../includes/header.php';

// Fetch Lecturer Activity Dataset
$lecStmt = $db->prepare("
    SELECT u.full_name as lecturer_name, u.email as lecturer_email, c.course_code, c.course_title, 
           COUNT(ep.id) as submission_count, MAX(ep.created_at) as latest_submission, ep.status
    FROM lecturer_course_assignments lca
    JOIN users u ON lca.lecturer_id = u.id
    JOIN courses c ON lca.course_id = c.id
    LEFT JOIN examination_papers ep ON c.id = ep.course_id AND ep.created_by = u.id
    WHERE c.department_code = :dept AND lca.status = 'active'
    GROUP BY u.id, c.id
");
$lecStmt->execute([':dept' => $dept]);
$lecturerActivity = $lecStmt->fetchAll();

// Fetch Moderator Performance Dataset
$modStmt = $db->prepare("
    SELECT u.full_name as moderator_name, u.email as moderator_email, mla.level,
           COUNT(DISTINCT ar.id) as approval_count,
           COUNT(DISTINCT rc.id) as comment_count
    FROM moderator_level_assignments mla
    JOIN users u ON mla.moderator_id = u.id
    LEFT JOIN approval_records ar ON u.id = ar.moderator_id AND ar.department_code = mla.department_code
    LEFT JOIN review_comments rc ON u.id = rc.moderator_id AND rc.comment_type = 'general'
    WHERE mla.department_code = :dept
    GROUP BY u.id, mla.level
");
$modStmt->execute([':dept' => $dept]);
$moderatorPerformance = $modStmt->fetchAll();

// Fetch Department Audit Logs
$auditStmt = $db->prepare("
    SELECT created_at, staff_id, role, action, description, ip_address 
    FROM audit_logs 
    WHERE department_code = :dept 
    ORDER BY created_at DESC LIMIT 15
");
$auditStmt->execute([':dept' => $dept]);
$auditLogs = $auditStmt->fetchAll();
?>

<div class="space-y-8">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Reports & System Analytics</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Download system audit sheets and analyze departmental workflow tracking rates.</p>
        </div>
    </div>

    <!-- Grid Splits: Lecturer Activity & Moderator Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Lecturer activity tracker -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Lecturer Activity Sheet</h3>
                <a href="?export=lecturer_activity" class="text-xs font-bold text-brand-600 dark:text-brand-400 flex items-center gap-1 hover:underline">
                    📥 Export CSV
                </a>
            </div>
            
            <div class="overflow-x-auto max-h-[350px]">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                            <th class="py-2">Lecturer</th>
                            <th class="py-2">Course</th>
                            <th class="py-2">Submitted</th>
                            <th class="py-2">Latest Upload</th>
                            <th class="py-2 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($lecturerActivity as $row): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                <td class="py-3 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($row['lecturer_name']) ?>
                                    <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($row['lecturer_email']) ?></span>
                                </td>
                                <td class="py-3 font-mono"><?= htmlspecialchars($row['course_code']) ?></td>
                                <td class="py-3 font-semibold"><?= $row['submission_count'] ?> version(s)</td>
                                <td class="py-3 text-slate-500"><?= $row['latest_submission'] ? date('d M Y, H:i', strtotime($row['latest_submission'])) : 'No submission' ?></td>
                                <td class="py-3 text-right">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-800 dark:bg-slate-750 dark:text-slate-300">
                                        <?= $row['status'] ?? 'Not Started' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Moderator Performance Tracker -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Moderator Vetting Sheet</h3>
                <a href="?export=moderator_performance" class="text-xs font-bold text-brand-600 dark:text-brand-400 flex items-center gap-1 hover:underline">
                    📥 Export CSV
                </a>
            </div>

            <div class="overflow-x-auto max-h-[350px]">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                            <th class="py-2">Moderator</th>
                            <th class="py-2">Assigned Level</th>
                            <th class="py-2">Approvals Issued</th>
                            <th class="py-2 text-right">Review Comments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($moderatorPerformance as $row): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                <td class="py-3 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($row['moderator_name']) ?>
                                    <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($row['moderator_email']) ?></span>
                                </td>
                                <td class="py-3 font-semibold"><?= htmlspecialchars($row['level']) ?> Level</td>
                                <td class="py-3 text-emerald-600 font-bold"><?= $row['approval_count'] ?> approvals</td>
                                <td class="py-3 text-right font-semibold text-slate-655"><?= $row['comment_count'] ?> comments</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Audit logs row -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">System Audit Trail</h3>
            <a href="?export=audit_logs" class="text-xs font-bold text-brand-600 dark:text-brand-400 flex items-center gap-1 hover:underline">
                📥 Export CSV
            </a>
        </div>

        <div class="overflow-x-auto max-h-[450px]">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                        <th class="py-2">Timestamp</th>
                        <th class="py-2">Staff ID</th>
                        <th class="py-2">Role</th>
                        <th class="py-2">Action</th>
                        <th class="py-2">Details</th>
                        <th class="py-2 text-right">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($auditLogs as $act): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                            <td class="py-3 text-slate-500"><?= date('d M Y, H:i', strtotime($act['created_at'])) ?></td>
                            <td class="py-3 font-mono font-bold"><?= htmlspecialchars($act['staff_id'] ?? 'SYSTEM') ?></td>
                            <td class="py-3 uppercase text-[10px] font-semibold"><?= htmlspecialchars($act['role'] ?? 'cron') ?></td>
                            <td class="py-3 font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($act['action']) ?></td>
                            <td class="py-3 text-slate-600 dark:text-slate-400"><?= htmlspecialchars($act['description']) ?></td>
                            <td class="py-3 text-right font-mono text-[10px]"><?= htmlspecialchars($act['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
