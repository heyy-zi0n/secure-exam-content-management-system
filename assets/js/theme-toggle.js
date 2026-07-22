/**
 * LASU FCIT CMS - Theme Manager (Light / Dark Mode)
 */
(function () {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

    // Function to set theme state
    function applyTheme(isDark) {
        if (isDark) {
            document.documentElement.classList.add('dark');
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
            if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
        } else {
            document.documentElement.classList.remove('dark');
            if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
            if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
        }
    }

    // Determine initial state
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDarkMode = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);

    applyTheme(isDarkMode);

    // Toggle event listener
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function () {
            const currentlyDark = document.documentElement.classList.contains('dark');
            const newDarkState = !currentlyDark;
            localStorage.setItem('theme', newDarkState ? 'dark' : 'light');
            applyTheme(newDarkState);
        });
    }
})();