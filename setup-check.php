<?php
$pageTitle = "Module 1 - Setup Verification";
$noAuthRequired = true;
$noSidebar = true;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// System Checks
$phpVersionValid = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdoExtensionLoaded = extension_loaded('pdo') && extension_loaded('pdo_mysql');

// Storage Permissions Check
$directories = [
    'Temporary Uploads' => UPLOAD_PATH_TEMP,
    'Encrypted Storage' => UPLOAD_PATH_ENCRYPTED,
    'Archive Storage'   => UPLOAD_PATH_ARCHIVE,
    'System Logs'       => LOG_PATH
];

$dirStatuses = [];
foreach ($directories as $label => $path) {
    if (!file_exists($path)) {
        @mkdir($path, 0777, true);
    }
    $dirStatuses[$label] = [
        'path' => $path,
        'exists' => file_exists($path),
        'writable' => is_writable($path)
    ];
}

// Database Connection Test
$dbConnected = false;
$dbError = null;
try {
    $db = Database::getInstance();
    $dbConnected = ($db instanceof PDO);
} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Module 1 Setup Diagnostics</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Environment validation & system readiness report</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
            System Initialization
        </span>
    </div>

    <!-- Diagnostic Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        <!-- PHP Environment Card -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🖥️</span> PHP Runtime Environment
            </h3>
            <ul class="text-sm space-y-3">
                <li class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700">
                    <span class="text-slate-600 dark:text-slate-400">PHP Version (>= 8.0)</span>
                    <span class="font-bold <?= $phpVersionValid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= PHP_VERSION ?> <?= $phpVersionValid ? '✓' : '✗' ?>
                    </span>
                </li>
                <li class="flex justify-between items-center">
                    <span class="text-slate-600 dark:text-slate-400">PDO MySQL Driver</span>
                    <span class="font-bold <?= $pdoExtensionLoaded ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= $pdoExtensionLoaded ? 'Enabled ✓' : 'Disabled ✗' ?>
                    </span>
                </li>
            </ul>
        </div>

        <!-- Storage Permissions Card -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📁</span> Storage & Permissions
            </h3>
            <ul class="text-sm space-y-2">
                <?php foreach ($dirStatuses as $label => $status): ?>
                    <li class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700">
                        <span class="text-slate-600 dark:text-slate-400"><?= $label ?></span>
                        <span class="font-semibold text-xs px-2 py-0.5 rounded <?= $status['writable'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-800' ?>">
                            <?= $status['writable'] ? 'Writable ✓' : 'Not Writable ✗' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Database Status Card -->
        <div class="bg-white dark:bg-slate-800 p-6 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>🗄️</span> Database Connectivity
            </h3>
            <?php if ($dbConnected): ?>
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm">
                    <strong>Connected Successfully!</strong><br>
                    <span class="text-xs">PDO Singleton initialized & responsive.</span>
                </div>
            <?php else: ?>
                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-sm">
                    <strong>Database Not Connected</strong><br>
                    <span class="text-xs block mt-1">Create the database <code>lasu_fcit_exam_cms</code> in MySQL before proceeding to Module 2.</span>
                    <?php if ($dbError): ?>
                        <div class="mt-2 text-xs font-mono bg-white/50 dark:bg-black/30 p-2 rounded">
                            <?= sanitizeInput($dbError) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- System Configuration Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 font-bold text-slate-900 dark:text-white">
            Configured FCIT Departments
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <?php foreach (FCIT_DEPARTMENTS as $code => $name): ?>
                    <div class="p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-200 dark:border-slate-600 flex items-center justify-between">
                        <span class="font-bold text-brand-600 dark:text-brand-400 text-sm"><?= $code ?></span>
                        <span class="text-xs text-slate-600 dark:text-slate-300"><?= $name ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>