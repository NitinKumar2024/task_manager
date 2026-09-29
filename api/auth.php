<?php
/**
 * Authentication API Endpoint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$db = getDb();
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (empty($action)) {
    $input = getJsonInput();
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'status':
        $configured = isAppConfigured($db);
        $authenticated = isAuthenticated();
        $userName = getSetting($db, 'user_name', 'Me');
        $hasGemini = !empty(getSetting($db, 'gemini_api_key', ''));

        jsonResponse([
            'success' => true,
            'configured' => $configured,
            'authenticated' => $authenticated,
            'user_name' => $userName,
            'has_gemini_key' => $hasGemini
        ]);
        break;

    case 'setup':
        if (isAppConfigured($db)) {
            jsonResponse(['success' => false, 'message' => 'Application is already configured.'], 400);
        }

        $input = getJsonInput();
        $password = trim($input['password'] ?? '');
        $userName = trim($input['user_name'] ?? 'Me');
        $geminiKey = trim($input['gemini_api_key'] ?? '');

        if (strlen($password) < 4) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 4 characters long.'], 400);
        }

        setMasterPassword($db, $password);
        if (!empty($userName)) {
            setSetting($db, 'user_name', $userName);
        }
        if (!empty($geminiKey)) {
            setSetting($db, 'gemini_api_key', $geminiKey);
        }

        logActivity($db, null, 'setup', 'Initial setup completed');
        logInSession($db, true);

        jsonResponse([
            'success' => true,
            'message' => 'Master password and workspace created successfully!',
            'user_name' => $userName
        ]);
        break;

    case 'login':
        if (!isAppConfigured($db)) {
            jsonResponse(['success' => false, 'requires_setup' => true, 'message' => 'Setup required'], 400);
        }

        $input = getJsonInput();
        $password = trim($input['password'] ?? '');
        $remember = !empty($input['remember']);

        if (verifyMasterPassword($db, $password)) {
            logInSession($db, $remember);
            logActivity($db, null, 'login', 'User logged in successfully');
            jsonResponse([
                'success' => true,
                'message' => 'Welcome back!',
                'user_name' => getSetting($db, 'user_name', 'Me')
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Incorrect master password. Please try again.'], 401);
        }
        break;

    case 'logout':
        logActivity($db, null, 'logout', 'User logged out');
        logOutSession($db);
        jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
        break;

    case 'change_password':
        requireAuth($db);
        $input = getJsonInput();
        $currentPassword = trim($input['current_password'] ?? '');
        $newPassword = trim($input['new_password'] ?? '');

        if (!verifyMasterPassword($db, $currentPassword)) {
            jsonResponse(['success' => false, 'message' => 'Current password does not match.'], 400);
        }

        if (strlen($newPassword) < 4) {
            jsonResponse(['success' => false, 'message' => 'New password must be at least 4 characters.'], 400);
        }

        setMasterPassword($db, $newPassword);
        logActivity($db, null, 'password_change', 'Master password updated');
        jsonResponse(['success' => true, 'message' => 'Password updated successfully.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);
}
