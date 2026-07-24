<?php
$pageTitle = "Course Routing";
$breadcrumbs = ['HOD Workspace' => 'dashboard/hod/index.php', 'Course Routing' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('hod');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Handle Add Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_course') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $code = strtoupper(sanitizeInput($_POST['course_code'] ?? ''));
        $title = sanitizeInput($_POST['course_title'] ?? '');
        $level = (int)($_POST['level'] ?? 100);
        
        if (empty($code) || empty($title)) {
            $error = "Course code and course title are required.";
        } else {
            // Check if course already exists globally or in department
            $cCheck = $db->prepare("SELECT id FROM courses WHERE course_code = :code LIMIT 1");
            $cCheck->execute([':code' => $code]);
            if ($cCheck->fetch()) {
                $error = "A course with code $code already exists in the system.";
            } else {
                $cInsert = $db->prepare("
                    INSERT INTO courses (course_code, course_title, department_code, level)
                    VALUES (:code, :title, :dept, :level)
                ");
                $cInsert->execute([
                    ':code'  => $code,
                    ':title' => $title,
                    ':dept'  => $dept,
                    ':level' => $level
                ]);
                
                logAudit('Course Created', "Created course $code: $title");
                $success = "Course $code was successfully added to the department catalog.";
            }
        }
    }
}

// Handle Allocate Lecturer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'allocate_lecturer') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? 0);
        
        if (!$courseId || !$lecturerId || !$sessionId || !$semesterId) {
            $error = "All allocation form fields are required.";
        } else {
            // Verify if allocation already exists
            $aCheck = $db->prepare("
                SELECT id FROM lecturer_course_assignments 
                WHERE course_id = :course_id 
                  AND lecturer_id = :lec_id 
                  AND academic_session_id = :sess_id 
                  AND semester_id = :sem_id 
                  AND status = 'active'
                LIMIT 1
            ");
            $aCheck->execute([
                ':course_id' => $courseId,
                ':lec_id'    => $lecturerId,
                ':sess_id'   => $sessionId,
                ':sem_id'    => $semesterId
            ]);
            
            if ($aCheck->fetch()) {
                $error = "This lecturer is already allocated to this course for the selected session and semester.";
            } else {
                $aInsert = $db->prepare("
                    INSERT INTO lecturer_course_assignments (course_id, lecturer_id, academic_session_id, semester_id, status)
                    VALUES (:course_id, :lec_id, :sess_id, :sem_id, 'active')
                ");
                $aInsert->execute([
                    ':course_id' => $courseId,
                    ':lec_id'    => $lecturerId,
                    ':sess_id'   => $sessionId,
                    ':sem_id'    => $semesterId
                ]);
                
                // Fetch course details for logging
                $cDetails = $db->query("SELECT course_code FROM courses WHERE id = $courseId")->fetchColumn();
                // Fetch lecturer details
                $lDetails = $db->query("SELECT email FROM users WHERE id = $lecturerId")->fetchColumn();
                
                logAudit('Course Allocated', "Allocated $cDetails to lecturer $lDetails");
                $success = "Lecturer successfully allocated to $cDetails.";
            }
        }
    }
}

// Fetch Department Courses
$courseStmt = $db->prepare("SELECT * FROM courses WHERE department_code = :dept ORDER BY course_code ASC");
$courseStmt->execute([':dept' => $dept]);
$courses = $courseStmt->fetchAll();

// Fetch Active Lecturer Allocations in HOD Department
$allocStmt = $db->prepare("
    SELECT lca.*, c.course_code, c.course_title, u.full_name as lecturer_name, u.department_code as lecturer_home_dept, s.name as session_name, sem.name as semester_name
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN users u ON lca.lecturer_id = u.id
    JOIN academic_sessions s ON lca.academic_session_id = s.id
    JOIN semesters sem ON lca.semester_id = sem.id
    WHERE c.department_code = :dept AND lca.status = 'active'
    ORDER BY c.course_code ASC
");
$allocStmt->execute([':dept' => $dept]);
$allocations = $allocStmt->fetchAll();

// Fetch All System Lecturers (supporting cross-departmental routing)
$lecturers = $db->query("SELECT id, full_name, email, department_code FROM users WHERE role = 'lecturer' ORDER BY full_name ASC")->fetchAll();

// Fetch Academic Sessions & Semesters
$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
$semesters = $db->query("SELECT * FROM semesters ORDER BY name ASC")->fetchAll();
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Course Allocations & Routing</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage courses and allocate lecturers across academic sessions.</p>
        </div>
    </div>

    <!-- Error/Success Alerts -->
    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-955/20 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-400 text-sm font-semibold">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-955/20 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-sm font-semibold">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Split Grid: Forms vs Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Allocation Forms -->
        <div class="space-y-6 lg:col-span-1">
            
            <!-- Create Course Form -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Create New Course</h3>
                
                <form action="<?= url('dashboard/hod/courses.php') ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_course">
                    
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Course Code</label>
                        <input type="text" name="course_code" required placeholder="e.g. CSC221"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500 font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Course Title</label>
                        <input type="text" name="course_title" required placeholder="e.g. Artificial Intelligence"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Course Level</label>
                        <select name="level" required
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="100">100 Level</option>
                            <option value="200">200 Level</option>
                            <option value="300">300 Level</option>
                            <option value="400">400 Level</option>
                            <option value="500">500 Level</option>
                        </select>
                    </div>

                    <button type="submit" 
                            class="w-full py-2.5 px-4 rounded-lg font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm mt-2">
                        Add Course
                    </button>
                </form>
            </div>

            <!-- Allocate Lecturer Form -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Allocate Lecturer</h3>
                
                <form action="<?= url('dashboard/hod/courses.php') ?>" method="POST" class="space-y-4 text-xs">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="allocate_lecturer">
                    
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Course</label>
                        <select name="course_id" required
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= htmlspecialchars($c['course_code']) ?> - <?= htmlspecialchars($c['course_title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Lecturer (supports cross-dept)</label>
                        <select name="lecturer_id" required
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="">-- Select Lecturer --</option>
                            <?php foreach ($lecturers as $lec): ?>
                                <option value="<?= $lec['id'] ?>">
                                    <?= htmlspecialchars($lec['full_name']) ?> (Home: <?= $lec['department_code'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Session</label>
                            <select name="session_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Semester</label>
                            <select name="semester_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['id'] ?>"><?= htmlspecialchars($sem['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full py-2.5 px-4 rounded-lg font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm mt-2">
                        Allocate Route
                    </button>
                </form>
            </div>

        </div>

        <!-- Right: Allocation Grid/Table -->
        <div class="space-y-6 lg:col-span-2">
            
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Active Allocation Map</h3>
                
                <?php if (empty($allocations)): ?>
                    <p class="text-xs text-slate-400 py-12 text-center">No active lecturer allocations for courses in this department.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                                    <th class="pb-2">Course</th>
                                    <th class="pb-2">Lecturer</th>
                                    <th class="pb-2">Home Dept</th>
                                    <th class="pb-2">Session / Sem</th>
                                    <th class="pb-2">Date Allocated</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($alloc['course_code']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($alloc['course_title']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-750 dark:text-slate-300 font-medium"><?= htmlspecialchars($alloc['lecturer_name']) ?></td>
                                        <td class="py-3 font-mono"><?= htmlspecialchars($alloc['lecturer_home_dept']) ?></td>
                                        <td class="py-3 text-slate-600 dark:text-slate-400">
                                            <?= htmlspecialchars($alloc['session_name']) ?>
                                            <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($alloc['semester_name']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-450"><?= date('d M Y', strtotime($alloc['assignment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Department Catalog -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Course Catalog</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($courses as $c): ?>
                        <div class="p-3 border border-slate-100 dark:border-slate-700 bg-slate-50/30 dark:bg-slate-900/10 rounded-xl flex items-center justify-between text-xs">
                            <div>
                                <span class="font-extrabold text-slate-900 dark:text-white block"><?= htmlspecialchars($c['course_code']) ?></span>
                                <span class="text-[10px] text-slate-400 font-semibold"><?= htmlspecialchars($c['course_title']) ?></span>
                            </div>
                            <span class="px-2 py-0.5 rounded bg-slate-200/50 dark:bg-slate-700 text-[10px] font-bold text-slate-600 dark:text-slate-350">
                                <?= $c['level'] ?> Level
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
