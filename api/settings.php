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

        $availJson = getSetting($db, 'available_models', '');
        $availableModels = !empty($availJson) ? json_decode($availJson, true) : null;
        if (!is_array($availableModels) || empty($availableModels)) {
            $availableModels = ['gemini-3.8-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-pro', 'gemini-1.5-flash'];
        }

        $activeModel = getSetting($db, 'gemini_model', 'gemini-3.8-flash');
        if ($activeModel === 'gemini-2.5-flash') {
            $activeModel = 'gemini-3.8-flash';
        }
        if (!in_array($activeModel, $availableModels)) {
            array_unshift($availableModels, $activeModel);
        }

        jsonResponse([
            'success' => true,
            'settings' => [
                'app_title' => getSetting($db, 'app_title', 'NexusAI Task Master'),
                'user_name' => getSetting($db, 'user_name', 'Me'),
                'gemini_model' => $activeModel,
                'available_models' => array_values(array_unique($availableModels)),
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
            $modelName = ltrim(trim($input['gemini_model']), '/');
            if (str_starts_with($modelName, 'models/')) {
                $modelName = substr($modelName, 7);
            }
            if (!empty($modelName)) {
                setSetting($db, 'gemini_model', $modelName);

                // Add to available_models
                $avail = json_decode(getSetting($db, 'available_models', '[]'), true) ?: ['gemini-3.8-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-pro', 'gemini-1.5-flash'];
                if (!in_array($modelName, $avail)) {
                    array_unshift($avail, $modelName);
                    setSetting($db, 'available_models', json_encode(array_values(array_unique($avail))));
                }
            }
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

    case 'add_model':
        $newModel = ltrim(trim($input['model'] ?? ''), '/');
        if (str_starts_with($newModel, 'models/')) {
            $newModel = substr($newModel, 7);
        }

        if (empty($newModel)) {
            jsonResponse(['success' => false, 'message' => 'Model name cannot be empty'], 400);
        }

        $avail = json_decode(getSetting($db, 'available_models', '[]'), true) ?: ['gemini-3.8-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-pro', 'gemini-1.5-flash'];
        if (!in_array($newModel, $avail)) {
            array_unshift($avail, $newModel);
        }
        setSetting($db, 'available_models', json_encode(array_values(array_unique($avail))));
        setSetting($db, 'gemini_model', $newModel);

        logActivity($db, null, 'model_added', "Added and activated custom model: {$newModel}");

        jsonResponse([
            'success' => true,
            'message' => "Model '{$newModel}' added and set as active!",
            'model' => $newModel,
            'available_models' => array_values(array_unique($avail))
        ]);
        break;

    case 'delete_model':
        $delModel = trim($input['model'] ?? '');
        $avail = json_decode(getSetting($db, 'available_models', '[]'), true) ?: ['gemini-3.8-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-pro', 'gemini-1.5-flash'];
        $avail = array_values(array_filter($avail, fn($m) => $m !== $delModel));

        if (empty($avail)) {
            $avail = ['gemini-3.8-flash', 'gemini-3.5-flash-lite'];
        }

        setSetting($db, 'available_models', json_encode($avail));

        $curActive = getSetting($db, 'gemini_model', 'gemini-3.8-flash');
        if ($curActive === $delModel) {
            setSetting($db, 'gemini_model', $avail[0]);
        }

        jsonResponse([
            'success' => true,
            'message' => "Model '{$delModel}' removed",
            'active_model' => getSetting($db, 'gemini_model', 'gemini-3.8-flash'),
            'available_models' => $avail
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
