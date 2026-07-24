<?php
$pageTitle = "Approved Papers Repository";
$breadcrumbs = ['Exam Officer Workspace' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('exam_officer');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Handle Re-queuing Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'queue_paper') {
    // TODO: examination_papers not yet migrated
    $error = "This feature is pending examination_papers module migration.";
}

// Fetch Approved Departmental Papers
// TODO: examination_papers, approval_records, print_queue not yet migrated
$papers = [];
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Approved Papers Repository</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Archived list of vetted examination papers. Blind Lockdown is active across all records.</p>
        </div>
    </div>

    <!-- Error/Success alerts -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-955/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-450 text-sm font-semibold">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-955/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-450 text-sm font-semibold">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Papers Grid List -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <?php if (empty($papers)): ?>
            <div class="text-center py-16 text-slate-400 space-y-3">
                <span class="text-4xl block">🔒</span>
                <h3 class="font-bold text-slate-800 dark:text-white">No Approved Papers</h3>
                <p class="text-xs max-w-sm mx-auto">Approved and locked exam papers will be routed here automatically for printing preparation.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                            <th class="pb-2">Course Details</th>
                            <th class="pb-2">Lecturer</th>
                            <th class="pb-2">Approval Cert ID</th>
                            <th class="pb-2">Exam Date</th>
                            <th class="pb-2">Queue Status</th>
                            <th class="pb-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        <?php foreach ($papers as $p): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                <td class="py-3.5 font-bold text-slate-900 dark:text-white">
                                    <?= htmlspecialchars($p['course_code']) ?>
                                    <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($p['course_title']) ?> (<?= htmlspecialchars($p['level']) ?> Level)</span>
                                </td>
                                <td class="py-3.5 text-xs text-slate-600 dark:text-slate-350"><?= htmlspecialchars($p['lecturer_name']) ?></td>
                                <td class="py-3.5 font-mono text-xs text-brand-600 dark:text-brand-400 font-bold"><?= htmlspecialchars($p['approval_id']) ?></td>
                                <td class="py-3.5 text-xs text-slate-700 dark:text-slate-300 font-medium"><?= date('d M, Y', strtotime($p['exam_date'])) ?></td>
                                <td class="py-3.5">
                                    <?php if ($p['queue_status']): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-450 uppercase">
                                            In Queue (<?= $p['queue_status'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-500 uppercase">
                                            Not in queue
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="<?= url('dashboard/view_file_secure.php?id=' . $p['id']) ?>" target="_blank"
                                           class="px-2 py-1.5 text-xs font-semibold border border-slate-200 dark:border-slate-700 hover:border-brand-500 rounded-lg hover:bg-brand-50 dark:hover:bg-brand-950/40 text-slate-700 dark:text-slate-300 hover:text-brand-600 transition-colors">
                                            View Paper
                                        </a>
                                        <?php if (!$p['queue_status']): ?>
                                            <form action="<?= url('dashboard/exam_officer/index.php') ?>" method="POST" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="queue_paper">
                                                <input type="hidden" name="paper_id" value="<?= $p['id'] ?>">
                                                <button type="submit" 
                                                        class="px-2.5 py-1.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors">
                                                    Queue Print
                                                </button>
                                            </form>
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