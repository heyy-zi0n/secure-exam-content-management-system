<?php
$pageTitle = "Assigned Courses";
$breadcrumbs = ['Lecturer Workspace' => 'dashboard/lecturer/index.php', 'Assigned Courses' => ''];

require_once __DIR__ . '/../../includes/header.php';
requireRole('lecturer');

$db = Database::getInstance();
$user = currentUser();

// Fetch assigned courses
$stmt = $db->prepare("
    SELECT c.*, d.name AS department_name, lca.assigned_date AS assignment_date, 
           s.session_name, sem.semester_name,
           l.level_name AS level
    FROM lecturer_course_assignments lca
    JOIN courses c ON lca.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN academic_sessions s ON lca.academic_session_id = s.id
    JOIN semesters sem ON c.semester_id = sem.id
    JOIN levels l ON c.level_id = l.id
    WHERE lca.lecturer_id = :lecturer_id AND lca.assignment_status = 'Active'
    ORDER BY c.course_code ASC
");
$stmt->execute([':lecturer_id' => $user['id']]);
$courses = $stmt->fetchAll();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Allocated Courses</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">View courses assigned to you by your HOD across the faculty.</p>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-lg bg-blue-50 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
            <span>Total Assigned: <?= count($courses) ?></span>
        </div>
    </div>

    <!-- Courses Grid -->
    <?php if (empty($courses)): ?>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center max-w-xl mx-auto shadow-sm space-y-4 mt-8">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-300 rounded-full flex items-center justify-center mx-auto text-2xl">
                📚
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">No Assigned Courses</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                You have not been assigned to any courses for the current academic session. Please contact your Head of Department to allocate courses.
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($courses as $course): ?>
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-6 shadow-sm hover:shadow-md hover:border-brand-500 transition-all flex flex-col justify-between">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 text-xs font-bold rounded-md bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-800">
                                <?= htmlspecialchars($course['course_code']) ?>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                <?= htmlspecialchars($course['level']) ?> Level
                            </span>
                        </div>
                        
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white leading-snug">
                                <?= htmlspecialchars($course['course_title']) ?>
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                <?= htmlspecialchars($course['department_name']) ?> Department
                            </p>
                        </div>

                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-2 gap-2 text-[11px]">
                            <div>
                                <span class="text-slate-400 block uppercase font-bold tracking-wider">Session</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($course['session_name']) ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block uppercase font-bold tracking-wider">Semester</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($course['semester_name']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400">
                            Assigned on: <?= date('d M, Y', strtotime($course['assignment_date'])) ?>
                        </span>
                        
                        <a href="<?= url('dashboard/lecturer/submissions.php?course_id=' . $course['id']) ?>" 
                           class="inline-flex items-center text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline gap-1">
                            <span>Manage Submissions</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
