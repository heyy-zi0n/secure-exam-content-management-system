<?php
/**
 * UPDATED: Admin Dashboard — Live stat cards with real DB counts.
 * All business logic is placeholder only per module spec.
 */
$pageTitle  = 'Admin Control Center';
$breadcrumbs = ['Admin Dashboard' => ''];

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../helpers/security_helper.php';

requireRole('admin');

$db   = Database::getInstance();
$user = currentUser();

// Live DB counts
$totalUsers    = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDepts    = (int) $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalPapers   = 0; // TODO: migrate examination_papers
$activeSession = (int) $db->query("SELECT COUNT(*) FROM academic_sessions WHERE is_current = 1")->fetchColumn();
$securityEvents= 0; // TODO: migrate security_events
$auditToday    = 0; // TODO: migrate audit_logs

// Recent audit entries
$recentAudit = []; // TODO: migrate audit_logs

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-brand-600 to-brand-800 p-6 rounded-2xl text-white shadow-lg shadow-brand-500/20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold mb-1">System Administrator</h1>
                <p class="text-brand-100 text-sm">
                    Welcome back, <strong><?= htmlspecialchars($user['full_name'] ?? 'Admin') ?></strong>.
                    You have full system access.
                </p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold bg-white/20 backdrop-blur px-3 py-1.5 rounded-lg">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                System Operational
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <?php
        $stats = [
            ['label' => 'Total Users',      'value' => $totalUsers,     'color' => 'text-brand-600 dark:text-brand-400'],
            ['label' => 'Departments',       'value' => $totalDepts,     'color' => 'text-purple-600 dark:text-purple-400'],
            ['label' => 'Exam Papers',       'value' => $totalPapers,    'color' => 'text-emerald-600 dark:text-emerald-400'],
            ['label' => 'Active Sessions',   'value' => $activeSession,  'color' => 'text-amber-600 dark:text-amber-400'],
            ['label' => "Security Events",   'value' => $securityEvents, 'color' => 'text-rose-600 dark:text-rose-400'],
            ['label' => "Audits Today",      'value' => $auditToday,     'color' => 'text-slate-700 dark:text-slate-300'],
        ];
        foreach ($stats as $s): ?>
            <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block"><?= $s['label'] ?></span>
                <span class="text-3xl font-black <?= $s['color'] ?> block mt-1"><?= $s['value'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Quick Access Modules -->
        <div class="lg:col-span-4 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Quick Access</h2>
            <?php
            $modules = [
                ['title' => 'Academic Sessions', 'desc' => 'Manage academic sessions and semesters.',    'icon' => '📅', 'url' => url('dashboard/admin/sessions.php'),    'color' => 'bg-indigo-50 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-800'],
                ['title' => 'System Users',    'desc' => 'Manage accounts, roles, and status.',         'icon' => '👤', 'url' => url('dashboard/admin/users.php'),    'color' => 'bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800'],
                ['title' => 'Departments',     'desc' => 'Configure FCIT department structure.',         'icon' => '🏢', 'url' => url('dashboard/admin/departments.php'), 'color' => 'bg-purple-50 dark:bg-purple-950/20 border-purple-200 dark:border-purple-800'],
                ['title' => 'Security Logs',   'desc' => 'Monitor security incidents and events.',       'icon' => '🔒', 'url' => url('dashboard/admin/logs.php'),    'color' => 'bg-rose-50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800'],
                ['title' => 'System Settings', 'desc' => 'Global configuration and parameters.',        'icon' => '⚙️', 'url' => url('dashboard/admin/settings.php'), 'color' => 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'],
                ['title' => 'Reports',         'desc' => 'Analytics and activity summaries.',           'icon' => '📊', 'url' => url('dashboard/admin/reports.php'),  'color' => 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800'],
                ['title' => 'Audit Logs',      'desc' => 'Full system audit trail and event history.',  'icon' => '📋', 'url' => url('dashboard/admin/audit.php'),    'color' => 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800'],
            ];
            foreach ($modules as $m): ?>
                <a href="<?= $m['url'] ?>"
                   class="flex items-center gap-4 p-4 rounded-xl border <?= $m['color'] ?> hover:shadow-md transition-all group">
                    <span class="text-2xl"><?= $m['icon'] ?></span>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                            <?= $m['title'] ?>
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400"><?= $m['desc'] ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Recent Audit Log -->
        <div class="lg:col-span-8">
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm h-full">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Recent System Activity</h2>
                    <a href="<?= url('dashboard/admin/audit.php') ?>"
                       class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">View All →</a>
                </div>

                <?php if (empty($recentAudit)): ?>
                    <div class="text-center py-10 text-slate-400">
                        <span class="text-3xl block mb-2">📋</span>
                        <p class="text-sm">No activity recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentAudit as $log): ?>
                            <div class="flex items-start gap-3 py-2.5 border-b border-slate-100 dark:border-slate-700/60 last:border-0">
                                <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs shrink-0">
                                    🔹
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </p>
                                    <p class="text-[10px] text-slate-500 truncate">
                                        <?= htmlspecialchars($log['description'] ?? '') ?>
                                    </p>
                                    <span class="text-[9px] text-slate-400">
                                        <?= htmlspecialchars($log['full_name'] ?? 'System') ?>
                                        &middot; <?= date('d M, H:i', strtotime($log['created_at'])) ?>
                                    </span>
                                </div>
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 uppercase shrink-0">
                                    <?= htmlspecialchars($log['role'] ?? 'sys') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>