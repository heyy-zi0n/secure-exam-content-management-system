<?php
/**
 * Reusable "Coming Soon" Placeholder Template
 * For unfinished modules in LASU FCIT Exam CMS
 */

// Default values
$pageTitle = $pageTitle ?? 'Coming Soon';
$moduleName = $moduleName ?? 'This Module';
$plannedVersion = $plannedVersion ?? 'v1.0';
$moduleDescription = $moduleDescription ?? 'This feature is currently under development and will be available in a future release.';
$progressPercent = $progressPercent ?? 0;
$backUrl = $backUrl ?? url('dashboard/index.php');
$backLabel = $backLabel ?? '← Back to Dashboard';
?>

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-8 md:p-12 text-center">
        <!-- Icon -->
        <div class="w-20 h-20 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-6">
            <span class="text-5xl">🚧</span>
        </div>

        <!-- Title -->
        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-2">
            Module Under Development
        </h2>
        <h1 class="text-3xl font-black text-brand-600 dark:text-brand-400 mb-4">
            <?= htmlspecialchars($moduleName) ?>
        </h1>

        <!-- Description -->
        <p class="text-sm text-slate-600 dark:text-slate-400 max-w-md mx-auto mb-8">
            <?= htmlspecialchars($moduleDescription) ?>
        </p>

        <!-- Roadmap Info -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto mb-8">
            <!-- Current Status -->
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Current Status</div>
                <div class="text-sm font-bold text-amber-600 dark:text-amber-400 flex items-center justify-center gap-2">
                    <span>🚧</span> Under Development
                </div>
            </div>

            <!-- Estimated Version -->
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Estimated Version</div>
                <div class="text-sm font-black text-brand-600 dark:text-brand-400">
                    <?= htmlspecialchars($plannedVersion) ?>
                </div>
            </div>

            <!-- Progress -->
            <div class="bg-slate-50 dark:bg-slate-900/50 rounded-xl p-4 border border-slate-200 dark:border-slate-700">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Progress</div>
                <div class="text-sm font-black text-slate-700 dark:text-slate-300">
                    <?= htmlspecialchars($progressPercent) ?>%
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="max-w-md mx-auto mb-8">
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                <div class="bg-gradient-to-r from-brand-500 to-brand-600 h-2.5 rounded-full transition-all duration-500" style="width: <?= htmlspecialchars($progressPercent) ?>%;"></div>
            </div>
        </div>

        <!-- Back Button -->
        <a href="<?= htmlspecialchars($backUrl) ?>"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-lg text-sm font-bold text-white bg-brand-600 hover:bg-brand-700 transition-colors shadow-sm">
            <span><?= htmlspecialchars($backLabel) ?></span>
        </a>
    </div>
</div>
