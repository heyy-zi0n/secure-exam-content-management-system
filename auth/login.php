<?php
/**
 * UPDATED: Added CSRF token generation & verification.
 * Login with email or Staff ID, password_verify, session creation.
 */
$pageTitle = 'Staff Login';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in, route to dashboard
if (isLoggedIn()) {
    redirect('dashboard/index.php');
}

$error = '';

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $identifier = sanitizeInput($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter both your Email / Staff ID and password.';
        } else {
            if (loginUser($identifier, $password)) {
                // Regenerate session ID after successful login (session fixation prevention)
                session_regenerate_id(true);
                // Rotate CSRF token for the new session
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                flash('success', 'Welcome back! You have successfully signed in.');
                redirect('dashboard/index.php');
            } else {
                $error = 'Invalid credentials or inactive account. Please try again.';
            }
        }
    }
}

$noAuthRequired = true;
$noSidebar = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col justify-center py-8 sm:px-6 lg:px-8 max-w-md mx-auto w-full">
    <div class="bg-white dark:bg-slate-800 py-8 px-6 shadow-xl rounded-2xl border border-slate-200/80 dark:border-slate-700/80 sm:px-10">

        <!-- Header Section with Logo -->
        <div class="text-center mb-6">
            <img src="<?= url('assets/images/lasu-logo.png') ?>" alt="LASU Logo" class="h-16 w-auto mx-auto mb-3 object-contain">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Staff Portal Login</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Faculty of Computing &amp; Information Technology, LASU
            </p>
        </div>

        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 text-xs font-semibold border border-rose-200 dark:border-rose-800" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="<?= url('auth/login.php') ?>" method="POST" class="space-y-5">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div>
                <label for="identifier" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    Email or Staff ID
                </label>
                <input type="text" id="identifier" name="identifier"
                       value="<?= isset($identifier) ? htmlspecialchars($identifier) : '' ?>" required
                       autocomplete="username"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors"
                       placeholder="e.g. admin@lasu.edu.ng or FCIT/ADM/001">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    Password
                </label>
                <input type="password" id="password" name="password" required
                       autocomplete="current-password"
                       class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors"
                       placeholder="••••••••">
            </div>

            <div>
                <button type="submit" id="btn-login"
                        class="w-full flex justify-center items-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition-colors shadow-sm">
                    Sign In to Portal
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </div>
        </form>

        <p class="mt-6 text-center text-[11px] text-slate-400">
            Authorized personnel only. All access is monitored and logged.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>