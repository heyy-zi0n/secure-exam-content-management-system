</main>
    </div>

        <!-- Footer (full width, outside sidebar+content flex row) -->
        <footer class="bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 py-4 mt-auto shrink-0">
            <div class="w-full px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <p>&copy; <?= date('Y') ?> <?= INSTITUTION_NAME ?>. <?= FACULTY_NAME ?>. All rights reserved.</p>
                <p class="mt-1 sm:mt-0 font-medium"><?= APP_NAME ?> v<?= APP_VERSION ?></p>
            </div>
        </footer>
    </div>

    <script src="<?= url('assets/js/theme-toggle.js') ?>"></script>
</body>
</html>