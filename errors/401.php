<?php
/**
 * NEW: 401 Unauthorized — Session Expired / Not Authenticated
 */
if (!function_exists('url')) {
    require_once dirname(__DIR__) . '/helpers/functions.php';
}

$pageTitle    = '401 — Unauthorized';
$noSidebar    = true;
$noAuthRequired = true;

if (!headers_sent()) {
    http_response_code(401);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="max-w-lg w-full text-center space-y-6">

        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 mx-auto">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-amber-500 dark:text-amber-400 mb-1">Error 401</p>
            <h1 class="text-4xl font-extrabold text-slate-900 dark:text-white mb-3">Not Authenticated</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed max-w-md mx-auto">
                Your session has expired or you are not signed in. Please log in to continue.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?= url('auth/login.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold transition-colors shadow-sm">
                Sign In to Portal
            </a>
            <a href="<?= url('index.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold transition-colors">
                Back to Home
            </a>
        </div>

        <p class="text-[11px] text-slate-400">LASU FCIT Secure Examination CMS</p>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
