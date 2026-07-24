<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

$pageTitle  = 'Academic Sessions';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'Academic Sessions' => ''];

requireRole('admin');

$db = Database::getInstance();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['session_id'])) {
    validateCsrfToken($_POST['csrf_token'] ?? '');
    $sessionId = (int)$_POST['session_id'];
    
    if ($_POST['action'] === 'set_active') {
        try {
            $db->beginTransaction();
            $db->exec("UPDATE academic_sessions SET is_current = 0");
            $stmt = $db->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?");
            $stmt->execute([$sessionId]);
            
            $db->exec("UPDATE semesters SET is_active = 0");
            $semStmt = $db->prepare("UPDATE semesters SET is_active = 1 WHERE academic_session_id = ? AND semester_name = 'First'");
            $semStmt->execute([$sessionId]);
            
            $db->commit();
            $success = "Academic session activated successfully.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to activate session: " . $e->getMessage();
        }
    }
}

$sessions = $db->query("
    SELECT s.id, s.session_name, s.start_date, s.end_date, s.is_current, s.created_at,
           (SELECT COUNT(*) FROM semesters sm WHERE sm.academic_session_id = s.id) as semester_count
    FROM academic_sessions s
    ORDER BY s.session_name DESC
")->fetchAll();

$semestersBySession = [];
foreach ($sessions as $s) {
    $semStmt = $db->prepare("
        SELECT id, semester_name, is_active, start_date, end_date
        FROM semesters
        WHERE academic_session_id = ?
        ORDER BY semester_name ASC
    ");
    $semStmt->execute([$s['id']]);
    $semestersBySession[$s['id']] = $semStmt->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Academic Sessions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage academic sessions and set the currently active session.</p>
        </div>
        <span class="px-3 py-1 text-xs font-bold rounded-full bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-700">
            <?= count($sessions) ?> sessions
        </span>
    </div>

    <?php if (isset($success)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-300">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php foreach ($sessions as $s): ?>
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl <?= $s['is_current'] ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-300' ?> flex items-center justify-center text-xl font-bold shrink-0">
                        <?= substr($s['session_name'], 2, 2) ?>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($s['session_name']) ?></h2>
                            <?php if ($s['is_current']): ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    Active
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400 border border-slate-200 dark:border-slate-600">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            <?= date('M j, Y', strtotime($s['start_date'])) ?> – <?= date('M j, Y', strtotime($s['end_date'])) ?>
                            • <?= $s['semester_count'] ?> semesters
                        </p>
                    </div>
                </div>
                <?php if (!$s['is_current']): ?>
                    <form method="POST" class="shrink-0">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="set_active">
                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Set as Active
                        </button>
                    </form>
                <?php else: ?>
                    <span class="shrink-0 inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Currently Active
                    </span>
                <?php endif; ?>
            </div>
            <div class="p-5">
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-3">Semesters</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach ($semestersBySession[$s['id']] as $sem): ?>
                        <div class="rounded-xl border border-slate-100 dark:border-slate-700/60 p-4 bg-slate-50/50 dark:bg-slate-700/20 flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($sem['semester_name']) ?> Semester</span>
                                    <?php if ($sem['is_active']): ?>
                                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Active</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                    <?= date('M j', strtotime($sem['start_date'])) ?> – <?= date('M j, Y', strtotime($sem['end_date'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
