<?php
$user = currentUser();
$isLoggedIn = !empty($user);
$roleDisplay = $isLoggedIn ? strtoupper(str_replace('_', ' ', $user['role'] ?? 'USER')) : 'GUEST';

// Role badge colors
$roleBadgeClasses = [
    'admin'        => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    'hod'          => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
    'exam_officer' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    'lecturer'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
    'moderator'    => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
];
$badgeClass = ($isLoggedIn && isset($user['role'])) ? ($roleBadgeClasses[$user['role']] ?? 'bg-slate-100 text-slate-800') : 'bg-slate-100 text-slate-800';
?>

<header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 sticky top-0 z-30">
    <div class="w-full px-4 sm:px-6 h-16 flex items-center justify-between">
        
        <!-- Brand / Mobile Logo -->
        <a href="<?= $isLoggedIn ? url('dashboard/index.php') : url('index.php') ?>" class="flex items-center space-x-3 group">
            <img src="<?= url('assets/images/lasu-logo.png') ?>" alt="LASU Logo" class="h-9 w-auto object-contain">
            <div class="hidden sm:block">
                <span class="text-base font-bold text-slate-900 dark:text-white block leading-tight">
                    <?= APP_SHORT_NAME ?>
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                    <?= FACULTY_NAME ?>
                </span>
            </div>
        </a>

        <!-- User Actions & Theme Toggle -->
        <div class="flex items-center space-x-3">
            <!-- Role Badge -->
            <span class="px-2.5 py-1 text-xs font-extrabold rounded-full border <?= $badgeClass ?>">
                <?= $roleDisplay ?>
            </span>

            <?php if ($isLoggedIn): ?>
                <!-- User Info -->
                <div class="hidden md:flex flex-col text-right">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                        <?= sanitizeInput($user['full_name']) ?>
                    </span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                        <?= sanitizeInput($user['staff_id']) ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Theme Switcher -->
            <button id="theme-toggle" type="button" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg text-sm">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                </svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.707.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 100 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path>
                </svg>
            </button>

            <?php if ($isLoggedIn): ?>
                <!-- Logout Button -->
                <a href="<?= url('auth/logout.php') ?>" class="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-955/40 rounded-lg text-sm transition-colors" title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </a>
            <?php else: ?>
                <!-- Login Button -->
                <a href="<?= url('auth/login.php') ?>" class="px-3 py-1.5 text-xs font-bold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm">
                    Portal Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>