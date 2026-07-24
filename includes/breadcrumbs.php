<?php if (empty($noSidebar)): ?>
<nav class="flex mb-6 text-xs text-slate-500 dark:text-slate-400 font-medium" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2">
        <li class="inline-flex items-center">
            <a href="<?= url('dashboard/index.php') ?>" class="hover:text-brand-600 dark:hover:text-brand-400 inline-flex items-center">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Home
            </a>
        </li>
        <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
            <?php foreach ($breadcrumbs as $label => $link): ?>
                <li>
                    <div class="flex items-center">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                        <?php if ($link): ?>
                            <a href="<?= $link ?>" class="ml-1 md:ml-2 hover:text-brand-600 dark:hover:text-brand-400"><?= htmlspecialchars($label) ?></a>
                        <?php else: ?>
                            <span class="ml-1 md:ml-2 text-slate-800 dark:text-slate-200 font-semibold"><?= htmlspecialchars($label) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ol>
</nav>
<?php endif; ?>