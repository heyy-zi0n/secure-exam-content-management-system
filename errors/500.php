<?php
/**
 * NEW: 500 Internal Server Error
 */
if (!function_exists('url')) {
    // Safe fallback if helpers aren't loadable (critical crash scenario)
    function url(string $path = ''): string {
        return 'http://localhost/lasu_exam_cms/' . ltrim($path, '/');
    }
}

$pageTitle    = '500 — Server Error';
$noSidebar    = true;
$noAuthRequired = true;

if (!headers_sent()) {
    http_response_code(500);
}

// Try to load header, but gracefully handle if it also fails
try {
    require_once dirname(__DIR__) . '/includes/header.php';
} catch (Throwable $e) {
    // Minimal inline fallback if the layout itself is broken
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>500 Server Error</title>
    <script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-900 text-white flex items-center justify-center min-h-screen">';
}
?>

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="max-w-lg w-full text-center space-y-6">

        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-rose-900/30 text-rose-400 mx-auto">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>

        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-rose-400 mb-1">Error 500</p>
            <h1 class="text-4xl font-extrabold text-slate-900 dark:text-white mb-3">Internal Server Error</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed max-w-md mx-auto">
                An unexpected error has occurred on the server. The system administrator has been notified.
                Please try again in a few moments.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="javascript:history.back()"
               class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold transition-colors shadow-sm">
                ← Go Back
            </a>
            <a href="<?= url('index.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold transition-colors">
                Back to Home
            </a>
        </div>

        <p class="text-[11px] text-slate-400">LASU FCIT Secure Examination CMS &mdash; Error Reference: <?= date('Y-m-d H:i:s') ?></p>
    </div>
</div>

<?php
try {
    require_once dirname(__DIR__) . '/includes/footer.php';
} catch (Throwable $e) {
    echo '</body></html>';
}
?>
