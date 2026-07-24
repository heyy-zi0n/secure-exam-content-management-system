<?php
$pageTitle = "Vetting Center";
$breadcrumbs = ['Vetting Center' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('moderator');

$db = Database::getInstance();
$user = currentUser();
$modId = $user['id'];
$homeDept = $user['department_code'];

// 1. Fetch moderator level allocations
$mlaStmt = $db->prepare("
    SELECT mla.*, d.name as department_name, s.name as session_name
    FROM moderator_level_assignments mla
    JOIN departments d ON mla.department_code = d.code
    JOIN academic_sessions s ON mla.academic_session_id = s.id
    WHERE mla.moderator_id = :mod_id
");
$mlaStmt->execute([':mod_id' => $modId]);
$allocations = $mlaStmt->fetchAll();

// Extract assigned levels and departments
$allocatedLevels = [];
$allocatedDepts = [];
foreach ($allocations as $alloc) {
    $allocatedLevels[] = $alloc['level'];
    $allocatedDepts[] = $alloc['department_code'];
}
$allocatedLevels = array_unique($allocatedLevels);
$allocatedDepts = array_unique($allocatedDepts);

// 2. Metrics Queries
$pendingCount = 0;
if (!empty($allocations)) {
    // Generate placeholders for departments and levels
    $pendingQuery = "
        SELECT COUNT(DISTINCT ep.id) 
        FROM examination_papers ep
        JOIN moderator_level_assignments mla 
          ON ep.department_code = mla.department_code 
         AND ep.level = mla.level 
         AND ep.academic_session_id = mla.academic_session_id
        WHERE mla.moderator_id = :mod_id 
          AND ep.status IN ('Submitted', 'Re-Submitted', 'Under Review')
    ";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute([':mod_id' => $modId]);
    $pendingCount = $pendingStmt->fetchColumn();
}

$approvedCount = $db->query("SELECT COUNT(*) FROM approval_records WHERE moderator_id = $modId")->fetchColumn();
$returnedCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE assigned_moderator_id = $modId AND status = 'Correction Requested'")->fetchColumn();

// 3. Pending papers list
$pendingPapers = [];
if (!empty($allocations)) {
    $listStmt = $db->prepare("
        SELECT DISTINCT ep.*, c.course_code, c.course_title, u.full_name as lecturer_name, d.name as department_name
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        JOIN users u ON ep.created_by = u.id
        JOIN departments d ON ep.department_code = d.code
        JOIN moderator_level_assignments mla 
          ON ep.department_code = mla.department_code 
         AND ep.level = mla.level 
         AND ep.academic_session_id = mla.academic_session_id
        WHERE mla.moderator_id = :mod_id 
          AND ep.status IN ('Submitted', 'Re-Submitted', 'Under Review')
        ORDER BY ep.updated_at DESC LIMIT 5
    ");
    $listStmt->execute([':mod_id' => $modId]);
    $pendingPapers = $listStmt->fetchAll();
}

// 4. Live activity feed for moderator actions
$logStmt = $db->prepare("
    SELECT al.* 
    FROM audit_logs al
    WHERE al.user_id = :id 
       OR (al.action = 'Paper Approved' AND al.description LIKE :desc)
    ORDER BY al.created_at DESC LIMIT 5
");
$logStmt->execute([
    ':id' => $modId,
    ':desc' => "%ID: %"
]);
$activities = $logStmt->fetchAll();
?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Moderation Workspace</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Vetting Center & Peer-Review Portal</p>
    </div>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Assigned Levels</span>
            <span class="text-xl font-black text-slate-950 dark:text-white block mt-2">
                <?= empty($allocatedLevels) ? 'None allocated' : implode(', ', $allocatedLevels) . ' Level' ?>
            </span>
            <span class="text-[10px] text-slate-500 block mt-1">Across <?= count($allocatedDepts) ?> department(s)</span>
        </div>

        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Awaiting Vetting</span>
            <span class="text-3xl font-black text-brand-600 dark:text-brand-400 block mt-2"><?= $pendingCount ?></span>
            <span class="text-[10px] text-slate-500 block mt-1">Ready for peer-review</span>
        </div>

        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Approved by Me</span>
            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 block mt-2"><?= $approvedCount ?></span>
            <span class="text-[10px] text-slate-500 block mt-1">Sent to lockdown & print</span>
        </div>

        <div class="p-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Returned Papers</span>
            <span class="text-3xl font-black text-rose-600 dark:text-rose-400 block mt-2"><?= $returnedCount ?></span>
            <span class="text-[10px] text-slate-500 block mt-1">Awaiting corrections</span>
        </div>
    </div>

    <!-- Main Grid Workspace splits -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Pending Moderation List -->
        <div class="lg:col-span-2 space-y-6">
            
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Awaiting Vetting</h3>
                    <a href="<?= url('dashboard/moderator/vetting.php') ?>" class="text-xs font-bold text-brand-600 hover:underline">View All</a>
                </div>

                <?php if (empty($pendingPapers)): ?>
                    <div class="text-center py-16 text-slate-400 space-y-2">
                        <span class="text-3xl block">🔍</span>
                        <p class="text-xs">No pending exam papers in your allocated levels.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                                    <th class="py-2">Course</th>
                                    <th class="py-2">Lecturer</th>
                                    <th class="py-2">Department</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach ($pendingPapers as $paper): 
                                    $statusColor = 'bg-blue-150 text-blue-800 dark:bg-blue-950/30 dark:text-blue-300';
                                    if ($paper['status'] === 'Re-Submitted') {
                                        $statusColor = 'bg-purple-100 text-purple-800 dark:bg-purple-950/30 dark:text-purple-300';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($paper['course_code']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($paper['course_title']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-600 dark:text-slate-350"><?= htmlspecialchars($paper['lecturer_name']) ?></td>
                                        <td class="py-3 text-slate-500"><?= htmlspecialchars($paper['department_name']) ?></td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold <?= $statusColor ?>">
                                                <?= $paper['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="<?= url('dashboard/moderator/moderate_paper.php?id=' . $paper['id']) ?>" 
                                               class="px-2.5 py-1 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors">
                                                Moderate
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Vetting level Allocations -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">My Level Allocations</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($allocations as $alloc): ?>
                        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/10 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-extrabold text-slate-900 dark:text-white block"><?= htmlspecialchars($alloc['department_name']) ?></span>
                                <span class="text-[10px] text-slate-500 font-medium">Session: <?= htmlspecialchars($alloc['session_name']) ?></span>
                            </div>
                            <span class="px-2.5 py-1 font-bold text-xs bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300 border border-brand-200 dark:border-brand-800 rounded-lg">
                                <?= htmlspecialchars($alloc['level']) ?> Level
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Right Side: Vetting Activity Timeline -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Moderator Log Feed</h3>
                
                <div class="flow-root">
                    <ul class="-mb-8">
                        <?php if (empty($activities)): ?>
                            <li class="text-xs text-slate-400 py-4 text-center">No recent activity.</li>
                        <?php else: ?>
                            <?php foreach ($activities as $idx => $act): 
                                $isLast = ($idx === count($activities) - 1);
                            ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if (!$isLast): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-100 dark:bg-slate-700" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs">
                                                    🔍
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