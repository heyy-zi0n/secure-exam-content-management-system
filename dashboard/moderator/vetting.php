<?php
$pageTitle = "Pending Papers";
$breadcrumbs = ['Vetting Center' => 'dashboard/moderator/index.php', 'Pending Papers' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('moderator');

$db = Database::getInstance();
$user = currentUser();
$modId = $user['id'];

// Fetch level allocations to identify what papers the moderator is authorized to view
$mlaStmt = $db->prepare("
    SELECT mla.*, d.name as department_name 
    FROM moderator_level_assignments mla
    JOIN departments d ON mla.department_code = d.code
    WHERE mla.moderator_id = :mod_id
");
$mlaStmt->execute([':mod_id' => $modId]);
$allocations = $mlaStmt->fetchAll();

$pendingPapers = [];

if (!empty($allocations)) {
    // We can fetch all papers matching the level, department and session
    $query = "
        SELECT DISTINCT ep.*, c.course_code, c.course_title, u.full_name as lecturer_name, d.name as department_name, setts.submission_deadline
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        JOIN users u ON ep.created_by = u.id
        JOIN departments d ON ep.department_code = d.code
        LEFT JOIN system_settings setts ON ep.department_code = setts.department_code
        JOIN moderator_level_assignments mla 
          ON ep.department_code = mla.department_code 
         AND ep.level = mla.level 
         AND ep.academic_session_id = mla.academic_session_id
        WHERE mla.moderator_id = :mod_id 
          AND ep.status IN ('Submitted', 'Re-Submitted', 'Under Review')
        ORDER BY ep.updated_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':mod_id' => $modId]);
    $pendingPapers = $stmt->fetchAll();
}
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Pending Moderations</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Review and vet examination papers submitted in your assigned course levels.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <span>Papers to Vet: <?= count($pendingPapers) ?></span>
        </div>
    </div>

    <!-- Papers List -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <?php if (empty($pendingPapers)): ?>
            <div class="text-center py-16 text-slate-450 space-y-3">
                <span class="text-4xl">📭</span>
                <h3 class="font-bold text-slate-800 dark:text-white">All Clear!</h3>
                <p class="text-xs max-w-sm mx-auto">There are no pending exam papers requiring moderation in your levels at this time.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <th class="pb-3 font-semibold">Course Details</th>
                            <th class="pb-3 font-semibold">Department</th>
                            <th class="pb-3 font-semibold">Submitted By</th>
                            <th class="pb-3 font-semibold">Level</th>
                            <th class="pb-3 font-semibold">Status</th>
                            <th class="pb-3 font-semibold">Submission Date</th>
                            <th class="pb-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($pendingPapers as $paper): 
                            $isLate = ($paper['submission_deadline'] && strtotime($paper['created_at']) > strtotime($paper['submission_deadline']));
                            $statusColor = 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400';
                            if ($paper['status'] === 'Re-Submitted') {
                                $statusColor = 'bg-purple-100 text-purple-800 dark:bg-purple-950/30 dark:text-purple-400';
                            }
                        ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750/30 transition-colors">
                                <td class="py-3.5 pr-2 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($paper['course_code']) ?>
                                    <?php if ($isLate): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 uppercase tracking-wide ml-1">LATE</span>
                                    <?php endif; ?>
                                    <span class="block text-xs font-normal text-slate-500 mt-0.5"><?= htmlspecialchars($paper['course_title']) ?></span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($paper['department_name']) ?></td>
                                <td class="py-3.5 text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($paper['lecturer_name']) ?></td>
                                <td class="py-3.5 text-xs font-semibold"><?= htmlspecialchars($paper['level']) ?> Level</td>
                                <td class="py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $statusColor ?>">
                                        <?= $paper['status'] ?>
                                    </span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-500"><?= date('d M Y, H:i', strtotime($paper['updated_at'])) ?></td>
                                <td class="py-3.5 text-right">
                                    <a href="<?= url('dashboard/moderator/moderate_paper.php?id=' . $paper['id']) ?>" 
                                       class="inline-flex items-center px-3 py-1.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">
                                        <span>Moderate</span>
                                        <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
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
