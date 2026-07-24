<?php
$pageTitle = "Lecturer Workspace";
$breadcrumbs = ['Lecturer Workspace' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$userId = $user['id'];
$homeDept = $user['department_code'];

// 1. Metrics Queries
$assignedCount = $db->query("SELECT COUNT(*) FROM lecturer_course_assignments WHERE lecturer_id = $userId AND status = 'active'")->fetchColumn();
$submittedCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE created_by = $userId")->fetchColumn();
$vettingCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE created_by = $userId AND status IN ('Submitted', 'Re-Submitted', 'Under Review')")->fetchColumn();
$returnedCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE created_by = $userId AND status = 'Correction Requested'")->fetchColumn();
$approvedCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE created_by = $userId AND status IN ('Approved', 'Blind Lockdown Activated', 'Ready for Printing', 'Printing Queue', 'Printed')")->fetchColumn();
$archivedCount = $db->query("SELECT COUNT(*) FROM examination_papers WHERE created_by = $userId AND status = 'Archived'")->fetchColumn();

// 2. Recent Submissions
$subStmt = $db->prepare("
    SELECT ep.*, c.course_code, c.course_title, u.full_name as moderator_name
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN users u ON ep.assigned_moderator_id = u.id
    WHERE ep.created_by = :id
    ORDER BY ep.updated_at DESC LIMIT 5
");
$subStmt->execute([':id' => $userId]);
$recentSubmissions = $subStmt->fetchAll();

// 3. Upcoming Deadlines for departments they teach in
$deadlinesStmt = $db->prepare("
    SELECT DISTINCT ss.department_code, d.name as department_name, ss.submission_deadline
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN system_settings ss ON c.department_code = ss.department_code
    JOIN departments d ON ss.department_code = d.code
    WHERE lca.lecturer_id = :id AND lca.status = 'active'
");
$deadlinesStmt->execute([':id' => $userId]);
$deadlines = $deadlinesStmt->fetchAll();

// 4. Announcements Board (their home dept + departments they teach in)
$annStmt = $db->prepare("
    SELECT DISTINCT a.*, u.full_name as author_name, d.name as department_name
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    JOIN departments d ON a.department_code = d.code
    WHERE a.department_code = :home_dept 
       OR a.department_code IN (
          SELECT DISTINCT c.department_code 
          FROM lecturer_course_assignments lca
          JOIN courses c ON lca.course_id = c.id
          WHERE lca.lecturer_id = :id AND lca.status = 'active'
       )
    ORDER BY a.created_at DESC LIMIT 3
");
$annStmt->execute([':id' => $userId, ':home_dept' => $homeDept]);
$announcements = $annStmt->fetchAll();

// 5. Recent Activity Feed
$logStmt = $db->prepare("
    SELECT al.* 
    FROM audit_logs al
    WHERE al.user_id = :id 
       OR (al.department_code = :home_dept AND al.action IN ('Blind Lockdown Activated', 'Paper Approved', 'Emergency Unlock'))
    ORDER BY al.created_at DESC LIMIT 5
");
$logStmt->execute([':id' => $userId, ':home_dept' => $homeDept]);
$activities = $logStmt->fetchAll();
?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Welcome back, <?= htmlspecialchars($user['full_name']) ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Faculty of Computing & Information Technology Exam Portal</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('dashboard/lecturer/submissions.php') ?>" class="px-4 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                <span>Submit Exam Paper</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </a>
        </div>
    </div>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Courses</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $assignedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Submissions</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $submittedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Under Review</span>
            <span class="text-2xl font-black text-blue-600 dark:text-blue-400 block mt-1"><?= $vettingCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Corrections</span>
            <span class="text-2xl font-black text-rose-600 dark:text-rose-400 block mt-1"><?= $returnedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Approved Papers</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 block mt-1"><?= $approvedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Archived</span>
            <span class="text-2xl font-black text-slate-500 block mt-1"><?= $archivedCount ?></span>
        </div>
    </div>

    <!-- Main Workspace splits -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Submissions Table & Timeline -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Recent Submissions -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">My Submissions Status</h3>
                    <a href="<?= url('dashboard/lecturer/submissions.php') ?>" class="text-xs font-bold text-brand-600 hover:underline">View All</a>
                </div>

                <?php if (empty($recentSubmissions)): ?>
                    <div class="text-center py-16 text-slate-400 space-y-2">
                        <span class="text-3xl block">📚</span>
                        <p class="text-xs">No examination papers submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                                    <th class="py-2">Course</th>
                                    <th class="py-2">Moderator</th>
                                    <th class="py-2">Version</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach ($recentSubmissions as $sub): 
                                    // Status Badge Class
                                    $statusBadge = 'bg-slate-100 text-slate-800 dark:bg-slate-700/60 dark:text-slate-300';
                                    if ($sub['status'] === 'Approved') {
                                        $statusBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400';
                                    } elseif ($sub['status'] === 'Correction Requested') {
                                        $statusBadge = 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400';
                                    } elseif ($sub['status'] === 'Blind Lockdown Activated') {
                                        $statusBadge = 'bg-brand-600 text-white dark:bg-brand-900/40 dark:text-brand-300';
                                    } elseif ($sub['status'] === 'Submitted' || $sub['status'] === 'Re-Submitted') {
                                        $statusBadge = 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($sub['course_code']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($sub['course_title']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-600 dark:text-slate-350"><?= htmlspecialchars($sub['moderator_name']) ?></td>
                                        <td class="py-3 font-mono">v<?= $sub['current_version_number'] ?></td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold <?= $statusBadge ?>">
                                                <?= $sub['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $sub['id']) ?>" class="text-[11px] font-bold text-brand-600 hover:underline">Track</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Department Announcements -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Department Bulletin Board</h3>
                
                <?php if (empty($announcements)): ?>
                    <p class="text-xs text-slate-400 py-6 text-center">No active announcements for your department.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($announcements as $ann): ?>
                            <div class="p-4 rounded-xl border border-slate-100 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/20 space-y-2">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-bold uppercase text-brand-600"><?= htmlspecialchars($ann['department_name']) ?> HOD</span>
                                    <span class="text-slate-400"><?= date('d M Y, H:i', strtotime($ann['created_at'])) ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($ann['title']) ?></h4>
                                <p class="text-[11px] text-slate-600 dark:text-slate-450 leading-relaxed"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: Deadlines, Reminders & Audit logs -->
        <div class="space-y-6">
            
            <!-- Countdown Deadlines -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Submission Deadlines</h3>
                
                <?php if (empty($deadlines)): ?>
                    <p class="text-xs text-slate-400 py-4 text-center">No assigned course deadlines.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($deadlines as $dl): 
                            $dlTime = strtotime($dl['submission_deadline'] . ' 23:59:59');
                            $isOver = time() > $dlTime;
                        ?>
                            <div class="p-3 rounded-lg border text-xs <?= $isOver ? 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-950/20 dark:border-rose-800 dark:text-rose-300' : 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/20 dark:border-amber-800 dark:text-amber-300' ?>">
                                <span class="font-bold block"><?= htmlspecialchars($dl['department_name']) ?></span>
                                <span class="block mt-0.5 font-medium">Deadline: <?= date('d M, Y', strtotime($dl['submission_deadline'])) ?></span>
                                
                                <span class="block font-bold mt-2" id="dl-cd-<?= htmlspecialchars($dl['department_code']) ?>"></span>
                                <script>
                                    (function() {
                                        const target = <?= $dlTime * 1000 ?>;
                                        const outputEl = document.getElementById("dl-cd-<?= htmlspecialchars($dl['department_code']) ?>");
                                        function tick() {
                                            const now = new Date().getTime();
                                            const diff = target - now;
                                            if (diff <= 0) {
                                                outputEl.innerHTML = "⚠️ Deadline Expired";
                                                outputEl.className = "block font-bold text-rose-600 dark:text-rose-450 mt-1";
                                            } else {
                                                const d = Math.floor(diff / 86400000);
                                                const h = Math.floor((diff % 86400000) / 3600000);
                                                const m = Math.floor((diff % 3600000) / 60000);
                                                outputEl.innerHTML = `⏰ ${d}d ${h}h ${m}m remaining`;
                                            }
                                        }
                                        tick();
                                        setInterval(tick, 60000);
                                    })();
                                </script>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Logs Feed -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider pb-3 border-b border-slate-100 dark:border-slate-700">Recent Workflow Events</h3>
                
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
                                                    ⚡
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