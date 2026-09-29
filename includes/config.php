<?php
/**
 * Application Configuration & Bootstrap
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Timezone configuration (default to system or Indian Standard Time based on user metadata, configurable in settings)
date_default_timezone_set('Asia/Kolkata');

// Directory paths
define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'data');
define('DB_PATH', DATA_DIR . DIRECTORY_SEPARATOR . 'tasks.db');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Global response helper
function jsonResponse($data, int $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Get JSON request body
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// Sanitization helper
function cleanString($value): string {
    return trim(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}
