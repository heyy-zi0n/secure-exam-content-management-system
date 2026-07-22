<?php
/**
 * Core Application Helper Functions
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/security_helper.php';

/**
 * Get absolute URL path
 */
function url(string $path = ''): string {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Safe Redirect Function
 */
function redirect(string $path): void {
    header("Location: " . url($path));
    exit;
}

/**
 * Set Flash Message in Session
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Get and Clear Flash Message
 */
function getFlash(string $type): ?string {
    if (isset($_SESSION['flash_' . $type])) {
        $msg = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $msg;
    }
    return null;
}

/**
 * Format raw bytes into human-readable format
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

/**
 * Generate secure obfuscated filename for storage
 */
function generateRandomFileName(string $extension): string {
    return bin2hex(random_bytes(16)) . '.' . strtolower(ltrim($extension, '.'));
}