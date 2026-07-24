<?php
/**
 * NEW: 404 Not Found
 */
if (!function_exists('url')) {
    require_once dirname(__DIR__) . '/helpers/functions.php';
}

$pageTitle    = '404 — Page Not Found';
$noSidebar    = true;
$noAuthRequired = true;

if (!headers_sent()) {
    http_response_code(404);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="max-w-lg w-full text-center space-y-6">

        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-slate-400 mb-1">Error 404</p>
            <h1 class="text-4xl font-extrabold text-slate-900 dark:text-white mb-3">Page Not Found</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed max-w-md mx-auto">
                The page you are looking for does not exist or may have been moved.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?= url('dashboard/index.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold transition-colors shadow-sm">
                ← Back to Dashboard
            </a>
            <a href="<?= url('index.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold transition-colors">
                Go to Home
            </a>
        </div>

        <p class="text-[11px] text-slate-400">LASU FCIT Secure Examination CMS</p>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
