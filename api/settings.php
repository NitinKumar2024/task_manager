<?php
/**
 * Settings and Data Backup/Restore API Endpoint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$db = getDb();
requireAuth($db);

$action = $_GET['action'] ?? '';
$input = getJsonInput();
if (empty($action)) {
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'get':
        $apiKey = getSetting($db, 'gemini_api_key', '');
        $maskedKey = '';
        if (!empty($apiKey)) {
            $maskedKey = substr($apiKey, 0, 4) . '...' . substr($apiKey, -4);
        }

        jsonResponse([
            'success' => true,
            'settings' => [
                'app_title' => getSetting($db, 'app_title', 'NexusAI Task Master'),
                'user_name' => getSetting($db, 'user_name', 'Me'),
                'gemini_model' => getSetting($db, 'gemini_model', 'gemini-2.5-flash'),
                'has_gemini_key' => !empty($apiKey),
                'masked_gemini_key' => $maskedKey,
                'theme' => getSetting($db, 'theme', 'dark'),
                'daily_goal_minutes' => (int)getSetting($db, 'daily_goal_minutes', '240'),
                'timezone' => getSetting($db, 'timezone', 'Asia/Kolkata')
            ]
        ]);
        break;

    case 'save':
        if (isset($input['app_title'])) {
            setSetting($db, 'app_title', trim($input['app_title']));
        }
        if (isset($input['user_name'])) {
            setSetting($db, 'user_name', trim($input['user_name']));
            $_SESSION['user_name'] = trim($input['user_name']);
        }
        if (!empty($input['gemini_api_key'])) {
            setSetting($db, 'gemini_api_key', trim($input['gemini_api_key']));
        }
        if (isset($input['gemini_model'])) {
            setSetting($db, 'gemini_model', trim($input['gemini_model']));
        }
        if (isset($input['theme'])) {
            setSetting($db, 'theme', trim($input['theme']));
        }
        if (isset($input['daily_goal_minutes'])) {
            setSetting($db, 'daily_goal_minutes', (string)(int)$input['daily_goal_minutes']);
        }

        logActivity($db, null, 'settings_updated', 'System settings updated');

        jsonResponse([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
        break;

    case 'export_json':
        $tasks = $db->query("SELECT * FROM tasks")->fetchAll();
        $categories = $db->query("SELECT * FROM categories")->fetchAll();
        $tags = $db->query("SELECT * FROM tags")->fetchAll();
        $subtasks = $db->query("SELECT * FROM subtasks")->fetchAll();
        $timeLogs = $db->query("SELECT * FROM time_logs")->fetchAll();

        $backup = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'categories' => $categories,
            'tags' => $tags,
            'tasks' => $tasks,
            'subtasks' => $subtasks,
            'time_logs' => $timeLogs
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="nexus_tasks_backup_' . date('Ymd_His') . '.json"');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;

    case 'export_db':
        if (!file_exists(DB_PATH)) {
            jsonResponse(['success' => false, 'message' => 'Database file not found.'], 404);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="tasks_' . date('Ymd_His') . '.db"');
        header('Content-Length: ' . filesize(DB_PATH));
        readfile(DB_PATH);
        exit;

    case 'import_json':
        $data = $input['data'] ?? null;
        if (!$data || !is_array($data) || empty($data['tasks'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid backup file format.'], 400);
        }

        $imported = 0;
        $insertTask = $db->prepare("
            INSERT INTO tasks (title, description, status, priority, due_date, estimated_minutes, position)
            VALUES (?, ?, ?, ?, ?, ?, 999)
        ");

        foreach ($data['tasks'] as $t) {
            if (!empty($t['title'])) {
                $insertTask->execute([
                    $t['title'],
                    $t['description'] ?? '',
                    $t['status'] ?? 'inbox',
                    $t['priority'] ?? 'medium',
                    $t['due_date'] ?? null,
                    (int)($t['estimated_minutes'] ?? 0)
                ]);
                $imported++;
            }
        }

        logActivity($db, null, 'data_import', "Imported {$imported} tasks from backup");

        jsonResponse([
            'success' => true,
            'message' => "Successfully imported {$imported} tasks."
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
