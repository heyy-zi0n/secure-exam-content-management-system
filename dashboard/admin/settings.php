<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'System Settings';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'System Settings' => ''];
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">System Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">This module is under active development.</p>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-12 text-center">
        <span class="text-5xl block mb-4">🚧</span>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Coming Soon</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">The <strong>System Settings</strong> module is being built. Check back after the next deployment sprint.</p>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
