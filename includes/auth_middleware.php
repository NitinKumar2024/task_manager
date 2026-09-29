<?php
/**
 * Authentication and Security Middleware
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function isAppConfigured(PDO $db): bool {
    $hash = getSetting($db, 'password_hash', '');
    return !empty($hash);
}

function isAuthenticated(): bool {
    if (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return true;
    }

    // Check remember-me cookie if present
    if (!empty($_COOKIE['task_auth_token'])) {
        $db = getDb();
        $storedToken = getSetting($db, 'remember_token', '');
        $storedExpiry = (int)getSetting($db, 'remember_token_expiry', '0');
        
        if ($storedToken && time() < $storedExpiry && hash_equals($storedToken, hash('sha256', $_COOKIE['task_auth_token']))) {
            $_SESSION['authenticated'] = true;
            $_SESSION['user_name'] = getSetting($db, 'user_name', 'Me');
            return true;
        }
    }

    return false;
}

function requireAuth(PDO $db): void {
    if (!isAppConfigured($db)) {
        jsonResponse([
            'success' => false,
            'requires_setup' => true,
            'message' => 'Application setup required'
        ], 403);
    }

    if (!isAuthenticated()) {
        jsonResponse([
            'success' => false,
            'requires_login' => true,
            'message' => 'Authentication required'
        ], 401);
    }
}

function verifyMasterPassword(PDO $db, string $password): bool {
    $hash = getSetting($db, 'password_hash', '');
    if (empty($hash)) {
        return false;
    }
    return password_verify($password, $hash);
}

function setMasterPassword(PDO $db, string $newPassword): void {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    setSetting($db, 'password_hash', $hash);
}

function logInSession(PDO $db, bool $remember = false): void {
    $_SESSION['authenticated'] = true;
    $_SESSION['user_name'] = getSetting($db, 'user_name', 'Me');
    $_SESSION['login_time'] = time();

    if ($remember) {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expiry = time() + (30 * 86400); // 30 days

        setSetting($db, 'remember_token', $hashedToken);
        setSetting($db, 'remember_token_expiry', (string)$expiry);

        setcookie('task_auth_token', $rawToken, [
            'expires' => $expiry,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function logOutSession(PDO $db): void {
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }

    if (isset($_COOKIE['task_auth_token'])) {
        setSetting($db, 'remember_token', '');
        setSetting($db, 'remember_token_expiry', '0');
        setcookie('task_auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
