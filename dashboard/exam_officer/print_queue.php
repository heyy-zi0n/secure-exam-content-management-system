<?php
$pageTitle = "Active Printing Queue";
$breadcrumbs = ['Exam Officer Workspace' => 'dashboard/exam_officer/index.php', 'Printing Queue' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('exam_officer');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Fetch active print queue for HOD/EO department
$stmt = $db->prepare("
    SELECT pq.id as queue_item_id, pq.status as queue_status,
           (SELECT COUNT(*) FROM print_logs WHERE paper_id = ep.id) AS prints_count,
           ep.*, c.course_code, c.course_title, ar.approval_id
    FROM print_queue pq
    JOIN examination_papers ep ON pq.paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    JOIN approval_records ar ON ep.id = ar.paper_id
    WHERE ep.department_code = :dept AND pq.status != 'Archived'
    ORDER BY ep.exam_date ASC
");
$stmt->execute([':dept' => $dept]);
$queueItems = $stmt->fetchAll();
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Active Printing Queue</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage print queues. Papers can only be decrypted and streamed 1 day before the exam date.</p>
    </div>

    <!-- Error/Success alerts -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-955/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-455 text-sm font-semibold font-mono">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Print Queue Table -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <?php if (empty($queueItems)): ?>
            <div class="text-center py-16 text-slate-400 space-y-3">
                <span class="text-4xl block">🖨️</span>
                <h3 class="font-bold text-slate-850 dark:text-white">Print Queue Empty</h3>
                <p class="text-xs max-w-sm mx-auto">There are no examination papers waiting to be printed in your department.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                            <th class="pb-2">Course Details</th>
                            <th class="pb-2">Approval Certificate</th>
                            <th class="pb-2">Exam Date</th>
                            <th class="pb-2">Prints</th>
                            <th class="pb-2">Lock Status</th>
                            <th class="pb-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($queueItems as $p): 
                            // Check eligibility (1 day before exam)
                            // For development flexibility: we define the date threshold
                            $examTimestamp = strtotime($p['exam_date'] . ' 00:00:00');
                            $eligibleTimestamp = strtotime($p['exam_date'] . ' -1 day 00:00:00');
                            $isEligible = time() >= $eligibleTimestamp;
                            
                            // Countdown math
                            $remainingSeconds = $eligibleTimestamp - time();
                            $daysRemaining = ceil($remainingSeconds / 86400);
                        ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                <td class="py-3.5 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($p['course_code']) ?>
                                    <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($p['course_title']) ?></span>
                                </td>
                                <td class="py-3.5 font-mono text-xs text-slate-600 dark:text-slate-350"><?= htmlspecialchars($p['approval_id']) ?></td>
                                <td class="py-3.5 text-xs text-slate-700 dark:text-slate-300 font-medium"><?= date('d M, Y', strtotime($p['exam_date'])) ?></td>
                                <td class="py-3.5 text-xs font-semibold"><?= $p['prints_count'] ?> times</td>
                                <td class="py-3.5">
                                    <?php if ($isEligible): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-450 uppercase">
                                            🔓 Unlocked
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/20 dark:text-amber-450 uppercase">
                                            🔒 Locked (<?= $daysRemaining ?> days)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($isEligible): ?>
                                            <a href="<?= url('dashboard/exam_officer/print_paper.php?id=' . $p['id']) ?>" target="_blank"
                                               class="px-3 py-1.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">
                                                Print Paper
                                            </a>
                                        <?php else: ?>
                                            <!-- Disabled Print Button with warning -->
                                            <button disabled title="Locked until 1 day before the exam date."
                                                    class="px-3 py-1.5 text-xs font-bold text-slate-400 bg-slate-100 dark:bg-slate-700 rounded-lg cursor-not-allowed">
                                                Locked
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
