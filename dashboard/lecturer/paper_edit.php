<?php
$pageTitle = 'Create Paper';
$breadcrumbs = [
    'Lecturer Workspace' => 'dashboard/lecturer/index.php',
    'Examination Papers' => 'dashboard/lecturer/submissions.php',
    'Create Paper' => ''
];

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../helpers/security_helper.php';
require_once __DIR__ . '/../../helpers/document_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$lecturerId = $user['id'];

$paperId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($paperId > 0);

$editingPaper = null;
$preselectCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$versions = [];
$currentVersionFiles = [];
$currentVersionId = 0;
$currentVersionNumber = 1;

if ($isEdit) {
    $paperStmt = $db->prepare("
        SELECT ep.*, c.course_code, c.course_title
        FROM examination_papers ep
        JOIN courses c ON ep.course_id = c.id
        WHERE ep.id = ? AND ep.lecturer_id = ? LIMIT 1
    ");
    $paperStmt->execute([$paperId, $lecturerId]);
    $editingPaper = $paperStmt->fetch(PDO::FETCH_ASSOC);
    if (!$editingPaper) {
        flash('danger', 'Paper not found or access denied.');
        redirect('dashboard/lecturer/submissions.php');
    }
    if ($editingPaper['submission_status'] !== 'Draft' && $editingPaper['submission_status'] !== 'Returned') {
        flash('danger', 'Only Draft or Returned papers can be edited.');
        redirect('dashboard/lecturer/submissions.php');
    }
    $pageTitle = ($editingPaper['submission_status'] === 'Returned') ? 'Revise Paper' : 'Edit Draft';
    $breadcrumbs['Examination Papers'] = 'dashboard/lecturer/submissions.php';
    $breadcrumbs[$pageTitle] = '';

    $versions = getPaperVersionsWithFiles($paperId);
    $currentVersionNumber = (int)$editingPaper['current_version'];
    foreach ($versions as $v) {
        if ((int)$v['version_number'] === $currentVersionNumber) {
            $currentVersionId    = (int)$v['id'];
            $currentVersionFiles = $v['files'] ?: [];
            break;
        }
    }
}

// ---------------------------------------------------------------
// Document handlers (upload / replace / delete)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_action']) && $isEdit) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        flash('danger', 'Invalid security token. Please refresh and try again.');
        redirect("dashboard/lecturer/paper_edit.php?id=$paperId#documents");
    }

    $docAction = $_POST['doc_action'];

    if ($docAction === 'upload' || $docAction === 'replace') {
        $fileType = trim((string)($_POST['file_type'] ?? ''));
        if (!in_array($fileType, PAPER_FILE_TYPES, true)) {
            flash('danger', 'Invalid document category.');
            redirect("dashboard/lecturer/paper_edit.php?id=$paperId#documents");
        }
        if (!isset($_FILES['docfile']) || !is_array($_FILES['docfile'])) {
            flash('danger', 'No file was attached.');
            redirect("dashboard/lecturer/paper_edit.php?id=$paperId#documents");
        }
        $validated = validateExamUpload($_FILES['docfile']);
        $replace = ($docAction === 'replace');
        $result  = uploadPaperDocument(
            $paperId,
            $currentVersionNumber,
            $fileType,
            $validated,
            $lecturerId,
            $replace,
            $replace ? "Replaced {$fileType} via editor" : null
        );
        if ($result['ok']) {
            flash('success', $replace
                ? $fileType . ' replaced and fingerprint recalculated successfully.'
                : $fileType . ' uploaded successfully. Fingerprint: ' . substr($validated['file']['sha256'] ?? '', 0, 12));
        } else {
            flash('danger', 'Upload failed: ' . ($result['error'] ?: 'Unknown reason.'));
        }
        redirect("dashboard/lecturer/paper_edit.php?id=$paperId#documents");
    }

    if ($docAction === 'delete') {
        $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
        if ($fileId <= 0) {
            flash('danger', 'Missing file id.');
        } else {
            $res = deletePaperFile($fileId, $lecturerId);
            flash($res['ok'] ? 'success' : 'danger',
                  $res['ok'] ? 'Document deleted from this version.' : 'Delete failed: ' . $res['error']);
        }
        redirect("dashboard/lecturer/paper_edit.php?id=$paperId#documents");
    }
}

$assignedCoursesStmt = $db->prepare("
    SELECT DISTINCT c.id, c.course_code, c.course_title,
           d.id AS department_id, d.name AS department_name,
           l.id AS level_id, l.level_name, l.level_code,
           s.id AS session_id, s.session_name,
           sem.id AS semester_id, sem.semester_name
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN levels l ON c.level_id = l.id
    JOIN academic_sessions s ON lca.academic_session_id = s.id
    JOIN semesters sem ON c.semester_id = sem.id
    WHERE lca.lecturer_id = ? AND lca.assignment_status = 'Active'
    ORDER BY s.session_name DESC, c.course_code ASC
");
$assignedCoursesStmt->execute([$lecturerId]);
$assignedCourses = $assignedCoursesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($assignedCourses)) {
    flash('warning', 'You have no active course assignments. Please contact your HOD before creating examination papers.');
}

$examTypes = [
    'Mid Semester Test',
    'Continuous Assessment',
    'Practical',
    'Final Examination',
    'Supplementary Examination'
];

$errors = [];
$form = [
    'course_id'          => $isEdit ? (int)$editingPaper['course_id']          : $preselectCourseId,
    'academic_session_id'=> $isEdit ? (int)$editingPaper['academic_session_id']: 0,
    'semester_id'        => $isEdit ? (int)$editingPaper['semester_id']        : 0,
    'department_id'      => $isEdit ? (int)$editingPaper['department_id']      : 0,
    'level_id'           => $isEdit ? (int)$editingPaper['level_id']           : 0,
    'examination_type'   => $isEdit ? $editingPaper['examination_type']        : 'Final Examination',
    'paper_title'        => $isEdit ? $editingPaper['paper_title']             : '',
    'instructions'       => $isEdit ? $editingPaper['instructions']            : '',
    'duration_minutes'   => $isEdit ? (int)$editingPaper['duration_minutes']   : 120,
    'total_marks'        => $isEdit ? (int)$editingPaper['total_marks']        : 100,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        flash('danger', 'Invalid security token. Please refresh and try again.');
        redirect('dashboard/lecturer/' . ($isEdit ? "paper_edit.php?id=$paperId" : 'paper_edit.php'));
    }

    $submitMode = $_POST['submit_mode'] ?? 'draft';
    $isSubmitReview = ($submitMode === 'submit');

    $form['course_id']          = isset($_POST['course_id'])          ? (int)$_POST['course_id']          : 0;
    $form['examination_type']   = isset($_POST['examination_type'])   ? trim((string)$_POST['examination_type']) : 'Final Examination';
    $form['paper_title']        = isset($_POST['paper_title'])        ? trim((string)$_POST['paper_title'])      : '';
    $form['instructions']       = isset($_POST['instructions'])       ? trim((string)$_POST['instructions'])     : '';
    $form['duration_minutes']   = isset($_POST['duration_minutes'])   ? (int)$_POST['duration_minutes']           : 120;
    $form['total_marks']        = isset($_POST['total_marks'])        ? (int)$_POST['total_marks']                : 100;

    $validCourse = null;
    foreach ($assignedCourses as $ac) {
        if ((int)$ac['id'] === $form['course_id']) {
            $validCourse = $ac;
            break;
        }
    }
    if (!$validCourse) {
        $errors['course_id'] = 'Please select a valid assigned course.';
    } else {
        $form['academic_session_id'] = (int)$validCourse['session_id'];
        $form['semester_id']         = (int)$validCourse['semester_id'];
        $form['department_id']       = (int)$validCourse['department_id'];
        $form['level_id']            = (int)$validCourse['level_id'];
    }

    if (!in_array($form['examination_type'], $examTypes, true)) {
        $errors['examination_type'] = 'Please select a valid examination type.';
    }

    if ($form['paper_title'] === '') {
        $errors['paper_title'] = 'Paper title is required.';
    } elseif (mb_strlen($form['paper_title']) > 255) {
        $errors['paper_title'] = 'Paper title must not exceed 255 characters.';
    }

    if ($isSubmitReview) {
        if ($form['instructions'] === '') {
            $errors['instructions'] = 'Examination instructions are required to submit for review.';
        }
    }

    if ($form['duration_minutes'] < 5 || $form['duration_minutes'] > 720) {
        $errors['duration_minutes'] = 'Duration must be between 5 and 720 minutes.';
    }

    if ($form['total_marks'] < 5 || $form['total_marks'] > 500) {
        $errors['total_marks'] = 'Total marks must be between 5 and 500.';
    }

    if (empty($errors)) {
        $newStatus = $isSubmitReview ? 'Submitted' : 'Draft';
        $previousStatus = $isEdit ? ($editingPaper['submission_status'] ?? null) : null;
        $currentVersion = $isEdit ? (int)$editingPaper['current_version'] : 1;
        $versionBumped = false;

        if ($isSubmitReview && $editingPaper && $editingPaper['submission_status'] === 'Returned') {
            $currentVersion += 1;
            $versionBumped = true;
        }

        try {
            if ($isEdit) {
                $updateStmt = $db->prepare("
                    UPDATE examination_papers SET
                        course_id = ?,
                        academic_session_id = ?,
                        semester_id = ?,
                        department_id = ?,
                        level_id = ?,
                        examination_type = ?,
                        paper_title = ?,
                        instructions = ?,
                        duration_minutes = ?,
                        total_marks = ?,
                        submission_status = ?,
                        current_version = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND lecturer_id = ?
                ");
                $updateStmt->execute([
                    $form['course_id'],
                    $form['academic_session_id'],
                    $form['semester_id'],
                    $form['department_id'],
                    $form['level_id'],
                    $form['examination_type'],
                    $form['paper_title'],
                    $form['instructions'],
                    $form['duration_minutes'],
                    $form['total_marks'],
                    $newStatus,
                    $currentVersion,
                    $paperId,
                    $lecturerId
                ]);

                // Upsert paper_versions row
                $notes = $versionBumped
                    ? "Re-submitted for review after moderator feedback (v$currentVersion)."
                    : ($isSubmitReview
                        ? "Draft submitted for review (v$currentVersion)."
                        : "Draft details updated (v$currentVersion).");
                upsertPaperVersion($paperId, $currentVersion, $lecturerId, $newStatus, $notes);

                // Relocate files if status bucket changed (e.g. Draft -> Submitted)
                if ($previousStatus !== $newStatus) {
                    $fv = $db->prepare("SELECT pf.id FROM paper_files pf
                        JOIN paper_versions pv ON pf.paper_version_id = pv.id
                        WHERE pv.examination_paper_id = ? AND pv.version_number = ?");
                    $fv->execute([$paperId, $currentVersion]);
                    foreach ($fv->fetchAll() as $row) relocateFileToStatusBucket((int)$row['id'], $newStatus);
                }

                logAudit($isSubmitReview
                    ? ($versionBumped ? 'Paper Re-Submitted' : 'Paper Submitted')
                    : 'Paper Draft Updated',
                    "Paper #$paperId ($editingPaper[course_code]) → $newStatus v$currentVersion.");

                if ($isSubmitReview) {
                    flash('success', $editingPaper['submission_status'] === 'Returned'
                        ? "Paper revised and re-submitted successfully. Version bumped to v$currentVersion."
                        : 'Draft submitted for moderation successfully.');
                    redirect('dashboard/lecturer/submissions.php?tab=submitted');
                } else {
                    flash('success', 'Draft updated successfully.');
                    redirect('dashboard/lecturer/submissions.php?tab=drafts');
                }
            } else {
                $db->beginTransaction();
                $insertStmt = $db->prepare("
                    INSERT INTO examination_papers
                    (course_id, lecturer_id, academic_session_id, semester_id, department_id, level_id,
                     examination_type, paper_title, instructions, duration_minutes, total_marks,
                     submission_status, current_version)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $form['course_id'],
                    $lecturerId,
                    $form['academic_session_id'],
                    $form['semester_id'],
                    $form['department_id'],
                    $form['level_id'],
                    $form['examination_type'],
                    $form['paper_title'],
                    $form['instructions'],
                    $form['duration_minutes'],
                    $form['total_marks'],
                    $newStatus,
                    1
                ]);
                $newPaperId = (int)$db->lastInsertId();

                // Create v1 version row immediately so file uploads can attach
                $notes = $isSubmitReview
                    ? "Initial paper created and submitted (v1)."
                    : "Initial draft paper created (v1).";
                upsertPaperVersion($newPaperId, 1, $lecturerId, $newStatus, $notes);

                $db->commit();

                logAudit($isSubmitReview ? 'Paper Submitted' : 'Paper Draft Created',
                    "New paper #$newPaperId, status $newStatus v1.");

                if ($isSubmitReview) {
                    flash('success', 'Paper submitted for moderation successfully.');
                    redirect('dashboard/lecturer/submissions.php?tab=submitted');
                } else {
                    flash('success', 'Draft paper created successfully. You may now upload examination documents.');
                    redirect("dashboard/lecturer/paper_edit.php?id=$newPaperId#documents");
                }
            }
        } catch (Exception $e) {
            $errors['db'] = 'Database error: ' . $e->getMessage();
        }
    }
}

function fieldError(string $field, array $errors): string {
    if (empty($errors[$field])) return '';
    return '<p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400 font-medium">' . htmlspecialchars($errors[$field]) . '</p>';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                <?= $isEdit ? ($editingPaper['submission_status'] === 'Returned' ? '🔄 Revise Returned Paper' : '✏️ Edit Draft Paper') : '📝 New Examination Paper' ?>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                <?= $isEdit
                    ? ($editingPaper['submission_status'] === 'Returned'
                        ? 'Address the moderator feedback and revise your paper before re-submitting.'
                        : 'Continue editing your draft paper. You may save as draft or submit for review.')
                    : 'Fill in the examination paper details. Save as draft or submit directly for moderation.' ?>
            </p>
        </div>
        <div class="flex gap-2">
            <a href="<?= url('dashboard/lecturer/submissions.php') ?>"
               class="px-4 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/80 border border-slate-200 dark:border-slate-600 rounded-xl transition-all shadow-sm">
                ← Back to Papers
            </a>
        </div>
    </div>

    <?php if (!empty($errors['db'])): ?>
        <div class="p-4 rounded-xl border bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300">
            <p class="text-sm font-medium"><?= htmlspecialchars($errors['db']) ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($assignedCourses)): ?>
        <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 p-6 rounded-2xl shadow-sm">
            <div class="flex items-start gap-4">
                <span class="text-2xl">⚠️</span>
                <div class="space-y-2">
                    <h3 class="text-base font-bold text-amber-800 dark:text-amber-300">No Courses Assigned</h3>
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        You do not have any active course assignments for this academic session. Please contact your Head of Department (HOD) to request course allocations before you can create examination papers.
                    </p>
                    <a href="<?= url('dashboard/lecturer/courses.php') ?>" class="inline-flex items-center px-3 py-1.5 text-[11px] font-bold rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 hover:bg-amber-200 dark:hover:bg-amber-900 transition-colors mt-2">
                        View Assigned Courses
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>

    <form method="POST" id="paperForm" class="space-y-6" novalidate>
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">Paper Details</h3>

                    <div>
                        <label for="course_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Assigned Course <span class="text-rose-500">*</span>
                        </label>
                        <select id="course_id" name="course_id" required
                                class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white <?= isset($errors['course_id']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors"
                                onchange="updateCourseInfo(this.value)">
                            <option value="" disabled <?= $form['course_id'] <= 0 ? 'selected' : '' ?>>-- Select an assigned course --</option>
                            <?php foreach ($assignedCourses as $ac):
                                $selected = ($form['course_id'] === (int)$ac['id']) ? 'selected' : '';
                                $safeJson = htmlspecialchars(json_encode([
                                    'session'   => $ac['session_name'],
                                    'semester'  => $ac['semester_name'],
                                    'level'     => $ac['level_name'],
                                    'dept'      => $ac['department_name'],
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                                <option value="<?= (int)$ac['id'] ?>" <?= $selected ?> data-info="<?= $safeJson ?>">
                                    <?= htmlspecialchars($ac['course_code']) ?> — <?= htmlspecialchars($ac['course_title']) ?>
                                    <span class="text-slate-400">(<?= htmlspecialchars($ac['session_name']) ?> / <?= htmlspecialchars($ac['level_name']) ?>)</span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= fieldError('course_id', $errors) ?>
                        <div id="courseInfo" class="mt-3 hidden">
                            <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 grid grid-cols-2 md:grid-cols-4 gap-3 text-[11px]">
                                <div><span class="block uppercase font-black text-slate-400 tracking-wider mb-0.5">Session</span><span id="ci_session" class="font-bold text-slate-700 dark:text-slate-300">-</span></div>
                                <div><span class="block uppercase font-black text-slate-400 tracking-wider mb-0.5">Semester</span><span id="ci_semester" class="font-bold text-slate-700 dark:text-slate-300">-</span></div>
                                <div><span class="block uppercase font-black text-slate-400 tracking-wider mb-0.5">Level</span><span id="ci_level" class="font-bold text-slate-700 dark:text-slate-300">-</span></div>
                                <div><span class="block uppercase font-black text-slate-400 tracking-wider mb-0.5">Department</span><span id="ci_dept" class="font-bold text-slate-700 dark:text-slate-300">-</span></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="paper_title" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Paper Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="paper_title" name="paper_title" required maxlength="255"
                               value="<?= htmlspecialchars($form['paper_title']) ?>"
                               placeholder="e.g. CSC101 Final Examination — First Semester 2025/2026"
                               class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder:text-slate-400 <?= isset($errors['paper_title']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors">
                        <?= fieldError('paper_title', $errors) ?>
                    </div>

                    <div>
                        <label for="examination_type" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Examination Type <span class="text-rose-500">*</span>
                        </label>
                        <select id="examination_type" name="examination_type" required
                                class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white <?= isset($errors['examination_type']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors">
                            <?php foreach ($examTypes as $et):
                                $sel = ($form['examination_type'] === $et) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($et) ?>" <?= $sel ?>><?= htmlspecialchars($et) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= fieldError('examination_type', $errors) ?>
                    </div>

                    <div>
                        <label for="instructions" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Examination Instructions <span class="text-slate-400 font-normal">(required to submit)</span>
                        </label>
                        <textarea id="instructions" name="instructions" rows="8"
                                  placeholder="Enter examination instructions for candidates. Example:&#10;1. Answer ALL questions in Section A.&#10;2. Answer any THREE (3) questions in Section B.&#10;3. Total marks: 100. Duration: 3 hours.&#10;4. Use of non-programmable calculators is permitted."
                                  class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder:text-slate-400 <?= isset($errors['instructions']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors font-mono leading-relaxed"><?= htmlspecialchars($form['instructions']) ?></textarea>
                        <?= fieldError('instructions', $errors) ?>
                    </div>
                </div>

                <!-- ===== EXAMINATION DOCUMENTS (v0.7.1) ===== -->
                <?php if ($isEdit): ?>
                <div id="documents" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                    <div class="flex items-start justify-between gap-4 pb-2 border-b border-slate-100 dark:border-slate-700">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Examination Documents
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                Upload question papers, marking schemes, practical resources, or additional instructions.
                                Files stored securely (max <?= round(MAX_FILE_SIZE / 1024 / 1024, 0) ?> MB · <?= implode(', ', array_map('strtoupper', ALLOWED_EXTENSIONS)) ?> only).
                                <span class="text-rose-500/80 font-semibold">Question Paper is required before submitting.</span>
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 dark:bg-slate-700/60 text-[10px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            v<?= (int)$currentVersionNumber ?> • <?= htmlspecialchars((string)($editingPaper['submission_status'] ?? 'Draft')) ?>
                        </span>
                    </div>

                    <?php
                        $hasQuestion = false;
                        foreach ($currentVersionFiles as $f) if ($f['file_type'] === 'Question Paper') { $hasQuestion = true; break; }
                        $fileTypeUsed = [];
                        foreach ($currentVersionFiles as $f) $fileTypeUsed[$f['file_type']] = $f;
                    ?>

                    <?php if (!$hasQuestion): ?>
                        <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
                            <span class="text-lg">⚠️</span>
                            <div class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
                                <span class="font-black uppercase tracking-wider">Required</span><br>
                                A <strong>Question Paper</strong> document must be uploaded before you can submit for moderation.
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Uploaded files list -->
                    <?php if (!empty($currentVersionFiles)): ?>
                    <div class="space-y-2">
                        <?php
                            $iconMap = [
                                'pdf'  => ['📕', 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60'],
                                'docx' => ['📘', 'bg-sky-50 text-sky-600 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/60'],
                                'zip'  => ['🗜️', 'bg-violet-50 text-violet-600 border-violet-200 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-800/60'],
                            ];
                            function fmtBytes(int $b): string {
                                if ($b < 1024) return "{$b} B";
                                if ($b < 1024*1024) return round($b/1024, 1) . ' KB';
                                return round($b/1024/1024, 2) . ' MB';
                            }
                            $typeColor = [
                                'Question Paper'          => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                'Marking Scheme'          => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                'Practical Resources'     => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
                                'Additional Instructions' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                            ];
                        ?>
                        <?php foreach ($currentVersionFiles as $f):
                            $ext = strtolower($f['file_extension']);
                            $ico = $iconMap[$ext] ?? ['📄','bg-slate-100 text-slate-600 border-slate-200'];
                        ?>
                        <div class="group p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-brand-300 dark:hover:border-brand-700 transition-colors bg-slate-50/60 dark:bg-slate-900/40">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-11 h-11 shrink-0 rounded-lg border flex items-center justify-center text-xl <?= $ico[1] ?> border-inherit">
                                    <?= $ico[0] ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-md <?= $typeColor[$f['file_type']] ?? 'bg-slate-100 text-slate-700' ?>">
                                            <?= htmlspecialchars($f['file_type']) ?>
                                        </span>
                                        <span class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate max-w-[22ch] md:max-w-[40ch]" title="<?= htmlspecialchars($f['generated_filename']) ?>">
                                            <?= htmlspecialchars($f['generated_filename']) ?>
                                        </span>
                                    </div>
                                    <div class="mt-1 text-[10px] text-slate-500 dark:text-slate-400 flex flex-wrap gap-x-3 gap-y-0.5">
                                        <span>Original: <span class="font-semibold text-slate-600 dark:text-slate-300"><?= htmlspecialchars($f['original_filename']) ?></span></span>
                                        <span>Size: <span class="font-mono"><?= fmtBytes((int)$f['file_size']) ?></span></span>
                                        <span>Type: <span class="font-mono text-[9px] uppercase"><?= htmlspecialchars($f['mime_type']) ?></span></span>
                                        <span>Uploaded: <span class="font-semibold"><?= date('d M, Y H:i', strtotime($f['uploaded_at'])) ?></span></span>
                                        <span>By: <span class="font-semibold"><?= htmlspecialchars($f['uploader_name'] ?? 'You') ?></span></span>
                                    </div>
                                    <div class="mt-1 text-[10px] flex items-center gap-1.5">
                                        <span class="font-black uppercase tracking-wider text-slate-400">SHA-256</span>
                                        <code class="font-mono text-slate-600 dark:text-slate-300 break-all select-all" title="<?= htmlspecialchars($f['sha256_hash']) ?>">
                                            <?= htmlspecialchars($f['sha256_hash']) ?>
                                        </code>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto">
                                    <a href="<?= url('dashboard/download.php?f=' . (int)$f['id']) ?>" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 hover:bg-sky-200 dark:hover:bg-sky-900/60 transition-colors">
                                        <?php if ($ext === 'pdf'): echo '👁 Preview'; else: echo '⬇ Download'; endif; ?>
                                    </a>
                                    <!-- Replace -->
                                    <form method="POST" enctype="multipart/form-data" class="inline-flex items-center gap-1 m-0">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="doc_action" value="replace">
                                        <input type="hidden" name="file_type" value="<?= htmlspecialchars($f['file_type']) ?>">
                                        <input type="file" name="docfile" accept=".pdf,.docx,.zip" required
                                               onchange="if(confirm('Replace this <?= htmlspecialchars($f['file_type']) ?> with a new file? The old file will be permanently deleted.')){ uploadWithProgress(this); this.form.submit(); } else { this.value=''; }"
                                               class="hidden" id="repfile_<?= (int)$f['id'] ?>">
                                        <button type="button" onclick="document.getElementById('repfile_<?= (int)$f['id'] ?>').click()"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/60 transition-colors">
                                            🔄 Replace
                                        </button>
                                    </form>
                                    <!-- Delete -->
                                    <form method="POST" class="inline-flex m-0"
                                          onsubmit="return confirm('Delete this <?= htmlspecialchars($f['file_type']) ?>? This action cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="doc_action" value="delete">
                                        <input type="hidden" name="file_id" value="<?= (int)$f['id'] ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-900/60 transition-colors">
                                            🗑 Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div id="emptyFiles" class="text-center py-8 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/40 dark:bg-slate-900/30">
                        <div class="text-3xl mb-2">📎</div>
                        <p class="text-sm font-bold text-slate-700 dark:text-slate-200">No documents uploaded yet</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Use the upload form below to add your examination documents.</p>
                    </div>
                    <?php endif; ?>

                    <!-- Drag-and-drop upload -->
                    <?php if ($editingPaper['submission_status'] === 'Draft' || $editingPaper['submission_status'] === 'Returned'): ?>
                    <div class="mt-2 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-3">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="doc_action" value="upload">
                            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_FILE_SIZE ?>">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label for="file_type" class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">Document Category</label>
                                    <select id="file_type" name="file_type" required
                                            class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                                        <option value="">-- Select category --</option>
                                        <?php foreach (PAPER_FILE_TYPES as $ft):
                                            $used = isset($fileTypeUsed[$ft]);
                                            $dis  = $used ? 'disabled' : '';
                                            $tag  = $used ? '  (already uploaded)' : ($ft === 'Question Paper' ? '  ★ Required' : '');
                                        ?>
                                            <option value="<?= htmlspecialchars($ft) ?>" <?= $dis ?>>
                                                <?= htmlspecialchars($ft . $tag) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1">File Attachment</label>
                                    <div id="dropZone"
                                         class="border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-brand-400 dark:hover:border-brand-600 rounded-lg bg-white dark:bg-slate-800 p-4 cursor-pointer transition-colors">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 shrink-0 rounded-xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-300 flex items-center justify-center text-2xl">⬆️</div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                                    Drag & drop your file here, or <span class="text-brand-600 dark:text-brand-400 underline">click to browse</span>
                                                </p>
                                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                    Accepted: <?= implode(', ', array_map('strtoupper', ALLOWED_EXTENSIONS)) ?> · Max <?= round(MAX_FILE_SIZE/1024/1024, 0) ?> MB
                                                </p>
                                                <input id="docfile" type="file" name="docfile" accept=".pdf,.docx,.zip" required
                                                       class="hidden" onchange="showFileInfo(this)">
                                            </div>
                                            <div id="fileInfo" class="hidden text-right">
                                                <div class="text-[11px] font-bold text-slate-700 dark:text-slate-200 max-w-[18ch] truncate" id="fileName">-</div>
                                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono" id="fileSize">-</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div id="progressWrap" class="hidden">
                                <div class="h-2 w-full bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                    <div id="progressBar" class="h-full bg-gradient-to-r from-brand-500 to-emerald-500 transition-all" style="width:0%"></div>
                                </div>
                                <p class="text-[10px] mt-1 font-mono text-slate-500 dark:text-slate-400" id="progressTxt">Preparing upload…</p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="text-[10px] text-slate-500 dark:text-slate-400 mr-auto">
                                    Each file is fingerprinted with SHA-256 and audited on upload.
                                </span>
                                <button id="uploadBtn" type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-[11px] font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                    📤 Upload Document
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($versions) && count($versions) > 1): ?>
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700">
                        <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">📜 Version History</h4>
                        <ol class="relative border-l border-slate-200 dark:border-slate-700 ml-2 space-y-3">
                            <?php foreach ($versions as $v): ?>
                            <li class="ml-4">
                                <div class="absolute -left-1.5 mt-1 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800 <?=
                                    (int)$v['version_number'] === (int)$currentVersionNumber
                                    ? 'bg-brand-500'
                                    : 'bg-slate-300 dark:bg-slate-600'
                                ?>"></div>
                                <div class="text-[11px]">
                                    <div class="font-bold text-slate-800 dark:text-slate-100">
                                        Version <span class="font-mono">v<?= (int)$v['version_number'] ?></span>
                                        <span class="ml-2 inline-block px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider
                                            <?php if ($v['submission_status']==='Draft'): ?>bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200
                                            <?php elseif ($v['submission_status']==='Submitted'): ?>bg-blue-200 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300
                                            <?php elseif ($v['submission_status']==='Returned'): ?>bg-amber-200 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300
                                            <?php elseif ($v['submission_status']==='Approved'): ?>bg-emerald-200 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300
                                            <?php else: ?>bg-rose-200 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300
                                            <?php endif; ?>">
                                            <?= htmlspecialchars($v['submission_status']) ?>
                                        </span>
                                    </div>
                                    <div class="text-slate-500 dark:text-slate-400 text-[10px]">
                                        <?= count($v['files'] ?? []) ?> file(s) · Created by <?= htmlspecialchars($v['creator_name'] ?? 'Unknown') ?> ·
                                        <?= date('d M, Y H:i', strtotime($v['created_at'])) ?>
                                    </div>
                                    <?php if (!empty($v['change_notes'])): ?>
                                        <p class="mt-1 text-[10px] italic text-slate-600 dark:text-slate-300">
                                            &ldquo;<?= htmlspecialchars($v['change_notes']) ?>&rdquo;
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="p-4 rounded-xl bg-slate-100 dark:bg-slate-700/60 border border-slate-200 dark:border-slate-600 text-[11px] text-slate-600 dark:text-slate-300">
                        🔒 Documents for this paper are currently <strong>read-only</strong> because the submission status is
                        <strong><?= htmlspecialchars($editingPaper['submission_status']) ?></strong>. No changes can be made until the paper returns to Draft or Returned.
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-300 dark:border-slate-700 text-[11px] text-slate-600 dark:text-slate-300 space-y-1">
                    <p class="font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                        📎 Examination Documents
                    </p>
                    <p>
                        Save this paper as a <strong>Draft</strong> first. You will then be able to upload question papers,
                        marking schemes, practical resources, and additional instructions with full SHA-256 fingerprint
                        and version history.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">Settings</h3>

                    <div>
                        <label for="duration_minutes" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Duration (minutes)
                        </label>
                        <div class="relative">
                            <input type="number" id="duration_minutes" name="duration_minutes" min="5" max="720" step="5"
                                   value="<?= (int)$form['duration_minutes'] ?>"
                                   class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white <?= isset($errors['duration_minutes']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors pr-12 font-bold">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black uppercase tracking-wider text-slate-400">min</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <?php foreach ([30, 60, 90, 120, 180] as $mins): ?>
                                <button type="button" onclick="document.getElementById('duration_minutes').value = <?= $mins ?>"
                                        class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-50 hover:bg-slate-100 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 transition-colors">
                                    <?= $mins ?>m
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?= fieldError('duration_minutes', $errors) ?>
                    </div>

                    <div>
                        <label for="total_marks" class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                            Total Marks
                        </label>
                        <div class="relative">
                            <input type="number" id="total_marks" name="total_marks" min="5" max="500" step="5"
                                   value="<?= (int)$form['total_marks'] ?>"
                                   class="w-full px-3.5 py-2.5 text-sm rounded-lg border bg-white dark:bg-slate-900 text-slate-900 dark:text-white <?= isset($errors['total_marks']) ? 'border-rose-400 dark:border-rose-600 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-600 focus:ring-brand-500' ?> focus:outline-none focus:ring-2 focus:ring-opacity-40 transition-colors pr-12 font-bold">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black uppercase tracking-wider text-slate-400">pts</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <?php foreach ([30, 40, 50, 100, 150] as $mk): ?>
                                <button type="button" onclick="document.getElementById('total_marks').value = <?= $mk ?>"
                                        class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-slate-50 hover:bg-slate-100 dark:bg-slate-700/60 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 transition-colors">
                                    <?= $mk ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?= fieldError('total_marks', $errors) ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-3 text-[11px]">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">Paper Metadata</h3>
                        <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400 font-semibold">Current Status</span><span class="font-black"><?= htmlspecialchars($editingPaper['submission_status']) ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400 font-semibold">Current Version</span><span class="font-black font-mono">v<?= (int)$editingPaper['current_version'] ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400 font-semibold">Created</span><span class="font-bold text-slate-700 dark:text-slate-300"><?= date('d M, Y', strtotime($editingPaper['created_at'])) ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400 font-semibold">Last Updated</span><span class="font-bold text-slate-700 dark:text-slate-300"><?= date('d M, Y H:i', strtotime($editingPaper['updated_at'])) ?></span></div>
                        <?php if ($editingPaper['submission_status'] === 'Returned'): ?>
                            <div class="mt-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                                <p class="text-[10px] font-black uppercase tracking-wider text-amber-700 dark:text-amber-300 mb-1">💡 Tip</p>
                                <p class="text-[11px] text-amber-800 dark:text-amber-300">Re-submitting this returned paper will automatically bump the version to <strong>v<?= (int)$editingPaper['current_version'] + 1 ?></strong>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 pb-2 border-b border-slate-100 dark:border-slate-700">Save & Submit</h3>
                    <button type="submit" name="submit_mode" value="draft"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-50 hover:bg-slate-100 dark:bg-slate-700/60 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl transition-all shadow-sm">
                        💾 Save as Draft
                    </button>
                    <button type="submit" name="submit_mode" value="submit"
                            onclick="return confirm('Submit this paper for moderation now? You will not be able to make further changes until it is returned or approved.');"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-all shadow-sm">
                        📤 Submit for Review
                    </button>
                    <a href="<?= url('dashboard/lecturer/submissions.php') ?>"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function() {
    const select = document.getElementById('course_id');
    const infoContainer = document.getElementById('courseInfo');
    const fields = {
        session:  document.getElementById('ci_session'),
        semester: document.getElementById('ci_semester'),
        level:    document.getElementById('ci_level'),
        dept:     document.getElementById('ci_dept'),
    };

    function updateFromSelected() {
        if (!select || !select.value) {
            if (infoContainer) infoContainer.classList.add('hidden');
            return;
        }
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.dataset.info) {
            if (infoContainer) infoContainer.classList.add('hidden');
            return;
        }
        try {
            const info = JSON.parse(opt.dataset.info);
            fields.session.textContent  = info.session  || '-';
            fields.semester.textContent = info.semester || '-';
            fields.level.textContent    = info.level    || '-';
            fields.dept.textContent     = info.dept     || '-';
            infoContainer.classList.remove('hidden');
        } catch(e) {
            infoContainer.classList.add('hidden');
        }
    }

    if (typeof window.updateCourseInfo !== 'function') {
        window.updateCourseInfo = updateFromSelected;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateFromSelected);
    } else {
        updateFromSelected();
    }
    if (select) select.addEventListener('change', updateFromSelected);

    // --- Drag & drop + file info + progress (v0.7.1) ---
    function fmtB(n) {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n/1024).toFixed(1) + ' KB';
        return (n/1048576).toFixed(2) + ' MB';
    }
    window.showFileInfo = function(inp) {
        const info = document.getElementById('fileInfo');
        if (!info) return;
        if (inp.files && inp.files[0]) {
            document.getElementById('fileName').textContent = inp.files[0].name;
            document.getElementById('fileSize').textContent = fmtB(inp.files[0].size);
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
        }
    };
    window.uploadWithProgress = function(inp) {
        const wrap = document.getElementById('progressWrap');
        const bar  = document.getElementById('progressBar');
        const txt  = document.getElementById('progressTxt');
        const btn  = document.getElementById('uploadBtn');
        if (!wrap) return;
        wrap.classList.remove('hidden');
        let pct = 0;
        bar.style.width = '0%';
        if (txt) txt.textContent = 'Preparing upload…';
        if (btn) { btn.disabled = true; btn.value = btn.textContent = 'Uploading…'; }
        const t = setInterval(function(){
            pct += (inp.files && inp.files[0] ? Math.max(2, Math.min(10, 70 / Math.max(1, inp.files[0].size/200000))) : 5);
            if (pct >= 95) pct = 95;
            bar.style.width = pct + '%';
            if (txt) txt.textContent = 'Uploading… ' + Math.floor(pct) + '%';
            if (pct >= 95) clearInterval(t);
        }, 80);
    };

    document.addEventListener('DOMContentLoaded', function(){
        const form = document.getElementById('uploadForm');
        const drop = document.getElementById('dropZone');
        const fileInput = document.getElementById('docfile');
        if (form) {
            form.addEventListener('submit', function(e){
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    // default HTML5 required already catches this
                    return;
                }
                uploadWithProgress(fileInput);
            });
        }
        if (drop && fileInput) {
            ['dragenter','dragover'].forEach(function(ev){
                drop.addEventListener(ev, function(e){
                    e.preventDefault(); e.stopPropagation();
                    drop.classList.add('border-brand-500','bg-brand-50/50','dark:bg-brand-900/30');
                });
            });
            ['dragleave','dragexit','drop'].forEach(function(ev){
                drop.addEventListener(ev, function(e){
                    e.preventDefault(); e.stopPropagation();
                    drop.classList.remove('border-brand-500','bg-brand-50/50','dark:bg-brand-900/30');
                });
            });
            drop.addEventListener('click', function(){ fileInput.click(); });
            drop.addEventListener('drop', function(e){
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files[0]) {
                    const dtFile = new DataTransfer();
                    dtFile.items.add(dt.files[0]);
                    fileInput.files = dtFile.files;
                    showFileInfo(fileInput);
                }
            });
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
