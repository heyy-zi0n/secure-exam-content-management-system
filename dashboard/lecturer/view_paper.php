<?php
$pageTitle = 'Paper Details';
$breadcrumbs = [
    'Lecturer Workspace' => 'dashboard/lecturer/index.php',
    'Examination Papers' => 'dashboard/lecturer/submissions.php',
    'Paper Details' => ''
];

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../helpers/security_helper.php';
require_once __DIR__ . '/../../helpers/document_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$lecturerId = $user['id'];

$paperId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paperId <= 0) {
    flash('danger', 'Invalid paper reference.');
    redirect('dashboard/lecturer/submissions.php');
}

$paperStmt = $db->prepare("
    SELECT ep.*,
           c.course_code, c.course_title,
           d.name AS department_name,
           l.level_name,
           s.session_name,
           sem.semester_name,
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS lecturer_name
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN departments d ON ep.department_id = d.id
    JOIN levels l ON ep.level_id = l.id
    JOIN academic_sessions s ON ep.academic_session_id = s.id
    JOIN semesters sem ON ep.semester_id = sem.id
    JOIN users u ON ep.lecturer_id = u.id
    WHERE ep.id = ? AND ep.lecturer_id = ? LIMIT 1
");
$paperStmt->execute([$paperId, $lecturerId]);
$paper = $paperStmt->fetch(PDO::FETCH_ASSOC);

if (!$paper) {
    flash('danger', 'Paper not found or access denied.');
    redirect('dashboard/lecturer/submissions.php');
}

// v0.7.1 - Version history with files
$versions = getPaperVersionsWithFiles($paperId);
$currentVersionId = null;
$currentVersion = null;
foreach ($versions as $v) {
    if ((int)$v['version_number'] === (int)$paper['current_version']) {
        $currentVersion = $v;
        $currentVersionId = (int)$v['id'];
        break;
    }
}
$currentFiles = $currentVersion ? ($currentVersion['files'] ?? []) : [];

function fmtBytes_(int $b): string {
    if ($b < 1024) return "{$b} B";
    if ($b < 1024*1024) return round($b/1024, 1) . ' KB';
    return round($b/1024/1024, 2) . ' MB';
}

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

$isDraft    = ($paper['submission_status'] === 'Draft');
$isReturned = ($paper['submission_status'] === 'Returned');
$canEdit    = $isDraft || $isReturned;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="space-y-2 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-black px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                    <?= htmlspecialchars($paper['course_code']) ?>
                </span>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black <?= statusBadgeClass($paper['submission_status']) ?>">
                    <?= htmlspecialchars($paper['submission_status']) ?>
                </span>
                <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/40 px-2 py-1 rounded border border-slate-200 dark:border-slate-700">
                    v<?= (int)$paper['current_version'] ?>
                </span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white break-words"><?= htmlspecialchars($paper['paper_title']) ?></h1>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= htmlspecialchars($paper['course_title']) ?></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= url('dashboard/lecturer/submissions.php') ?>"
               class="px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded-xl transition-all shadow-sm">
                ← All Papers
            </a>
            <?php if ($canEdit): ?>
                <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>"
                   class="px-4 py-2 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                    ✏️ <?= $isReturned ? 'Revise Paper' : 'Edit Draft' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                    📋 Examination Instructions
                </h3>
                <?php if (empty(trim($paper['instructions']))): ?>
                    <div class="text-center py-10 text-slate-400 space-y-1 rounded-lg border-2 border-dashed border-slate-200 dark:border-slate-700">
                        <span class="block text-3xl">📝</span>
                        <p class="text-xs">No instructions have been added yet.</p>
                        <?php if ($canEdit): ?>
                            <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>" class="inline-block mt-2 text-[11px] font-bold text-brand-600 dark:text-brand-400 hover:underline">
                                Add Instructions →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                        <pre class="text-[13px] text-slate-800 dark:text-slate-200 whitespace-pre-wrap font-sans leading-relaxed"><?= htmlspecialchars($paper['instructions']) ?></pre>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== CURRENT ACTIVE VERSION DOCUMENTS (v0.7.1) ===== -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-4">
                <?php
                    $iconMap = [
                        'pdf'  => ['📕', 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60'],
                        'docx' => ['📘', 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/60'],
                        'zip'  => ['🗜️', 'bg-violet-50 text-violet-600 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-800/60'],
                    ];
                    $typeColor = [
                        'Question Paper'            => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                        'Marking Scheme'            => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                        'Practical Resources'       => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                        'Additional Instructions'   => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                    ];
                ?>
                <div class="flex items-start justify-between gap-4 pb-2 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            📁 Current Active Version Documents
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 border border-brand-200/60 dark:border-brand-800/60 ml-1">
                                v<?= (int)$paper['current_version'] ?>
                            </span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                            All documents belonging to the currently active version of this paper. Click a file to preview (PDF) or download (DOCX/ZIP).
                        </p>
                    </div>
                    <?php if ($canEdit): ?>
                        <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>#documents"
                           class="inline-flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/40 dark:hover:bg-brand-900/60 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-800 transition-colors">
                            🔄 Manage Files
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($currentFiles)): ?>
                    <div class="text-center py-10 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/30 space-y-1">
                        <div class="text-3xl">📭</div>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200">No documents attached to this version</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Upload question papers, marking schemes and supporting documents in the editor.</p>
                        <?php if ($canEdit): ?>
                            <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>#documents"
                               class="inline-flex mt-2 items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-md bg-brand-600 text-white hover:bg-brand-700 transition-colors">
                                📤 Upload Documents
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($currentFiles as $f):
                            $ext = strtolower($f['file_extension']);
                            $ico = $iconMap[$ext] ?? ['📄','bg-slate-100 text-slate-600 border-slate-200'];
                            $dlHref = url('dashboard/download.php?f=' . (int)$f['id']);
                            $prevHref = $ext === 'pdf' ? url('dashboard/download.php?f=' . (int)$f['id'] . '&preview=1') : $dlHref;
                            $isPdf = $ext === 'pdf';
                        ?>
                        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40 hover:border-brand-300 dark:hover:border-brand-700 transition-colors">
                            <div class="flex flex-wrap items-start gap-3">
                                <div class="w-12 h-12 shrink-0 rounded-xl border flex items-center justify-center text-2xl <?= $ico[1] ?> border-inherit">
                                    <?= $ico[0] ?>
                                </div>
                                <div class="flex-1 min-w-0 space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md <?= $typeColor[$f['file_type']] ?? 'bg-slate-100 text-slate-700' ?>">
                                            <?= htmlspecialchars($f['file_type']) ?>
                                        </span>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 break-words">
                                            <?= htmlspecialchars($f['generated_filename']) ?>
                                        </h4>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-3 gap-y-1 text-[10px]">
                                        <div><span class="text-slate-400 uppercase font-black tracking-wider block">Original</span>
                                            <span class="font-medium text-slate-700 dark:text-slate-300 break-all" title="<?= htmlspecialchars($f['original_filename']) ?>"><?= htmlspecialchars($f['original_filename']) ?></span></div>
                                        <div><span class="text-slate-400 uppercase font-black tracking-wider block">Size</span>
                                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300"><?= fmtBytes_((int)$f['file_size']) ?></span></div>
                                        <div><span class="text-slate-400 uppercase font-black tracking-wider block">Uploaded</span>
                                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= date('d M, Y H:i', strtotime($f['uploaded_at'])) ?></span></div>
                                        <div><span class="text-slate-400 uppercase font-black tracking-wider block">Uploader</span>
                                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($f['uploader_name'] ?? 'Lecturer') ?></span></div>
                                    </div>
                                    <div class="mt-1 p-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 shrink-0">SHA-256</span>
                                            <code class="font-mono text-[10px] text-slate-600 dark:text-slate-300 break-all select-all flex-1" title="<?= htmlspecialchars($f['sha256_hash']) ?>">
                                                <?= htmlspecialchars($f['sha256_hash']) ?>
                                            </code>
                                        </div>
                                    </div>

                                    <?php if ($isPdf): ?>
                                        <details class="mt-1">
                                            <summary class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 cursor-pointer hover:text-brand-600 dark:hover:text-brand-400 select-none inline-flex items-center gap-1">
                                                🛈 File Metadata
                                            </summary>
                                            <div class="mt-2 p-3 rounded-md bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[10px] grid grid-cols-1 sm:grid-cols-2 gap-2 font-mono">
                                                <div><span class="text-slate-500 dark:text-slate-400">MIME</span> <span class="text-slate-800 dark:text-slate-100"><?= htmlspecialchars($f['mime_type']) ?></span></div>
                                                <div><span class="text-slate-500 dark:text-slate-400">Extension</span> <span class="text-slate-800 dark:text-slate-100">.<?= htmlspecialchars($f['file_extension']) ?></span></div>
                                                <div class="sm:col-span-2"><span class="text-slate-500 dark:text-slate-400">Storage</span> <span class="text-slate-800 dark:text-slate-100 break-all"><?= htmlspecialchars($f['storage_path']) ?></span></div>
                                            </div>
                                        </details>
                                    <?php elseif ($ext === 'docx' || $ext === 'zip'): ?>
                                        <details class="mt-1">
                                            <summary class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 cursor-pointer hover:text-brand-600 dark:hover:text-brand-400 select-none inline-flex items-center gap-1">
                                                🛈 Metadata (<?= $ext === 'docx' ? 'DOCX container' : 'ZIP archive' ?>)
                                            </summary>
                                            <div class="mt-2 p-3 rounded-md bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[10px] grid grid-cols-1 sm:grid-cols-2 gap-2 font-mono">
                                                <div><span class="text-slate-500 dark:text-slate-400">Format</span> <span class="text-slate-800 dark:text-slate-100"><?= $ext === 'docx' ? 'Office Open XML (DOCX)' : 'ZIP Archive' ?></span></div>
                                                <div><span class="text-slate-500 dark:text-slate-400">MIME</span> <span class="text-slate-800 dark:text-slate-100"><?= htmlspecialchars($f['mime_type']) ?></span></div>
                                                <div><span class="text-slate-500 dark:text-slate-400">Size</span> <span class="text-slate-800 dark:text-slate-100"><?= fmtBytes_((int)$f['file_size']) ?> (<?= number_format($f['file_size']) ?> bytes)</span></div>
                                                <div><span class="text-slate-500 dark:text-slate-400">Extension</span> <span class="text-slate-800 dark:text-slate-100">.<?= htmlspecialchars($f['file_extension']) ?></span></div>
                                                <div class="sm:col-span-2"><span class="text-slate-500 dark:text-slate-400">Preview</span> <span class="text-slate-700 dark:text-slate-200">Direct in-browser preview unavailable. Please download to view.</span></div>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-1.5 w-full sm:w-auto justify-end">
                                    <?php if ($isPdf): ?>
                                        <a href="<?= $prevHref ?>" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 hover:bg-sky-200 dark:hover:bg-sky-900/60 transition-colors">
                                            👁 Preview PDF
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= $dlHref ?>"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold rounded-lg bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors">
                                        ⬇ Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== VERSION HISTORY ===== -->
            <?php if (!empty($versions)): ?>
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                <div class="flex items-start justify-between gap-4 pb-2 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            📚 Version History
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                            Complete audit trail of every submitted version. Previous versions are <strong>read-only</strong> and are never overwritten.
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                        <?= count($versions) ?> version<?= count($versions) === 1 ? '' : 's' ?>
                    </span>
                </div>

                <ol class="relative border-l border-slate-200 dark:border-slate-700 ml-2 space-y-6">
                    <?php foreach ($versions as $v):
                        $isCurrent = ((int)$v['version_number'] === (int)$paper['current_version']);
                        $verFiles = $v['files'] ?? [];
                    ?>
                    <li class="ml-5">
                        <div class="absolute -left-1.5 mt-1.5 w-4 h-4 rounded-full border-2 border-white dark:border-slate-800
                            <?= $isCurrent ? 'bg-brand-500 shadow-[0_0_0_3px_rgba(59,130,246,0.15)]' : 'bg-slate-300 dark:bg-slate-600' ?>"></div>

                        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/40">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="text-sm font-black font-mono text-slate-800 dark:text-slate-100">v<?= (int)$v['version_number'] ?></span>
                                <span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider
                                    <?php if ($v['submission_status']==='Draft'): ?>bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200
                                    <?php elseif ($v['submission_status']==='Submitted'): ?>bg-blue-200 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                    <?php elseif ($v['submission_status']==='Returned'): ?>bg-amber-200 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300
                                    <?php elseif ($v['submission_status']==='Approved'): ?>bg-emerald-200 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300
                                    <?php else: ?>bg-rose-200 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300
                                    <?php endif; ?>">
                                    <?= htmlspecialchars($v['submission_status']) ?>
                                </span>
                                <?php if ($isCurrent): ?>
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 border border-brand-200/60 dark:border-brand-800/60">
                                        ★ Current
                                    </span>
                                <?php endif; ?>
                                <span class="ml-auto text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                                    <?= date('d M, Y H:i', strtotime($v['created_at'])) ?>
                                </span>
                            </div>
                            <div class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">
                                Created by <strong class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($v['creator_name'] ?? 'Unknown') ?></strong>
                                · <?= count($verFiles) ?> document<?= count($verFiles) === 1 ? '' : 's' ?>
                            </div>
                            <?php if (!empty($v['change_notes'])): ?>
                                <div class="mb-2 p-2.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 text-[11px] italic text-slate-600 dark:text-slate-300">
                                    <?= htmlspecialchars($v['change_notes']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($verFiles)): ?>
                                <div class="mt-2 space-y-1.5">
                                    <?php foreach ($verFiles as $vf): ?>
                                        <div class="flex items-center gap-2 p-2 rounded-md bg-white dark:bg-slate-800/70 border border-slate-200/60 dark:border-slate-700/60 text-[10px] flex-wrap">
                                            <span class="text-base shrink-0">
                                                <?=
                                                    strtolower($vf['file_extension']) === 'pdf' ? '📕' :
                                                    (strtolower($vf['file_extension']) === 'docx' ? '📘' :
                                                    (strtolower($vf['file_extension']) === 'zip' ? '🗜️' : '📄'))
                                                ?>
                                            </span>
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold <?= $typeColor[$vf['file_type']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= htmlspecialchars($vf['file_type']) ?>
                                            </span>
                                            <span class="font-bold text-slate-700 dark:text-slate-200 min-w-0 truncate max-w-[35ch]" title="<?= htmlspecialchars($vf['generated_filename']) ?>">
                                                <?= htmlspecialchars($vf['generated_filename']) ?>
                                            </span>
                                            <span class="text-slate-400 ml-auto shrink-0 font-mono">
                                                <?= fmtBytes_((int)$vf['file_size']) ?>
                                            </span>
                                            <a href="<?= url('dashboard/download.php?f=' . (int)$vf['id']) ?>"
                                               class="text-brand-600 dark:text-brand-400 font-bold hover:underline shrink-0">Download</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>
        </div>

        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-4 text-xs">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">📌 Paper Summary</h3>

                <div class="space-y-3">
                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Course</span>
                        <div class="text-right">
                            <span class="block font-black text-slate-900 dark:text-white"><?= htmlspecialchars($paper['course_code']) ?></span>
                            <span class="block text-[10px] text-slate-500 dark:text-slate-400 max-w-[180px] truncate" title="<?= htmlspecialchars($paper['course_title']) ?>"><?= htmlspecialchars($paper['course_title']) ?></span>
                        </div>
                    </div>

                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Type</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= htmlspecialchars($paper['examination_type']) ?></span>
                    </div>

                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Session</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= htmlspecialchars($paper['session_name']) ?></span>
                    </div>

                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Semester</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= htmlspecialchars($paper['semester_name']) ?> Semester</span>
                    </div>

                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Level</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= htmlspecialchars($paper['level_name']) ?></span>
                    </div>

                    <div class="flex justify-between items-start">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Department</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= htmlspecialchars($paper['department_name']) ?></span>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700 space-y-3">
                        <div class="flex justify-between items-start">
                            <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Duration</span>
                            <span class="font-black text-brand-600 dark:text-brand-400 text-right"><?= (int)$paper['duration_minutes'] ?> minutes</span>
                        </div>

                        <div class="flex justify-between items-start">
                            <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Total Marks</span>
                            <span class="font-black text-emerald-600 dark:text-emerald-400 text-right"><?= (int)$paper['total_marks'] ?> pts</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-3 text-xs">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">⏰ Workflow Timeline</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Created</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= date('d M, Y', strtotime($paper['created_at'])) ?><span class="block text-[10px] text-slate-500 dark:text-slate-400 font-normal"><?= date('H:i', strtotime($paper['created_at'])) ?></span></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider text-[10px] pt-0.5">Last Updated</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 text-right"><?= date('d M, Y', strtotime($paper['updated_at'])) ?><span class="block text-[10px] text-slate-500 dark:text-slate-400 font-normal"><?= date('H:i', strtotime($paper['updated_at'])) ?></span></span>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-3">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">⚡ Quick Actions</h3>
                <div class="space-y-2">
                    <a href="<?= url('dashboard/lecturer/submissions.php') ?>"
                       class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-bold rounded-lg bg-slate-50 hover:bg-slate-100 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600 transition-colors">
                        📂 View All Papers
                    </a>
                    <?php if ($canEdit): ?>
                        <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>"
                           class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-bold rounded-lg bg-brand-50 hover:bg-brand-100 dark:bg-brand-900/40 dark:hover:bg-brand-900/60 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-800 transition-colors">
                            ✏️ <?= $isReturned ? 'Revise & Resubmit' : 'Edit Draft' ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('dashboard/lecturer/paper_edit.php') ?>"
                       class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-bold rounded-lg bg-white dark:bg-slate-700/40 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 transition-colors">
                        ➕ Create New Paper
                    </a>
                </div>
            </div>

            <?php if ($isReturned): ?>
                <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl shadow-sm p-5 space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">↩️</span>
                        <div class="space-y-1.5">
                            <h4 class="text-xs font-black uppercase tracking-wider text-amber-800 dark:text-amber-300">Returned for Revision</h4>
                            <p class="text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed">
                                Moderation feedback was provided for this paper. Please revise the content accordingly and re-submit. The version will be bumped to <strong>v<?= (int)$paper['current_version'] + 1 ?></strong> on re-submission.
                            </p>
                            <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>"
                               class="inline-flex items-center mt-1 px-3 py-1.5 text-[10px] font-black rounded-md bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-100 hover:bg-amber-200 dark:hover:bg-amber-900 transition-colors">
                                Open Editor →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isDraft): ?>
                <div class="bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm p-5 space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">📝</span>
                        <div class="space-y-1.5">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">Draft Saved</h4>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                                This paper is still in draft mode. No moderation workflow has been triggered. You may continue editing or submit for review when ready.
                            </p>
                            <a href="<?= url('dashboard/lecturer/paper_edit.php?id=' . (int)$paper['id']) ?>"
                               class="inline-flex items-center mt-1 px-3 py-1.5 text-[10px] font-black rounded-md bg-slate-200 dark:bg-slate-600 text-slate-800 dark:text-slate-100 hover:bg-slate-300 dark:hover:bg-slate-500 transition-colors">
                                Continue Editing →
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($paper['submission_status'] === 'Submitted'): ?>
                <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-2xl shadow-sm p-5 space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">📤</span>
                        <div class="space-y-1.5">
                            <h4 class="text-xs font-black uppercase tracking-wider text-blue-800 dark:text-blue-300">Awaiting Moderation</h4>
                            <p class="text-[11px] text-blue-700 dark:text-blue-300 leading-relaxed">
                                This paper has been submitted and is currently awaiting review by the assigned moderator. Further edits are locked until review is complete.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($paper['submission_status'] === 'Approved'): ?>
                <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl shadow-sm p-5 space-y-2">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">✅</span>
                        <div class="space-y-1.5">
                            <h4 class="text-xs font-black uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Approved & Locked</h4>
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-300 leading-relaxed">
                                This paper has been approved by moderation and is now locked. No further changes can be made to this version (v<?= (int)$paper['current_version'] ?>).
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
