</main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 py-6 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <p>&copy; <?= date('Y') ?> <?= INSTITUTION_NAME ?>. <?= FACULTY_NAME ?>. All rights reserved.</p>
                <p class="mt-2 sm:mt-0 font-medium"><?= APP_NAME ?> v<?= APP_VERSION ?></p>
            </div>
        </footer>
    </div>

    <script src="<?= url('assets/js/theme-toggle.js') ?>"></script>
</body>
</html>