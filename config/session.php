<?php
/**
 * Secure Session Initialization & Lifetime Manager
 */

require_once __DIR__ . '/constants.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);

        $cookieParams = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        session_set_cookie_params($cookieParams);
        session_start();
    }

    // Check session inactivity timeout
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['flash_error'] = "Your session expired due to inactivity. Please log in again.";
        }
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Automatically start secure session on inclusion
startSecureSession();