<?php
// Ensure base functions (like url()) are available
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Get logged in user details
 */
if (!function_exists('currentUser')) {
    function currentUser(): ?array {
        if (!isLoggedIn()) return null;
        return [
            'id'              => $_SESSION['user_id'] ?? null,
            'staff_id'        => $_SESSION['staff_id'] ?? '',
            'full_name'       => $_SESSION['full_name'] ?? 'User',
            'email'           => $_SESSION['email'] ?? '',
            'role'            => $_SESSION['role'] ?? '',
            'department_code' => $_SESSION['department_code'] ?? 'CSC'
        ];
    }
}

/**
 * Enforce login authentication
 */
if (!function_exists('requireAuth')) {
    function requireAuth(): void {
        if (!isLoggedIn()) {
            $_SESSION['flash_error'] = "Please log in to access the dashboard.";
            header("Location: " . url('auth/login.php'));
            exit;
        }
    }
}

/**
 * Check if user has specific role
 */
if (!function_exists('hasRole')) {
    function hasRole($allowedRoles): bool {
        if (!isLoggedIn()) return false;
        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        return in_array($_SESSION['role'] ?? '', $roles, true);
    }
}

/**
 * Enforce strict role access
 */
if (!function_exists('requireRole')) {
    function requireRole($allowedRoles): void {
        requireAuth();
        if (!hasRole($allowedRoles)) {
            http_response_code(403);
            include dirname(__DIR__) . '/errors/403.php';
            exit;
        }
    }
}

/**
 * Map role to dashboard folder
 */
if (!function_exists('getRoleDashboardPath')) {
    function getRoleDashboardPath(string $role): string {
        $routes = [
            'admin'        => 'dashboard/admin/index.php',
            'hod'          => 'dashboard/hod/index.php',
            'exam_officer' => 'dashboard/exam_officer/index.php',
            'lecturer'     => 'dashboard/lecturer/index.php',
            'moderator'    => 'dashboard/moderator/index.php',
        ];

        return $routes[$role] ?? 'auth/login.php';
    }
}