<?php
/**
 * FCIT Secure Examination CMS - Global Helper Functions
 * Faculty of Computing and Information Technology, Lagos State University
 */

// Load global constants if not already loaded
require_once __DIR__ . '/../config/constants.php';

/**
 * Helper: Generate absolute URL for routing
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }
}

/**
 * Helper: Redirect to a relative application path
 */
if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header('Location: ' . url($path));
        exit;
    }
}

/**
 * Helper: Sanitize user input text
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(?string $data): string {
        return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Alias for sanitize() compatibility
if (!function_exists('sanitize')) {
    function sanitize(?string $data): string {
        return sanitizeInput($data);
    }
}

/**
 * Helper: Set or retrieve flash alert messages
 */
if (!function_exists('flash')) {
    function flash(string $type = '', string $message = ''): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($type) && !empty($message)) {
            $_SESSION['flash_message'] = [
                'type' => $type, // 'danger', 'success', 'warning', 'info'
                'message' => $message
            ];
            return null;
        }

        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }

        return null;
    }
}