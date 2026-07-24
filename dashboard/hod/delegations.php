<?php
$pageTitle = "Role Delegations";
$breadcrumbs = ['HOD Workspace' => 'dashboard/hod/index.php', 'Delegations' => ''];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../helpers/workflow_helper.php';

requireRole('hod');

$db = Database::getInstance();
$user = currentUser();
$dept = $user['department_code'];
$error = '';
$success = '';

// Handle New Delegation Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_delegation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $delegateId = (int)($_POST['delegate_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        
        if (!$delegateId || empty($startDate) || empty($endDate)) {
            $error = "All fields are required.";
        } elseif (strtotime($startDate) > strtotime($endDate)) {
            $error = "Start date cannot be after the end date.";
        } else {
            // Revoke any existing active delegations first
            $db->exec("UPDATE hod_delegations SET status = 'Revoked' WHERE department_code = '$dept' AND status = 'Active'");
            
            // Insert delegation
            $ins = $db->prepare("
                INSERT INTO hod_delegations (department_code, delegated_by, acting_officer_id, start_date, end_date, status)
                VALUES (:dept, :hod_id, :delegate_id, :start, :end, 'Active')
            ");
            $ins->execute([
                ':dept'      => $dept,
                ':hod_id'    => $user['id'],
                ':delegate_id' => $delegateId,
                ':start'     => $startDate,
                ':end'       => $endDate
            ]);
            
            $delegateEmail = $db->query("SELECT email FROM users WHERE id = $delegateId")->fetchColumn();
            logAudit('HOD Delegation Created', "Delegated HOD role to $delegateEmail from $startDate to $endDate");
            $success = "HOD role successfully delegated to staff member.";
        }
    }
}

// Handle Revoke Delegation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_delegation') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "CSRF verification failed.";
    } else {
        $delId = (int)($_POST['delegation_id'] ?? 0);
        
        $chk = $db->prepare("SELECT * FROM hod_delegations WHERE id = :id AND department_code = :dept");
        $chk->execute([':id' => $delId, ':dept' => $dept]);
        $info = $chk->fetch();
        
        if ($info) {
            $up = $db->prepare("UPDATE hod_delegations SET status = 'Revoked' WHERE id = :id");
            $up->execute([':id' => $delId]);
            
            $delegateEmail = $db->query("SELECT email FROM users WHERE id = {$info['acting_officer_id']}")->fetchColumn();
            logAudit('HOD Delegation Revoked', "Revoked delegation for $delegateEmail");
            $success = "Delegation revoked successfully.";
        } else {
            $error = "Delegation record not found.";
        }
    }
}

// Fetch Department Staff to delegate to (Lecturers and Moderators in the same department)
$staffStmt = $db->prepare("
    SELECT id, full_name, email, role 
    FROM users 
    WHERE department_code = :dept AND role IN ('lecturer', 'moderator') AND id != :id
    ORDER BY full_name ASC
");
$staffStmt->execute([':dept' => $dept, ':id' => $user['id']]);
$eligibleStaff = $staffStmt->fetchAll();

// Fetch Delegations History
$delStmt = $db->prepare("
    SELECT hd.*, u.full_name as delegate_name, u.email as delegate_email, h.full_name as hod_name
    FROM hod_delegations hd
    JOIN users u ON hd.acting_officer_id = u.id
    JOIN users h ON hd.delegated_by = h.id
    WHERE hd.department_code = :dept
    ORDER BY hd.created_at DESC
");
$delStmt->execute([':dept' => $dept]);
$delegations = $delStmt->fetchAll();
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Role Delegation Center</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Temporarily delegate HOD responsibilities to departmental colleagues.</p>
    </div>

    <!-- Alerts -->
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left: Form -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Delegate HOD Role</h3>
                
                <?php if (empty($eligibleStaff)): ?>
                    <p class="text-xs text-slate-400">No other academic staff members found in your department to delegate to.</p>
                <?php else: ?>
                    <form action="<?= url('dashboard/hod/delegations.php') ?>" method="POST" class="space-y-4 text-xs">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_delegation">
                        
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Select Delegate Staff</label>
                            <select name="delegate_id" required
                                    class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">-- Choose Staff Member --</option>
                                <?php foreach ($eligibleStaff as $s): ?>
                                    <option value="<?= $s['id'] ?>">
                                        <?= htmlspecialchars($s['full_name']) ?> (<?= strtoupper($s['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">Start Date</label>
                                <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-350 uppercase mb-1">End Date</label>
                                <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                        </div>

                        <button type="submit" 
                                class="w-full py-2.5 px-4 rounded-lg font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm">
                            Authorize Delegation
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Table -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Delegation Audit History</h3>
                
                <?php if (empty($delegations)): ?>
                    <p class="text-xs text-slate-400 py-12 text-center">No delegation records found.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-700/60 text-slate-400 font-bold uppercase pb-2">
                                    <th class="pb-2">Delegate User</th>
                                    <th class="pb-2">Timeframe</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <?php foreach ($delegations as $del): 
                                    $statusBadge = 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300';
                                    if ($del['status'] === 'Active') {
                                        $statusBadge = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400';
                                    } elseif ($del['status'] === 'Revoked') {
                                        $statusBadge = 'bg-rose-100 text-rose-800 dark:bg-rose-950/30 dark:text-rose-450';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-750 transition-colors">
                                        <td class="py-3 font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars($del['delegate_name']) ?>
                                            <span class="block font-normal text-[10px] text-slate-500"><?= htmlspecialchars($del['delegate_email']) ?></span>
                                        </td>
                                        <td class="py-3 text-slate-700 dark:text-slate-350">
                                            <?= date('d M Y', strtotime($del['start_date'])) ?> to <?= date('d M Y', strtotime($del['end_date'])) ?>
                                        </td>
                                        <td class="py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold <?= $statusBadge ?>">
                                                <?= $del['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <?php if ($del['status'] === 'Active'): ?>
                                                <form action="<?= url('dashboard/hod/delegations.php') ?>" method="POST" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to revoke this delegation immediately?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="revoke_delegation">
                                                    <input type="hidden" name="delegation_id" value="<?= $del['id'] ?>">
                                                    <button type="submit" 
                                                            class="px-2.5 py-1 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg text-[11px] font-bold border border-rose-200 dark:border-rose-900 transition-colors">
                                                        Revoke
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-400 font-bold uppercase">Archived</span>
                                            <?php endif; ?>
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
