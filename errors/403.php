<?php
/**
 * NEW: 403 Forbidden — Role-Based Access Denied Page
 * Displayed when an authenticated user attempts to access
 * a resource outside their assigned role permissions.
 */

// Load required helpers without triggering auth guard
if (!function_exists('url')) {
    require_once dirname(__DIR__) . '/helpers/functions.php';
}
if (!function_exists('currentUser')) {
    require_once dirname(__DIR__) . '/helpers/auth_helper.php';
}

$pageTitle    = '403 — Access Forbidden';
$noSidebar    = true;
$noAuthRequired = true;
$user = function_exists('currentUser') ? currentUser() : null;

// Set HTTP status code (only if headers not yet sent)
if (!headers_sent()) {
    http_response_code(403);
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center px-4">
    <div class="max-w-lg w-full text-center space-y-6">

        <!-- Error Badge -->
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 mx-auto">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>

        <!-- Error Code -->
        <div>
            <p class="text-sm font-bold uppercase tracking-widest text-rose-500 dark:text-rose-400 mb-1">Error 403</p>
            <h1 class="text-4xl font-extrabold text-slate-900 dark:text-white mb-3">Access Forbidden</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed max-w-md mx-auto">
                You are authenticated, but your role does not have permission to access this resource.
                This incident has been logged.
            </p>
        </div>

        <?php if ($user): ?>
        <!-- User Info -->
        <div class="inline-flex items-center gap-3 px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-400">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span>Signed in as <strong class="text-slate-800 dark:text-slate-200"><?= htmlspecialchars($user['full_name'] ?? 'Unknown') ?></strong>
                  with role <strong class="text-rose-600 dark:text-rose-400 uppercase"><?= htmlspecialchars(str_replace('_', ' ', $user['role'] ?? '')) ?></strong>
            </span>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="<?= url('dashboard/index.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold transition-colors shadow-sm">
                ← Go to My Dashboard
            </a>
            <a href="<?= url('auth/logout.php') ?>"
               class="px-5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-rose-300 text-slate-700 dark:text-slate-300 text-sm font-bold transition-colors">
                Sign Out
            </a>
        </div>

        <!-- Footer Note -->
        <p class="text-[11px] text-slate-400">
            LASU FCIT Secure Examination CMS &mdash; All access attempts are monitored and audited.
        </p>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
