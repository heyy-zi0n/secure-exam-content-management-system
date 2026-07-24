<?php
/**
 * FCIT Secure Examination CMS - Authentication & Session Management
 * Faculty of Computing and Information Technology, Lagos State University
 */
// includes/auth.php
require_once __DIR__ . '/../helpers/auth_helper.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Helper: Safely initialize session if not already started
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if the user session has timed out due to inactivity
 */
function isSessionExpired(): bool {
    initSession();

    if (isset($_SESSION['user_id'])) {
        // If last_activity wasn't set yet, set it now instead of expiring immediately
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return false;
        }

        $inactiveSeconds = time() - $_SESSION['last_activity'];
        $timeoutLimit = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 1800; // 30 minutes default

        if ($inactiveSeconds > $timeoutLimit) {
            return true;
        }
    }

    return false;
}

/**
 * Authenticate user by Email or Staff ID
 */
function loginUser(string $identifier, string $password): bool {
    try {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT u.id, u.staff_id, u.email, u.password_hash, u.account_status,
                   CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name,
                   r.role_code AS role, d.code AS department_code
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.email = :email OR u.staff_id = :staff_id 
            LIMIT 1
        ");

        $stmt->execute([
            ':email'    => $identifier,
            ':staff_id' => $identifier
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check account status
            if (isset($user['account_status']) && strtolower($user['account_status']) !== 'active') {
                flash('danger', 'Your account status is: ' . htmlspecialchars($user['account_status']) . '. Please contact support.');
                return false;
            }

            initSession();

            // Prevent session fixation attacks
            session_regenerate_id(true);

            // Update last login timestamp in DB
            $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);

            // Save user session details
            $_SESSION['user_id']         = $user['id'];
            $_SESSION['staff_id']        = $user['staff_id'];
            $_SESSION['role']            = $user['role'];
            $_SESSION['full_name']       = $user['full_name'];
            $_SESSION['email']           = $user['email'];
            $_SESSION['department_code'] = $user['department_code'];
            $_SESSION['last_activity']   = time(); // Initialize activity timestamp

            return true;
        }

        return false;
    } catch (PDOException $e) {
        if (defined('LOG_PATH')) {
            error_log("Login Error: " . $e->getMessage() . PHP_EOL, 3, LOG_PATH . '/app.log');
        }
        return false;
    }
}

/**
 * Check if user is logged in & enforce inactivity session timeout
 */
function isLoggedIn(): bool {
    initSession();

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Enforce 30-minute inactivity timeout safely without redirect loops
    if (isSessionExpired()) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();

        // Start a fresh session to deliver the expired flash message
        session_start();
        flash('warning', 'Your session has expired due to inactivity. Please sign in again.');

        return false;
    }

    // Refresh last activity timestamp on every active request
    $_SESSION['last_activity'] = time();

    return true;
}

/**
 * Fetch current authenticated user
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT u.id, u.staff_id, u.email, u.account_status AS status,
                   CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS full_name,
                   r.role_code AS role, d.code AS department_code
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Alias for getCurrentUser()
 */
function currentUser(): ?array {
    return getCurrentUser();
}

/**
 * Enforce authentication on protected pages
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect('auth/login.php');
    }
}

/**
 * Alias for requireLogin()
 */
function requireAuth(): void {
    requireLogin();
}

/**
 * Enforce role authorization
 */
function requireRole(array|string $roles): void {
    requireLogin();
    
    $allowedRoles = (array) $roles;
    $currentUser = getCurrentUser();

    if (!$currentUser || !in_array($currentUser['role'], $allowedRoles, true)) {
        // Redirect to professional 403 page instead of silent dashboard bounce
        http_response_code(403);
        include __DIR__ . '/../errors/403.php';
        exit;
    }
}

/**
 * Logout session with optional flash message
 */
function logoutUser(?string $message = null): void {
    initSession();
    $_SESSION = [];

    // Clear session cookies cleanly
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }

    session_destroy();

    if ($message) {
        session_start();
        flash('warning', $message);
    }

    redirect('auth/login.php');
}