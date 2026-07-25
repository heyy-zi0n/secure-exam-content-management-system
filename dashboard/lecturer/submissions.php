<?php
$pageTitle = 'Examination Papers';
$breadcrumbs = [
    'Lecturer Workspace' => 'dashboard/lecturer/index.php',
    'Examination Papers' => ''
];

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$lecturerId = $user['id'];

$activeTab = $_GET['tab'] ?? 'all';
$allowedTabs = ['all', 'drafts', 'submitted', 'returned', 'approved'];
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        flash('danger', 'Invalid security token. Please refresh and try again.');
        redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
    }

    $action = $_POST['action'] ?? '';
    $paperId = isset($_POST['paper_id']) ? (int)$_POST['paper_id'] : 0;

    if ($paperId <= 0) {
        flash('danger', 'Invalid paper reference.');
        redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
    }

    $verifyStmt = $db->prepare("SELECT id, submission_status, course_id FROM examination_papers WHERE id = ? AND lecturer_id = ? LIMIT 1");
    $verifyStmt->execute([$paperId, $lecturerId]);
    $paper = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$paper) {
        flash('danger', 'Paper not found or access denied.');
        redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
    }

    if ($action === 'delete') {
        if ($paper['submission_status'] !== 'Draft') {
            flash('danger', 'Only Draft papers can be deleted.');
            redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
        }
        $delStmt = $db->prepare("DELETE FROM examination_papers WHERE id = ? AND lecturer_id = ?");
        $delStmt->execute([$paperId, $lecturerId]);
        flash('success', 'Draft paper deleted successfully.');
        redirect('dashboard/lecturer/submissions.php?tab=drafts');
    }

    if ($action === 'resubmit') {
        if ($paper['submission_status'] !== 'Returned') {
            flash('danger', 'Only Returned papers can be re-submitted.');
            redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
        }
        $newVersion = $paper['current_version'] + 1;
        $updStmt = $db->prepare("UPDATE examination_papers SET submission_status = 'Submitted', current_version = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND lecturer_id = ?");
        $updStmt->execute([$newVersion, $paperId, $lecturerId]);
        flash('success', 'Paper re-submitted successfully. Version bumped to v' . $newVersion . '.');
        redirect('dashboard/lecturer/submissions.php?tab=submitted');
    }

    flash('warning', 'Unknown action.');
    redirect('dashboard/lecturer/submissions.php?tab=' . $activeTab);
}

$countSql = "SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = ?";
$countStmt = $db->prepare($countSql);
$countStmt->execute([$lecturerId]);
$totalPapers = (int)$countStmt->fetchColumn();

$draftCountStmt = $db->prepare($countSql . " AND submission_status = 'Draft'");
$draftCountStmt->execute([$lecturerId]);
$draftCount = (int)$draftCountStmt->fetchColumn();

$submittedCountStmt = $db->prepare($countSql . " AND submission_status = 'Submitted'");
$submittedCountStmt->execute([$lecturerId]);
$submittedCount = (int)$submittedCountStmt->fetchColumn();

$returnedCountStmt = $db->prepare($countSql . " AND submission_status = 'Returned'");
$returnedCountStmt->execute([$lecturerId]);
$returnedCount = (int)$returnedCountStmt->fetchColumn();

$approvedCountStmt = $db->prepare($countSql . " AND submission_status = 'Approved'");
$approvedCountStmt->execute([$lecturerId]);
$approvedCount = (int)$approvedCountStmt->fetchColumn();

$papersSql = "
    SELECT ep.id, ep.paper_title, ep.examination_type, ep.submission_status, ep.current_version,
           ep.duration_minutes, ep.total_marks, ep.created_at, ep.updated_at,
           c.course_code, c.course_title,
           d.name AS department_name,
           l.level_name,
           s.session_name,
           sem.semester_name
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON ep.department_id = d.id
    JOIN levels l ON ep.level_id = l.id
    JOIN academic_sessions s ON ep.academic_session_id = s.id
    JOIN semesters sem ON ep.semester_id = sem.id
    WHERE ep.lecturer_id = ?
";

$params = [$lecturerId];
if ($activeTab === 'drafts') {
    $papersSql .= " AND ep.submission_status = 'Draft'";
} elseif ($activeTab === 'submitted') {
    $papersSql .= " AND ep.submission_status = 'Submitted'";
} elseif ($activeTab === 'returned') {
    $papersSql .= " AND ep.submission_status = 'Returned'";
} elseif ($activeTab === 'approved') {
    $papersSql .= " AND ep.submission_status = 'Approved'";
}

$papersSql .= " ORDER BY ep.updated_at DESC, ep.created_at DESC";
$papersStmt = $db->prepare($papersSql);
$papersStmt->execute($params);
$papers = $papersStmt->fetchAll(PDO::FETCH_ASSOC);

function statusBadgeClass(string $status): string {
    $map = [
        'Draft'     => 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300 border border-slate-200 dark:border-slate-600',
        'Submitted' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
        'Returned'  => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
        'Approved'  => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
        'Rejected'  => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
    ];
    return $map[$status] ?? $map['Draft'];
}

$tabItems = [
    'all'       => ['label' => 'All Papers', 'count' => $totalPapers,     'icon' => '📄'],
    'drafts'    => ['label' => 'Drafts',     'count' => $draftCount,      'icon' => '📝'],
    'submitted' => ['label' => 'Submitted',  'count' => $submittedCount,  'icon' => '📤'],
    'returned'  => ['label' => 'Returned',   'count' => $returnedCount,   'icon' => '↩️'],
    'approved'  => ['label' => 'Approved',   'count' => $approvedCount,   'icon' => '✅'],
];
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Examination Papers</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create, manage, and submit examination papers for your assigned courses.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('dashboard/lecturer/courses.php') ?>" class="px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                <span>View Courses</span>
            </a>
            <a href="<?= url('dashboard/lecturer/paper_edit.php') ?>" class="px-4 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Paper</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Papers</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $totalPapers ?></span>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Drafts</span>
            <span class="text-2xl font-black text-slate-700 dark:text-slate-300 block mt-1"><?= $draftCount ?></span>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Submitted</span>
            <span class="text-2xl font-black text-blue-600 dark:text-blue-400 block mt-1"><?= $submittedCount ?></span>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Returned</span>
            <span class="text-2xl font-black text-amber-600 dark:text-amber-400 block mt-1"><?= $returnedCount ?></span>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Approved</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 block mt-1"><?= $approvedCount ?></span>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="flex flex-wrap" aria-label="Tabs">
                <?php foreach ($tabItems as $tabKey => $tab): ?>
                    <?php $isActive = ($activeTab === $tabKey); ?>
                    <a href="<?= url('dashboard/lecturer/submissions.php?tab=' . $tabKey) ?>"
                       class="px-4 sm:px-6 py-4 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors whitespace-nowrap <?= $isActive ? 'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600' ?>">
                        <span class="mr-1.5"><?= $tab['icon'] ?></span>
                        <?= htmlspecialchars($tab['label']) ?>
                        <span class="ml-2 px-2 py-0.5 rounded-full text-[9px] font-black <?= $isActive ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' ?>"><?= $tab['count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-4 sm:p-6">
            <?php if (empty($papers)): ?>
                <div class="text-center py-16 space-y-3">
                    <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-300 rounded-full flex items-center justify-center mx-auto text-2xl">
                        📂
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        <?= $activeTab === 'drafts'    ? 'No Draft Papers' :
                            ($activeTab === 'submitted' ? 'No Submitted Papers' :
                            ($activeTab === 'returned'  ? 'No Returned Papers' :
                            ($activeTab === 'approved'  ? 'No Approved Papers' : 'No Papers Yet'))) ?>
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                        <?= $activeTab === 'all' || $activeTab === 'drafts'
                            ? 'Click "New Paper" above to create a new examination paper draft for an assigned course.'
                            : 'Papers will appear in this section once they move to this status.' ?>
                    </p>
                    <?php if ($activeTab === 'all' || $activeTab === 'drafts'): ?>
                        <div class="pt-4">
                            <a href="<?= url('dashboard/lecturer/paper_edit.php') ?>" class="inline-flex items-center px-4 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Create First Paper
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto -mx-4 sm:-mx-6 px-4 sm:px-6">
                    <table class="w-full text-left text-xs min-w-[900px]">
                        <thead>
                            <tr class="text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200 dark:border-slate-700">
                                <th class="py-3 pr-4">Course / Paper</th>
                                <th class="py-3 pr-4">Type</th>
                                <th class="py-3 pr-4">Session / Level</th>
                                <th class="py-3 pr-4">Duration / Marks</th>
                                <th class="py-3 pr-4">Version / Status</th>
                                <th class="py-3 pr-4">Updated</th>
                                <th class="py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <?php foreach ($papers as $p):
                                $isDraft    = ($p['submission_status'] === 'Draft');
                                $isReturned = ($p['submission_status'] === 'Returned');
                                $isSubmitted = ($p['submission_status'] === 'Submitted');
                                $isApproved  = ($p['submission_status'] === 'Approved');
                                $canEdit     = $isDraft || $isReturned;
                            ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                                    <td class="py-4 pr-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-100 dark:border-brand-800 flex items-center justify-center text-brand-700 dark:text-brand-300 font-black text-[10px] shrink-0">
                                                <?= htmlspecialchars(substr($p['course_code'], 0, 3)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-2">
                                                    <span class="text-[11px] font-black px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300"><?= htmlspecialchars($p['course_code']) ?></span>
                                                    <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>" class="hover:text-brand-600 dark:hover:text-brand-400 hover:underline truncate max-w-[320px] block">
                                                        <?= htmlspecialchars($p['paper_title']) ?>
                                                    </a>
                                                </div>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-[420px]"><?= htmlspecialchars($p['course_title']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-slate-50 dark:bg-slate-700/50 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                                            <?= htmlspecialchars($p['examination_type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="space-y-0.5">
                                            <span class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($p['session_name']) ?></span>
                                            <span class="block text-[10px] text-slate-500 dark:text-slate-400"><?= htmlspecialchars($p['level_name']) ?> · <?= htmlspecialchars($p['semester_name']) ?> Sem</span>
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="space-y-0.5">
                                            <span class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300"><?= (int)$p['duration_minutes'] ?> min</span>
                                            <span class="block text-[10px] text-slate-500 dark:text-slate-400"><?= (int)$p['total_marks'] ?> marks</span>
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="flex flex-col gap-1.5 items-start">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black <?= statusBadgeClass($p['submission_status']) ?>">
                                                <?= htmlspecialchars($p['submission_status']) ?>
                                            </span>
                                            <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400">v<?= (int)$p['current_version'] ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 pr-4">
                                        <div class="space-y-0.5">
                                            <span class="block text-[11px] font-medium text-slate-700 dark:text-slate-300"><?= date('d M, Y', strtotime($p['updated_at'])) ?></span>
                                            <span class="block text-[10px] text-slate-500 dark:text-slate-400"><?= date('H:i', strtotime($p['updated_at'])) ?></span>
                                        </div>
                                    </td>
                                    <td class="py-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $p['id']) ?>"
                                               class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-slate-50 hover:bg-slate-100 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 transition-colors"
                                               title="View paper details">
                                                👁 View
                                            </a>
                                            <?php if ($canEdit): ?>
                                                <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . $p['id']) ?>"
                                                   class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/40 dark:hover:bg-brand-900/60 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-800 transition-colors"
                                                   title="<?= $isReturned ? 'Revise & resubmit' : 'Edit draft' ?>">
                                                    ✏️ <?= $isReturned ? 'Revise' : 'Edit' ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($isReturned): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Re-submit this paper now? The version number will be bumped automatically.');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action" value="resubmit">
                                                    <input type="hidden" name="paper_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/50 dark:hover:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 transition-colors"
                                                            title="Re-submit paper for moderation">
                                                        📤 Re-Submit
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($isDraft): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete this draft paper permanently? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="paper_id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit"
                                                            class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/50 dark:hover:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 transition-colors"
                                                            title="Delete draft paper">
                                                        🗑 Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($isSubmitted): ?>
                                                <span class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-slate-100 dark:bg-slate-700/40 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 cursor-not-allowed">
                                                    ⏳ Under Review
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($isApproved): ?>
                                                <span class="inline-flex items-center px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 cursor-default">
                                                    ✅ Locked
                                                </span>
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
