<?php
$pageTitle = "Moderator Allocations";
$breadcrumbs = ['HOD Workspace' => 'dashboard/hod/index.php', 'Moderator Level Allocations' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('hod');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Handle Allocation Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'allocate_moderator') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $modId = (int)($_POST['moderator_id'] ?? 0);
        $level = (int)($_POST['level'] ?? 100);
        $sessionId = (int)($_POST['session_id'] ?? 0);
        
        if (!$modId || !$level || !$sessionId) {
            $error = "All fields are required.";
        } else {
            // Check if this moderator is already assigned to this level for the session
            $chk = $db->prepare("
                SELECT ma.id FROM moderator_assignments ma
                JOIN departments d ON ma.department_id = d.id
                JOIN levels l ON ma.level_id = l.id
                WHERE d.code = :dept 
                  AND l.level_code = :level 
                  AND ma.academic_session_id = :sess_id
                LIMIT 1
            ");
            $chk->execute([
                ':dept'    => $dept,
                ':level'   => (string)$level,
                ':sess_id' => $sessionId
            ]);
            
            if ($chk->fetch()) {
                $error = "A moderator is already allocated to {$level} Level in this department for the selected session. Remove that assignment first to reallocate.";
            } else {
                $ins = $db->prepare("
                    INSERT INTO moderator_assignments (moderator_id, department_id, level_id, academic_session_id, assigned_by)
                    SELECT :mod_id, d.id, l.id, :sess_id, :assigned_by
                    FROM departments d, levels l
                    WHERE d.code = :dept AND l.level_code = :level
                ");
                $ins->execute([
                    ':mod_id'      => $modId,
                    ':sess_id'     => $sessionId,
                    ':assigned_by' => $user['id'],
                    ':dept'        => $dept,
                    ':level'       => (string)$level
                ]);
                
                $modEmail = $db->query("SELECT email FROM users WHERE id = $modId")->fetchColumn();
                logAudit('Moderator Allocated', "Allocated moderator $modEmail to {$level} Level for session ID $sessionId");
                $success = "Moderator allocated to {$level} Level successfully.";
            }
        }
    }
}

// Handle Delete Allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_allocation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $allocId = (int)($_POST['allocation_id'] ?? 0);
        
        // Fetch info before delete for logging
        $infoStmt = $db->prepare("
            SELECT ma.*, l.level_code AS level, u.email 
            FROM moderator_assignments ma
            JOIN users u ON ma.moderator_id = u.id
            JOIN departments d ON ma.department_id = d.id
            JOIN levels l ON ma.level_id = l.id
            WHERE ma.id = :id AND d.code = :dept
        ");
        $infoStmt->execute([':id' => $allocId, ':dept' => $dept]);
        $info = $infoStmt->fetch();
        
        if ($info) {
            $del = $db->prepare("DELETE FROM moderator_assignments WHERE id = :id");
            $del->execute([':id' => $allocId]);
            
            logAudit('Moderator Deallocated', "Removed moderator {$info['email']} from {$info['level']} Level allocation.");
            $success = "Moderator allocation removed successfully.";
        } else {
            $error = "Allocation not found or unauthorized deletion.";
        }
    }
}

// Fetch Active Moderator Assignments in HOD Department
$mlaStmt = $db->prepare("
    SELECT ma.id, l.level_code AS level,
           CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS moderator_name,
           u.email AS moderator_email, s.session_name AS session_name
    FROM moderator_assignments ma
    JOIN users u ON ma.moderator_id = u.id
    JOIN departments d ON ma.department_id = d.id
    JOIN levels l ON ma.level_id = l.id
    JOIN academic_sessions s ON ma.academic_session_id = s.id
    WHERE d.code = :dept
    ORDER BY l.level_code ASC
");
$mlaStmt->execute([':dept' => $dept]);
$allocations = $mlaStmt->fetchAll();

// Fetch Department Moderators
$modStmt = $db->prepare("
    SELECT u.id, CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name, u.email
    FROM users u
    JOIN roles r ON u.role_id = r.id
    JOIN departments d ON u.department_id = d.id
    WHERE r.role_code = 'moderator' AND d.code = :dept
    ORDER BY u.first_name ASC
");
$modStmt->execute([':dept' => $dept]);
$moderators = $modStmt->fetchAll();

// Fetch Sessions
$sessions = $db->query("SELECT id, session_name AS name, is_current FROM academic_sessions ORDER BY session_name DESC")->fetchAll();
$defaultSessionId = null;
foreach ($sessions as $s) {
    if ($s['is_current']) {
        $defaultSessionId = $s['id'];
        break;
    }
}
if (!$defaultSessionId && !empty($sessions)) {
    $defaultSessionId = $sessions[0]['id'];
}
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Moderator level Assignments</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Map moderators to academic course levels within your department.</p>
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

    <!-- Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Form Column -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Assign Moderator to Level</h3>
                
                <?php if (empty($moderators)): ?>
                    <p class="text-xs text-slate-400">There are no staff users registered with the "Moderator" role in your department.</p>
                <?php else: ?>
                    <form action="<?= url('dashboard/hod/moderators.php') ?>" method="POST" class="space-y-4 text-xs">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="allocate_moderator">
                        
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Moderator</label>
                            <select name="moderator_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">-- Choose Moderator --</option>
                                <?php foreach ($moderators as $m): ?>
                                    <option value="<?= $m['id'] ?>">
                                        <?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Level</label>
                            <select name="level" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="100">100 Level</option>
                                <option value="200">200 Level</option>
                                <option value="300">300 Level</option>
                                <option value="400">400 Level</option>
                                <option value="500">500 Level</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Academic Session</label>
                            <select name="session_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <?php foreach ($sessions as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $defaultSessionId == $s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" 
                                class="w-full py-2.5 px-4 rounded-lg font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm">
                            Allocate Moderator
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- List Column -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Active Level Allocations</h3>
                
                <?php if (empty($allocations)): ?>
                    <p class="text-xs text-slate-400 py-12 text-center">No moderators assigned to levels in this department.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                                    <th class="pb-2">Moderator Name</th>
                                    <th class="pb-2">Assigned Level</th>
                                    <th class="pb-2">Academic Session</th>
                                    <th class="pb-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <?php foreach ($allocations as $alloc): ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($alloc['moderator_name']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($alloc['moderator_email']) ?></span>
                                        </td>
                                        <td class="py-3 font-semibold text-slate-850 dark:text-slate-300">
                                            <span class="px-2 py-0.5 rounded bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300 border border-brand-200 dark:border-brand-850">
                                                <?= $alloc['level'] ?> Level
                                            </span>
                                        </td>
                                        <td class="py-3 text-slate-600 dark:text-slate-400 font-medium"><?= htmlspecialchars($alloc['session_name']) ?></td>
                                        <td class="py-3 text-right">
                                            <form action="<?= url('dashboard/hod/moderators.php') ?>" method="POST" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to revoke this moderator allocation?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="remove_allocation">
                                                <input type="hidden" name="allocation_id" value="<?= $alloc['id'] ?>">
                                                <button type="submit" 
                                                        class="px-2.5 py-1 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg text-[11px] font-bold border border-rose-200 dark:border-rose-900 transition-colors">
                                                    Revoke
                                                </button>
                                            </form>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
