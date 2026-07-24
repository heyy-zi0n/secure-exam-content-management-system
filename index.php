<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load Auth Helper functions safely
require_once __DIR__ . '/helpers/auth_helper.php';

// Auto-redirect logged-in users ONLY if they have a valid dashboard path
if (isLoggedIn()) {
    $userRole = currentUser()['role'] ?? '';
    $dashboardPath = getRoleDashboardPath($userRole);
    
    // Only redirect if a real dashboard path exists and it isn't pointing to login
    if (!empty($dashboardPath) && $dashboardPath !== 'auth/login.php') {
        header("Location: " . url($dashboardPath));
        exit;
    }
}

$pageTitle = "Welcome - FCIT Exam Portal";

// Fallback constant check for FCIT departments to prevent crash if config is not pre-loaded
if (!defined('FCIT_DEPARTMENTS')) {
    define('FCIT_DEPARTMENTS', [
        'CSC' => 'Computer Science',
        'INF' => 'Information Technology',
        'INS' => 'Information Systems',
        'CYB' => 'Cybersecurity',
        'SWE' => 'Software Engineering'
    ]);
}

$noAuthRequired = true;
$noSidebar = true;
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden py-12 lg:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-12 items-center">
            
            <!-- Left Column: Copy & Call-to-Actions -->
            <div class="sm:text-center lg:text-left lg:col-span-7 space-y-6">
                <div>
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300 border border-brand-200 dark:border-brand-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Official FCIT Examination Portal
                    </span>
                </div>
                
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    Secure Examination <br class="hidden sm:inline">Content Management System
                </h1>
                
                <p class="text-base sm:text-lg text-slate-600 dark:text-slate-300 max-w-2xl leading-relaxed">
                    Integrated peer-review pipeline, trademarked <strong class="text-slate-900 dark:text-white font-semibold">Blind Lockdown™ Security Protocol</strong>, and automated document moderation for the <strong class="text-slate-900 dark:text-white font-semibold">Faculty of Computing & Information Technology</strong>, Lagos State University.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-start gap-4 pt-2">
                    <a href="<?= url('auth/login.php') ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow-lg shadow-brand-500/25 transition-all text-center flex items-center justify-center gap-2">
                        <span>Access Staff Portal</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <a href="<?= url('setup-check.php') ?>" class="w-full sm:w-auto px-6 py-3.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-sm border border-slate-200 dark:border-slate-700 transition-all text-center">
                        System Setup Status
                    </a>
                </div>

                <!-- Fast Stats / Indicators -->
                <div class="pt-6 border-t border-slate-200 dark:border-slate-800 grid grid-cols-3 gap-4 text-left">
                    <div>
                        <span class="block text-2xl font-extrabold text-slate-900 dark:text-white"><?= count(FCIT_DEPARTMENTS) ?></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Departments</span>
                    </div>
                    <div>
                        <span class="block text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">Blind Lockdown™</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Powered by AES-256</span>
                    </div>
                    <div>
                        <span class="block text-2xl font-extrabold text-slate-900 dark:text-white">100%</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Audited Pipeline</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Visual System Representation -->
            <div class="mt-12 lg:mt-0 lg:col-span-5">
                <div class="relative mx-auto max-w-md lg:max-w-none">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-2xl p-6 space-y-5">
                        
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                                <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            </div>
                            <span class="text-xs font-mono text-slate-400">LASU FCIT Node v1.0</span>
                        </div>

                        <!-- Mini Workflow Timeline Preview -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Security Workflow Lifecycle</h4>
                            
                            <div class="space-y-3">
                                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm">📝</span>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">1. Lecturer Submission</p>
                                            <p class="text-[10px] text-slate-500">Version controlled & hash-renamed</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Complete</span>
                                </div>

                                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm">🔍</span>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">2. Moderator Peer Review</p>
                                            <p class="text-[10px] text-slate-500">Level-assigned peer vetting</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Approved</span>
                                </div>

                                <div class="p-3 rounded-lg bg-brand-50/50 dark:bg-brand-900/20 border border-brand-200 dark:border-brand-800 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm">🔒</span>
                                        <div>
                                            <p class="text-xs font-bold text-brand-900 dark:text-brand-200">3. Blind Lockdown™ Protocol</p>
                                            <p class="text-[10px] text-brand-600 dark:text-brand-400">Powered by AES-256 Encryption</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-brand-600 text-white">Active</span>
                                </div>

                                <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm">🖨️</span>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">4. Time-Restricted Printing</p>
                                            <p class="text-[10px] text-slate-500">Allowed &le; 24h prior to exam</p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-300">Queued</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Departmental Infrastructure Grid -->
<div class="py-12 border-t border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Faculty Departments</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Independent examination pipelines across all FCIT departments</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <?php foreach (FCIT_DEPARTMENTS as $code => $name): ?>
                <div class="p-5 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:border-brand-500 transition-all group">
                    <div class="w-9 h-9 rounded-lg bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 font-extrabold text-xs flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                        <?= htmlspecialchars($code) ?>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white leading-snug"><?= htmlspecialchars($name) ?></h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">Dedicated HOD & Exam Officer workflows</p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>