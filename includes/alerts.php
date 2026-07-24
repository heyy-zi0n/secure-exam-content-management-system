<?php
/**
 * UPDATED: Reads the unified flash_message key written by flash() helper.
 * Also handles legacy flash_success/flash_error keys for compatibility.
 */

// Read unified flash message from flash() helper
$flashData = flash();

if ($flashData && !empty($flashData['type']) && !empty($flashData['message'])):
    $type    = $flashData['type'];
    $message = $flashData['message'];

    $styles = [
        'success' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300',
        'danger'  => 'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300',
        'warning' => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300',
        'info'    => 'bg-sky-50 dark:bg-sky-950/50 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-300',
    ];
    $icons = [
        'success' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />',
        'danger'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />',
        'info'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
    ];
    $style = $styles[$type] ?? $styles['info'];
    $icon  = $icons[$type]  ?? $icons['info'];
?>
    <div class="mb-6 p-4 rounded-xl border <?= $style ?> flex items-center justify-between" role="alert">
        <div class="flex items-center space-x-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $icon ?></svg>
            <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
        </div>
    </div>
<?php endif; ?>