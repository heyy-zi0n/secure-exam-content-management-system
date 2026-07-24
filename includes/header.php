<?php
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($noAuthRequired) || !$noAuthRequired) {
    requireAuth(); // Require login across protected dashboard views
}
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitizeInput($pageTitle) ?> | <?= APP_SHORT_NAME ?></title>
    <link rel="icon" href="<?= url('assets/images/lasu-logo.png') ?>" type="image/png">
    
    <!-- Manrope Font & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= url('assets/css/main.css') ?>">

    <!-- Anti-FOUC script for Theme Switching -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 theme-transition antialiased font-sans">
    <div class="min-h-full flex flex-col">
        <!-- Dynamic Top Navigation -->
        <?php include __DIR__ . '/topbar.php'; ?>

        <!-- Layout Body: Sidebar + Main Content -->
        <div class="flex-1 flex w-full overflow-hidden">
            <?php include __DIR__ . '/sidebar.php'; ?>

            <!-- Main Content Area -->
            <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 overflow-y-auto">
                <?php include __DIR__ . '/breadcrumbs.php'; ?>
                <?php include __DIR__ . '/alerts.php'; ?>