<?php
$pageTitle = "Print Audits & History";
$breadcrumbs = ['Exam Officer Workspace' => 'dashboard/exam_officer/index.php', 'Print History' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('exam_officer');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];

// Fetch Print History Logs for the Department
$stmt = $db->prepare("
    SELECT pl.*, c.course_code, c.course_title, u.full_name as officer_name, u.email as officer_email,
           (SELECT COUNT(*) FROM print_logs WHERE paper_id = ep.id) AS prints_count
    FROM print_logs pl
    JOIN examination_papers ep ON pl.paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    JOIN users u ON pl.user_id = u.id
    WHERE ep.department_code = :dept
    ORDER BY pl.created_at DESC");
$stmt->execute([':dept' => $dept]);
$logs = $stmt->fetchAll();
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Print History & Audit Trail</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Verify printing operations and track who accessed locked paper contents.</p>
        </div>
    </div>

    <!-- Print Logs History Table -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <?php if (empty($logs)): ?>
            <div class="text-center py-16 text-slate-400 space-y-2">
                <span class="text-3xl block">📜</span>
                <p class="text-xs">No printing logs recorded yet for your department.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                            <th class="pb-2">Course details</th>
                            <th class="pb-2">Printed By</th>
                            <th class="pb-2">Timestamp</th>
                            <th class="pb-2">IP Address</th>
                            <th class="pb-2">Total Prints</th>
                            <th class="pb-2 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                <td class="py-3.5 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($log['course_code']) ?>
                                    <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($log['course_title']) ?></span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-700 dark:text-slate-350">
                                    <span class="font-bold block"><?= htmlspecialchars($log['officer_name']) ?></span>
                                    <span class="text-[10px] text-slate-500"><?= htmlspecialchars($log['officer_email']) ?></span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-500 font-medium"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?></td>
                                <td class="py-3.5 font-mono text-[10px] text-slate-655"><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td class="py-3.5 text-xs font-black"><?= $log['prints_count'] ?> times</td>
                                <td class="py-3.5 text-right">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-450 uppercase">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
