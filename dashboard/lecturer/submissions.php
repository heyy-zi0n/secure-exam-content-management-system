<?php
$pageTitle = "Submit Questions";
$breadcrumbs = ['Lecturer Workspace' => 'dashboard/lecturer/index.php', 'Submissions' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();
$error = '';
$success = '';

// Handle upload action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed. Please try again.";
        logSecurityEvent('CSRF_FAILURE', "CSRF failure on lecturer submission upload.", 'high');
    } else {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $examDate = $_POST['exam_date'] ?? '';
        $duration = sanitizeInput($_POST['duration'] ?? '');
        $numQuestions = (int)($_POST['num_questions'] ?? 0);
        $instructions = sanitizeInput($_POST['instructions'] ?? '');
        
        // 1. Verify lecturer is assigned to course
        $stmt = $db->prepare("
            SELECT c.*, lca.academic_session_id, lca.semester_id, s.submission_deadline, s.max_upload_size, s.allowed_file_types
            FROM lecturer_course_assignments lca
            JOIN courses c ON lca.course_id = c.id
            JOIN system_settings s ON c.department_code = s.department_code
            WHERE lca.lecturer_id = :lecturer_id AND lca.course_id = :course_id AND lca.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([
            ':lecturer_id' => $user['id'],
            ':course_id' => $courseId
        ]);
        $courseInfo = $stmt->fetch();
        
        if (!$courseInfo) {
            $error = "Unauthorized. You are not assigned to this course.";
            logSecurityEvent('UNAUTHORIZED_ACCESS_ATTEMPT', "Lecturer tried to upload unassigned course ID $courseId", 'high');
        } else {
            $dept = $courseInfo['department_code'];
            $level = $courseInfo['level'];
            $sessionId = $courseInfo['academic_session_id'];
            $semesterId = $courseInfo['semester_id'];
            $deadline = $courseInfo['submission_deadline'];
            
            // Check deadline rules (Disable upload completely if strict, but here we check and flag)
            $isPastDeadline = false;
            if ($deadline && strtotime(date('Y-m-d')) > strtotime($deadline)) {
                $isPastDeadline = true;
            }
            
            // 2. Fetch assigned moderator for this course level
            $modStmt = $db->prepare("
                SELECT moderator_id FROM moderator_level_assignments
                WHERE department_code = :dept AND level = :level AND academic_session_id = :session_id
                LIMIT 1
            ");
            $modStmt->execute([
                ':dept' => $dept,
                ':level' => $level,
                ':session_id' => $sessionId
            ]);
            $modId = $modStmt->fetchColumn();
            
            if (!$modId) {
                $error = "No moderator has been assigned by the HOD for {$level} Level in {$dept} department yet. Please contact your HOD.";
            } else {
                // File uploads validation
                $paperFile = $_FILES['exam_paper'] ?? null;
                $guideFile = $_FILES['marking_guide'] ?? null;
                $supportFile = $_FILES['supporting_files'] ?? null;
                
                if (!$paperFile || $paperFile['error'] === UPLOAD_ERR_NO_FILE) {
                    $error = "Please select the main examination paper file.";
                } else {
                    // Validate Exam Paper File
                    $allowedExts = ALLOWED_EXTENSIONS;
                    $allowedMimes = ALLOWED_MIME_TYPES;
                    
                    $paperName = $paperFile['name'];
                    $paperSize = $paperFile['size'];
                    $paperTmp = $paperFile['tmp_name'];
                    $paperExt = strtolower(pathinfo($paperName, PATHINFO_EXTENSION));
                    $paperMime = mime_content_type($paperTmp);
                    
                    if (!in_array($paperExt, $allowedExts) || !in_array($paperMime, $allowedMimes)) {
                        $error = "Invalid file type for Examination Paper. Only PDF and DOCX files are allowed.";
                        logSecurityEvent('MALICIOUS_FILE_UPLOAD', "Attempted upload of invalid file type: ext $paperExt, mime $paperMime", 'high');
                    } elseif ($paperSize > MAX_FILE_SIZE) {
                        $error = "Examination paper file is too large. Maximum size is 20MB.";
                    } else {
                        // All good. Proceed with upload
                        // Ensure temporary storage directory exists
                        if (!is_dir(UPLOAD_PATH_TEMP)) {
                            @mkdir(UPLOAD_PATH_TEMP, 0777, true);
                        }
                        
                        // Generate secure random names
                        $paperRandomName = bin2hex(random_bytes(16)) . '.' . $paperExt;
                        $paperPath = UPLOAD_PATH_TEMP . '/' . $paperRandomName;
                        
                        if (move_uploaded_file($paperTmp, $paperPath)) {
                            
                            // Handle optional Marking Guide upload
                            $guideRandomName = null;
                            $guideOrigName = null;
                            if ($guideFile && $guideFile['error'] === UPLOAD_ERR_OK) {
                                $guideExt = strtolower(pathinfo($guideFile['name'], PATHINFO_EXTENSION));
                                $guideMime = mime_content_type($guideFile['tmp_name']);
                                if (in_array($guideExt, $allowedExts) && in_array($guideMime, $allowedMimes) && $guideFile['size'] <= MAX_FILE_SIZE) {
                                    $guideRandomName = bin2hex(random_bytes(16)) . '.' . $guideExt;
                                    move_uploaded_file($guideFile['tmp_name'], UPLOAD_PATH_TEMP . '/' . $guideRandomName);
                                    $guideOrigName = $guideFile['name'];
                                }
                            }
                            
                            // Handle optional Supporting files upload
                            $supportRandomName = null;
                            $supportOrigName = null;
                            if ($supportFile && $supportFile['error'] === UPLOAD_ERR_OK) {
                                $supportExt = strtolower(pathinfo($supportFile['name'], PATHINFO_EXTENSION));
                                $supportMime = mime_content_type($supportFile['tmp_name']);
                                if (in_array($supportExt, $allowedExts) && in_array($supportMime, $allowedMimes) && $supportFile['size'] <= MAX_FILE_SIZE) {
                                    $supportRandomName = bin2hex(random_bytes(16)) . '.' . $supportExt;
                                    move_uploaded_file($supportFile['tmp_name'], UPLOAD_PATH_TEMP . '/' . $supportRandomName);
                                    $supportOrigName = $supportFile['name'];
                                }
                            }
                            
                            // Check if this course already has an active submission
                            $checkPaperStmt = $db->prepare("
                                SELECT id FROM examination_papers 
                                WHERE course_id = :course_id AND academic_session_id = :session_id AND semester_id = :semester_id 
                                LIMIT 1
                            ");
                            $checkPaperStmt->execute([
                                ':course_id' => $courseId,
                                ':session_id' => $sessionId,
                                ':semester_id' => $semesterId
                            ]);
                            $existingPaperId = $checkPaperStmt->fetchColumn();
                            
                            $db->beginTransaction();
                            try {
                                if ($existingPaperId) {
                                    // If already exists, we reject duplicate initial submission.
                                    // Updates should be done through the "View Paper Details" page as a new version!
                                    throw new Exception("This course already has an active examination paper submission. If you want to submit a revised version, please do so from the paper details page.");
                                } else {
                                    // Create new examination paper record
                                    $paperInsert = $db->prepare("
                                        INSERT INTO examination_papers 
                                        (course_id, department_code, academic_session_id, semester_id, level, exam_date, duration, num_questions, instructions, assigned_moderator_id, status, current_version_number, created_by)
                                        VALUES (:course_id, :department_code, :academic_session_id, :semester_id, :level, :exam_date, :duration, :num_questions, :instructions, :assigned_moderator_id, 'Submitted', 1, :created_by)
                                    ");
                                    $paperInsert->execute([
                                        ':course_id' => $courseId,
                                        ':department_code' => $dept,
                                        ':academic_session_id' => $sessionId,
                                        ':semester_id' => $semesterId,
                                        ':level' => $level,
                                        ':exam_date' => $examDate,
                                        ':duration' => $duration,
                                        ':num_questions' => $numQuestions,
                                        ':instructions' => $instructions,
                                        ':assigned_moderator_id' => $modId,
                                        ':created_by' => $user['id']
                                    ]);
                                    $paperId = $db->lastInsertId();
                                }
                                
                                // Insert version 1 record
                                $fileHash = hash_file('sha256', $paperPath);
                                $verInsert = $db->prepare("
                                    INSERT INTO paper_versions 
                                    (paper_id, version_number, file_path, original_filename, file_hash, file_size, marking_guide_path, marking_guide_orig, supporting_path, supporting_orig, reason_for_revision, uploaded_by)
                                    VALUES (:paper_id, 1, :file_path, :original_filename, :file_hash, :file_size, :marking_guide_path, :marking_guide_orig, :supporting_path, :supporting_orig, 'Initial submission', :uploaded_by)
                                ");
                                $verInsert->execute([
                                    ':paper_id' => $paperId,
                                    ':file_path' => $paperRandomName,
                                    ':original_filename' => $paperName,
                                    ':file_hash' => $fileHash,
                                    ':file_size' => $paperSize,
                                    ':marking_guide_path' => $guideRandomName,
                                    ':marking_guide_orig' => $guideOrigName,
                                    ':supporting_path' => $supportRandomName,
                                    ':supporting_orig' => $supportOrigName,
                                    ':uploaded_by' => $user['id']
                                ]);
                                
                                $db->commit();
                                
                                $success = "Examination question paper submitted successfully!";
                                if ($isPastDeadline) {
                                    $success .= " (Note: This is a LATE submission and has been flagged for HOD review)";
                                    // Log late submission event
                                    logAudit('Late Submission', "Lecturer submitted paper for {$courseInfo['course_code']} after deadline: $deadline");
                                } else {
                                    logAudit('Paper Uploaded', "Submitted initial examination paper for {$courseInfo['course_code']} (ID: $paperId)");
                                }
                                
                                // Send Notification to Moderator
                                sendNotification($modId, 'Review Assigned', "A new examination paper for {$courseInfo['course_code']} requires your review.");
                                
                                // Clear input variables
                                unset($courseId, $examDate, $duration, $numQuestions, $instructions);
                                
                            } catch (Exception $e) {
                                $db->rollBack();
                                // Clean up uploaded files on rollback
                                @unlink($paperPath);
                                if ($guideRandomName) @unlink(UPLOAD_PATH_TEMP . '/' . $guideRandomName);
                                if ($supportRandomName) @unlink(UPLOAD_PATH_TEMP . '/' . $supportRandomName);
                                $error = "Database error: " . $e->getMessage();
                            }
                        } else {
                            $error = "Failed to save uploaded file to disk. Please check directory permissions.";
                        }
                    }
                }
            }
        }
    }
}

// Fetch lecturer's courses for the form dropdown
$courseListStmt = $db->prepare("
    SELECT c.*, d.name as department_name, s.name as session_name, sem.name as semester_name, setts.submission_deadline
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_code = d.code
    JOIN academic_sessions s ON lca.academic_session_id = s.id
    JOIN semesters sem ON lca.semester_id = sem.id
    JOIN system_settings setts ON c.department_code = setts.department_code
    WHERE lca.lecturer_id = :lecturer_id AND lca.status = 'active'
    ORDER BY c.course_code ASC
");
$courseListStmt->execute([':lecturer_id' => $user['id']]);
$assignedCourses = $courseListStmt->fetchAll();

// Pre-selected course ID from query params
$selectedCourseId = (int)($_GET['course_id'] ?? 0);

// Fetch lecturer's submissions history
$subStmt = $db->prepare("
    SELECT ep.*, c.course_code, c.course_title, u.full_name as moderator_name, s.submission_deadline
    FROM examination_papers ep
    JOIN courses c ON ep.course_id = c.id
    JOIN users u ON ep.assigned_moderator_id = u.id
    JOIN system_settings s ON ep.department_code = s.department_code
    WHERE ep.created_by = :lecturer_id
    ORDER BY ep.created_at DESC
");
$subStmt->execute([':lecturer_id' => $user['id']]);
$submissions = $subStmt->fetchAll();
?>

<div class="space-y-8">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Examination Submissions</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Upload new examination papers and view the status of your current files.</p>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Upload Form Column -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Submit New Paper</h3>
                
                <?php if (!empty($error)): ?>
                    <div class="mb-4 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 text-xs font-semibold border border-rose-200 dark:border-rose-800">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="mb-4 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 text-xs font-semibold border border-emerald-200 dark:border-emerald-800">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($assignedCourses)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">You must be allocated to at least one course before you can upload papers.</p>
                <?php else: ?>
                    <form action="<?= url('dashboard/lecturer/submissions.php') ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?= csrfField() ?>
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Select Course</label>
                            <select name="course_id" required onchange="updateDeadlineInfo(this)"
                                    class="w-full px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">-- Choose Course --</option>
                                <?php foreach ($assignedCourses as $course): 
                                    $selected = ($course['id'] == $selectedCourseId) ? 'selected' : '';
                                ?>
                                    <option value="<?= $course['id'] ?>" <?= $selected ?> 
                                            data-deadline="<?= $course['submission_deadline'] ?>">
                                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Deadline countdown box -->
                        <div id="deadline-box" class="hidden p-3 rounded-lg border text-xs">
                            <span class="font-bold block">Submission Deadline:</span>
                            <span id="deadline-date" class="font-medium"></span>
                            <span id="deadline-timer" class="block font-bold mt-1"></span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Exam Date</label>
                                <input type="date" name="exam_date" required min="<?= date('Y-m-d') ?>"
                                       class="w-full px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Duration</label>
                                <input type="text" name="duration" placeholder="e.g. 2 Hours" required
                                       class="w-full px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Number of Questions</label>
                            <input type="number" name="num_questions" min="1" max="50" required
                                   class="w-full px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Instructions</label>
                            <textarea name="instructions" rows="2" placeholder="e.g. Answer any 4 questions..." required
                                      class="w-full px-3.5 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Upload Question Paper (PDF or DOCX)</label>
                            <input type="file" name="exam_paper" accept=".pdf,.docx" required
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 dark:file:bg-brand-950/40 dark:file:text-brand-300 hover:file:bg-brand-100 transition-all cursor-pointer border border-slate-300 dark:border-slate-600 p-2 rounded-lg bg-slate-50 dark:bg-slate-900">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Upload Marking Guide (Optional)</label>
                            <input type="file" name="marking_guide" accept=".pdf,.docx"
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 dark:file:bg-brand-950/40 dark:file:text-brand-300 hover:file:bg-brand-100 transition-all cursor-pointer border border-slate-300 dark:border-slate-600 p-2 rounded-lg bg-slate-50 dark:bg-slate-900">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Supporting Files (Optional)</label>
                            <input type="file" name="supporting_files" accept=".pdf,.docx"
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 dark:file:bg-brand-950/40 dark:file:text-brand-300 hover:file:bg-brand-100 transition-all cursor-pointer border border-slate-300 dark:border-slate-600 p-2 rounded-lg bg-slate-50 dark:bg-slate-900">
                        </div>

                        <button type="submit" id="submit-btn"
                                class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm mt-4 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>Upload Submissions</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submissions History List Column -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Submission History</h3>
                
                <?php if (empty($submissions)): ?>
                    <div class="text-center py-12 text-slate-500 dark:text-slate-400 space-y-3">
                        <span class="text-3xl">📭</span>
                        <h4 class="font-bold text-slate-800 dark:text-white">No Uploaded Papers Yet</h4>
                        <p class="text-xs max-w-sm mx-auto">Once you upload a paper, it will appear here with its moderation and lockdown status.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 text-xs font-bold uppercase tracking-wider">
                                    <th class="pb-3 font-semibold">Course</th>
                                    <th class="pb-3 font-semibold">Moderator</th>
                                    <th class="pb-3 font-semibold">Version</th>
                                    <th class="pb-3 font-semibold">Status</th>
                                    <th class="pb-3 font-semibold">Submitted On</th>
                                    <th class="pb-3 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <?php foreach ($submissions as $sub): 
                                    $isLate = ($sub['submission_deadline'] && strtotime($sub['created_at']) > strtotime($sub['submission_deadline']));
                                    
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
                                        <td class="py-3.5 pr-2 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($sub['course_code']) ?>
                                            <?php if ($isLate): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800 uppercase tracking-wide ml-1">LATE</span>
                                            <?php endif; ?>
                                            <span class="block text-xs font-normal text-slate-500 mt-0.5"><?= htmlspecialchars($sub['course_title']) ?></span>
                                        </td>
                                        <td class="py-3.5 text-xs text-slate-600 dark:text-slate-300"><?= htmlspecialchars($sub['moderator_name']) ?></td>
                                        <td class="py-3.5 text-xs font-mono">v<?= $sub['current_version_number'] ?></td>
                                        <td class="py-3.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $statusBadge ?>">
                                                <?= $sub['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-xs text-slate-500"><?= date('d M Y, H:i', strtotime($sub['created_at'])) ?></td>
                                        <td class="py-3.5 text-right">
                                            <a href="<?= url('dashboard/lecturer/view_paper.php?id=' . $sub['id']) ?>" 
                                               class="px-2.5 py-1 text-xs font-semibold border border-slate-200 dark:border-slate-700 hover:border-brand-500 rounded-lg hover:bg-brand-50 dark:hover:bg-brand-950/40 text-slate-700 dark:text-slate-300 hover:text-brand-600 transition-colors">
                                                View Pipeline
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

    </div>
</div>

<script>
function updateDeadlineInfo(select) {
    const box = document.getElementById('deadline-box');
    const timer = document.getElementById('deadline-timer');
    const dateSpan = document.getElementById('deadline-date');
    const submitBtn = document.getElementById('submit-btn');
    
    const option = select.options[select.selectedIndex];
    const deadlineVal = option.getAttribute('data-deadline');
    
    if (!deadlineVal) {
        box.classList.add('hidden');
        return;
    }
    
    box.classList.remove('hidden');
    dateSpan.textContent = new Date(deadlineVal).toLocaleDateString(undefined, {
        year: 'numeric', month: 'long', day: 'numeric'
    });
    
    // Calculate countdown
    const deadlineTime = new Date(deadlineVal + ' 23:59:59').getTime();
    
    function refreshTimer() {
        const now = new Date().getTime();
        const diff = deadlineTime - now;
        
        if (diff <= 0) {
            box.className = "p-3 rounded-lg bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 text-xs";
            timer.innerHTML = "⚠️ SUBMISSION DEADLINE EXPIRED. NEW UPLOADS WILL BE MARKED LATE.";
            timer.className = "block font-bold text-rose-600 dark:text-rose-400 mt-1 animate-pulse";
        } else {
            box.className = "p-3 rounded-lg bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 text-xs";
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            timer.innerHTML = `⏰ Countdown: ${days}d ${hours}h ${minutes}m remaining`;
            timer.className = "block font-bold text-amber-600 dark:text-amber-400 mt-1";
        }
    }
    
    refreshTimer();
    // Refresh timer periodically
    if (window.deadlineInterval) clearInterval(window.deadlineInterval);
    window.deadlineInterval = setInterval(refreshTimer, 60000);
}

// Auto-run if pre-selected course exists
document.addEventListener("DOMContentLoaded", () => {
    const select = document.querySelector('select[name="course_id"]');
    if (select && select.value) {
        updateDeadlineInfo(select);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
