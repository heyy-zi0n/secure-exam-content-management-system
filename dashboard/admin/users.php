<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle  = 'System Users';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'System Users' => ''];
require_once __DIR__ . '/../../helpers/security_helper.php';
requireRole('admin');
$db = Database::getInstance();
$users = $db->query("SELECT id, staff_id, full_name, email, role, department_code, status, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Users</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">All registered staff accounts across all departments.</p>
        </div>
        <span class="px-3 py-1 text-xs font-bold rounded-full bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-700">
            <?= count($users) ?> accounts
        </span>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700">
                    <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="px-4 py-3">Staff ID</th>
                        <th class="px-4 py-3">Full Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Dept</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last Login</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/20 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs font-bold text-brand-600 dark:text-brand-400"><?= htmlspecialchars($u['staff_id']) ?></td>
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($u['full_name']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full uppercase
                                <?= match($u['role']) {
                                    'admin'        => 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
                                    'hod'          => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                    'exam_officer' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                    'lecturer'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                    'moderator'    => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    default        => 'bg-slate-100 text-slate-600'
                                } ?>">
                                <?= str_replace('_', ' ', $u['role']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs font-bold text-slate-500"><?= htmlspecialchars($u['department_code'] ?? '—') ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded-full uppercase
                                <?= $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                                <?= $u['status'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= $u['last_login'] ? date('d M, H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
