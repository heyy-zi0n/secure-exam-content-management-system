<?php
$pageTitle = "Lecturer Workspace";
$breadcrumbs = ['Lecturer Workspace' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';
require_once __DIR__ . '/../../helpers/document_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$userId = $user['id'];
$homeDept = $user['department_code'];

// 1. Metrics Queries
$assignedCount = $db->query("SELECT COUNT(*) FROM lecturer_course_assignments WHERE lecturer_id = $userId AND assignment_status = 'Active'")->fetchColumn();

$submittedCount = (int)$db->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = $userId AND submission_status = 'Submitted'")->fetchColumn();
$returnedCount  = (int)$db->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = $userId AND submission_status = 'Returned'")->fetchColumn();
$approvedCount  = (int)$db->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = $userId AND submission_status = 'Approved'")->fetchColumn();
$draftCount     = (int)$db->query("SELECT COUNT(*) FROM examination_papers WHERE lecturer_id = $userId AND submission_status = 'Draft'")->fetchColumn();
$totalPaperCount = $submittedCount + $returnedCount + $approvedCount + $draftCount;

// ---- v0.7.1: Document Management statistics ----
$totalFilesStmt = $db->prepare("
    SELECT COUNT(*)
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    WHERE ep.lecturer_id = ?
");
$totalFilesStmt->execute([$userId]);
$totalFilesUploaded = (int)$totalFilesStmt->fetchColumn();

$storageUsedStmt = $db->prepare("
    SELECT COALESCE(SUM(pf.file_size), 0)
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    WHERE ep.lecturer_id = ?
");
$storageUsedStmt->execute([$userId]);
$storageUsedBytes = (int)$storageUsedStmt->fetchColumn();
$storageUsedFormatted = '';
if ($storageUsedBytes < 1024) $storageUsedFormatted = "$storageUsedBytes B";
elseif ($storageUsedBytes < 1024*1024) $storageUsedFormatted = round($storageUsedBytes/1024, 1) . ' KB';
else $storageUsedFormatted = round($storageUsedBytes/1024/1024, 2) . ' MB';

$latestUploadStmt = $db->prepare("
    SELECT pf.uploaded_at, pf.generated_filename, c.course_code, pv.version_number
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    JOIN courses c ON ep.course_id = c.id
    WHERE ep.lecturer_id = ?
    ORDER BY pf.uploaded_at DESC
    LIMIT 1
");
$latestUploadStmt->execute([$userId]);
$latestUpload = $latestUploadStmt->fetch() ?: null;

$draftFilesStmt = $db->prepare("
    SELECT COUNT(*)
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    WHERE ep.lecturer_id = ? AND pv.submission_status = 'Draft'
");
$draftFilesStmt->execute([$userId]);
$draftFilesCount = (int)$draftFilesStmt->fetchColumn();

$submittedFilesStmt = $db->prepare("
    SELECT COUNT(*)
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    WHERE ep.lecturer_id = ? AND pv.submission_status = 'Submitted'
");
$submittedFilesStmt->execute([$userId]);
$submittedFilesCount = (int)$submittedFilesStmt->fetchColumn();

$approvedFilesStmt = $db->prepare("
    SELECT COUNT(*)
    FROM paper_files pf
    JOIN paper_versions pv ON pf.paper_version_id = pv.id
    JOIN examination_papers ep ON pv.examination_paper_id = ep.id
    WHERE ep.lecturer_id = ? AND pv.submission_status = 'Approved'
");
$approvedFilesStmt->execute([$userId]);
$approvedFilesCount = (int)$approvedFilesStmt->fetchColumn();
// ---- END v0.7.1 document stats ----

// 2. Recent Submissions — display last 5 papers
$recentStmt = $db->prepare("
    SELECT ep.id, ep.paper_title, ep.submission_status AS status, ep.current_version AS current_version_number,
           ep.updated_at, ep.created_at,
           c.course_code, c.course_title
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    WHERE ep.lecturer_id = :lecturer_id
    ORDER BY ep.updated_at DESC
    LIMIT 5
");
$recentStmt->execute([':lecturer_id' => $userId]);
$recentSubmissions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Recent Activity Feed — synthetic from examination_papers timeline
$activities = [];
$activityStmt = $db->prepare("
    SELECT id, paper_title, submission_status, updated_at, created_at,
           submission_status AS status
    FROM examination_papers
    WHERE lecturer_id = :lecturer_id
    ORDER BY updated_at DESC
    LIMIT 6
");
$activityStmt->execute([':lecturer_id' => $userId]);
$activityRows = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activityRows as $row) {
    $titleShort = strlen($row['paper_title']) > 40 ? substr($row['paper_title'], 0, 37) . '...' : $row['paper_title'];
    if ($row['status'] === 'Draft') {
        $activities[] = [
            'action'      => 'Draft saved',
            'description' => "\"$titleShort\" saved as draft",
            'created_at'  => $row['updated_at']
        ];
    } elseif ($row['status'] === 'Submitted') {
        $activities[] = [
            'action'      => 'Paper submitted',
            'description' => "\"$titleShort\" submitted for moderation",
            'created_at'  => $row['updated_at']
        ];
    } elseif ($row['status'] === 'Returned') {
        $activities[] = [
            'action'      => 'Correction requested',
            'description' => "\"$titleShort\" returned with feedback",
            'created_at'  => $row['updated_at']
        ];
    } elseif ($row['status'] === 'Approved') {
        $activities[] = [
            'action'      => '✅ Paper approved',
            'description' => "\"$titleShort\" passed moderation review",
            'created_at'  => $row['updated_at']
        ];
    } elseif ($row['status'] === 'Rejected') {
        $activities[] = [
            'action'      => '❌ Paper rejected',
            'description' => "\"$titleShort\" was rejected by moderator",
            'created_at'  => $row['updated_at']
        ];
    }
}
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

    <!-- Metrics Cards Grid (Papers) -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Courses</span>
            <span class="text-2xl font-black text-slate-900 dark:text-white block mt-1"><?= $assignedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Draft Papers</span>
            <span class="text-2xl font-black text-slate-700 dark:text-slate-300 block mt-1"><?= $draftCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Submitted</span>
            <span class="text-2xl font-black text-blue-600 dark:text-blue-400 block mt-1"><?= $submittedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Returned</span>
            <span class="text-2xl font-black text-rose-600 dark:text-rose-400 block mt-1"><?= $returnedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Approved</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 block mt-1"><?= $approvedCount ?></span>
        </div>

        <div class="p-4 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Papers</span>
            <span class="text-2xl font-black text-slate-500 block mt-1"><?= $totalPaperCount ?></span>
        </div>
    </div>

    <!-- Document Management Stats (v0.7.1) -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 dark:border-slate-700">
            <div>
                <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    📁 Secure Document Management — Lecturer Examination Documents
                </h3>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Summary of document volume across all paper versions. All files stored securely with SHA-256 fingerprints.
                </p>
            </div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 text-right">
                <div>
                    <span class="font-bold text-slate-700 dark:text-slate-200">Latest Upload:</span>
                    <?php if ($latestUpload): ?>
                        <span class="block font-mono mt-0.5" title="<?= htmlspecialchars($latestUpload['generated_filename']) ?>">
                            <?= htmlspecialchars($latestUpload['course_code']) ?> v<?= (int)$latestUpload['version_number'] ?> · <?= date('d M, H:i', strtotime($latestUpload['uploaded_at'])) ?>
                        </span>
                    <?php else: ?>
                        <span class="block italic">— No documents uploaded yet.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="p-3 rounded-xl bg-gradient-to-br from-sky-50 to-white dark:from-sky-950/40 dark:to-slate-800 border border-sky-100 dark:border-sky-900/50">
                <div class="text-[9px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">Total Files</div>
                <div class="text-xl font-black text-slate-800 dark:text-white mt-1"><?= $totalFilesUploaded ?></div>
                <div class="text-[9px] text-sky-700/70 dark:text-sky-400/70 mt-1">across all versions</div>
            </div>

            <div class="p-3 rounded-xl bg-gradient-to-br from-slate-50 to-white dark:from-slate-700/40 dark:to-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="text-[9px] font-bold uppercase tracking-wider text-slate-600 dark:text-slate-400">Draft Files</div>
                <div class="text-xl font-black text-slate-700 dark:text-slate-200 mt-1"><?= $draftFilesCount ?></div>
                <div class="text-[9px] text-slate-500 dark:text-slate-500 mt-1">editable</div>
            </div>

            <div class="p-3 rounded-xl bg-gradient-to-br from-blue-50 to-white dark:from-blue-950/40 dark:to-slate-800 border border-blue-100 dark:border-blue-900/50">
                <div class="text-[9px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">Submitted Files</div>
                <div class="text-xl font-black text-blue-700 dark:text-blue-300 mt-1"><?= $submittedFilesCount ?></div>
                <div class="text-[9px] text-blue-700/70 dark:text-blue-400/70 mt-1">under review</div>
            </div>

            <div class="p-3 rounded-xl bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-950/40 dark:to-slate-800 border border-emerald-100 dark:border-emerald-900/50">
                <div class="text-[9px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Approved Files</div>
                <div class="text-xl font-black text-emerald-700 dark:text-emerald-300 mt-1"><?= $approvedFilesCount ?></div>
                <div class="text-[9px] text-emerald-700/70 dark:text-emerald-400/70 mt-1">locked</div>
            </div>

            <div class="p-3 rounded-xl bg-gradient-to-br from-violet-50 to-white dark:from-violet-950/40 dark:to-slate-800 border border-violet-100 dark:border-violet-900/50 lg:col-span-2">
                <div class="text-[9px] font-bold uppercase tracking-wider text-violet-700 dark:text-violet-400">💾 Storage Used</div>
                <div class="flex items-end gap-2 flex-wrap mt-1">
                    <div class="text-xl font-black text-violet-800 dark:text-violet-300"><?= $storageUsedFormatted ?></div>
                    <div class="flex-1 min-w-[80px] h-1.5 bg-violet-100 dark:bg-violet-950/60 rounded-full overflow-hidden">
                        <?php
                            $pct = 0;
                            $limit = MAX_FILE_SIZE * 50; // assume 50-file soft-quota display guide
                            if ($limit > 0) $pct = min(100, (int)($storageUsedBytes / $limit * 100));
                        ?>
                        <div class="h-full bg-gradient-to-r from-violet-500 to-brand-500 transition-all" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="text-[9px] text-violet-700/70 dark:text-violet-400/70 font-mono"><?= $pct ?>% of guide</span>
                </div>
                <?php if ($totalFilesUploaded > 0): ?>
                    <div class="text-[9px] text-violet-700/70 dark:text-violet-400/70 mt-1">
                        Average file: <?= round($storageUsedBytes / $totalFilesUploaded / 1024, 1) ?> KB avg · <?= number_format($totalFilesUploaded) ?> file(s)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Workspace splits -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Submissions Table & Timeline -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Recent Submissions -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">My Papers — Recent Activity</h3>
                    <a href="<?= url('dashboard/lecturer/submissions.php') ?>" class="text-xs font-bold text-brand-600 hover:underline">View All</a>
                </div>

                <?php if (empty($recentSubmissions)): ?>
                    <div class="text-center py-16 text-slate-400 space-y-2">
                        <span class="text-3xl block">📚</span>
                        <p class="text-xs">No examination papers submitted yet.</p>
                        <div class="pt-3">
                            <a href="<?= url('dashboard/lecturer/paper_edit.php') ?>" class="inline-flex items-center px-3 py-1.5 text-[11px] font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Create First Paper
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase border-b border-slate-100 dark:border-slate-700 pb-2">
                                    <th class="py-2">Course</th>
                                    <th class="py-2">Paper Title</th>
                                    <th class="py-2">Version</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <?php foreach ($recentSubmissions as $sub): 
                                    $statusBadge = 'bg-slate-100 text-slate-800 dark:bg-slate-700/60 dark:text-slate-300';
                                    if ($sub['status'] === 'Approved') {
                                        $statusBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400';
                                    } elseif ($sub['status'] === 'Returned') {
                                        $statusBadge = 'bg-amber-100 text-amber-800 dark:bg-amber-950/30 dark:text-amber-400';
                                    } elseif ($sub['status'] === 'Rejected') {
                                        $statusBadge = 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400';
                                    } elseif ($sub['status'] === 'Submitted') {
                                        $statusBadge = 'bg-blue-100 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400';
                                    } elseif ($sub['status'] === 'Draft') {
                                        $statusBadge = 'bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($sub['course_code']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($sub['course_title']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-600 dark:text-slate-350 max-w-[220px] truncate" title="<?= htmlspecialchars($sub['paper_title']) ?>">
                                            <?= htmlspecialchars($sub['paper_title']) ?>
                                        </td>
                                        <td class="py-3 font-mono">v<?= (int)$sub['current_version_number'] ?></td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold <?= $statusBadge ?>">
                                                <?= htmlspecialchars($sub['status']) ?>
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