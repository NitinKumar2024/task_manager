<?php
/**
 * AI API Endpoint - Powered by Gemini
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/gemini.php';

$db = getDb();
requireAuth($db);

$action = $_GET['action'] ?? '';
$input = getJsonInput();
if (empty($action)) {
    $action = $input['action'] ?? '';
}

// Retrieve Gemini settings
$apiKey = getSetting($db, 'gemini_api_key', '');
$model = getSetting($db, 'gemini_model', 'gemini-3.8-flash');
if ($model === 'gemini-2.5-flash') {
    $model = 'gemini-3.8-flash';
}
$userName = getSetting($db, 'user_name', 'Me');

// Helper to get client or error
function getAiClient(string $apiKey, string $model): GeminiClient {
    if (empty($apiKey)) {
        jsonResponse([
            'success' => false,
            'needs_key' => true,
            'message' => 'Gemini API key is not configured. Please open Settings and enter your free Gemini API key from Google AI Studio.'
        ], 400);
    }
    return new GeminiClient($apiKey, $model);
}

switch ($action) {
    case 'test_key':
        $testKey = trim($input['api_key'] ?? $apiKey);
        $testModel = trim($input['model'] ?? $model);

        if (empty($testKey)) {
            jsonResponse(['success' => false, 'message' => 'Please provide an API key to test.'], 400);
        }

        try {
            $client = new GeminiClient($testKey, $testModel);
            $res = $client->generateContent('Reply with: "NexusAI is connected and ready to supercharge your productivity!"', '', false);
            jsonResponse([
                'success' => true,
                'message' => 'Gemini API connected successfully!',
                'response' => $res['text'],
                'model' => $res['model']
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'quick_parse':
        $text = trim($input['text'] ?? '');
        $autoCreate = !empty($input['auto_create']);

        if (empty($text)) {
            jsonResponse(['success' => false, 'message' => 'Please enter a task description.'], 400);
        }

        $client = getAiClient($apiKey, $model);

        try {
            $categories = $db->query("SELECT id, name FROM categories")->fetchAll();
            $tags = $db->query("SELECT id, name FROM tags")->fetchAll();

            $parsed = $client->quickParseTask($text, $categories, $tags);

            // Match category if parsed
            $matchedCategoryId = null;
            if (!empty($parsed['category'])) {
                foreach ($categories as $c) {
                    if (strcasecmp($c['name'], $parsed['category']) === 0 || stripos($c['name'], $parsed['category']) !== false) {
                        $matchedCategoryId = $c['id'];
                        break;
                    }
                }
            }

            $taskId = null;
            if ($autoCreate) {
                // Insert directly
                $maxPos = (int)$db->query("SELECT MAX(position) FROM tasks")->fetchColumn();
                $stmt = $db->prepare("
                    INSERT INTO tasks (
                        category_id, title, description, priority, energy_level,
                        due_date, estimated_minutes, recurrence, position
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $matchedCategoryId,
                    $parsed['title'],
                    $parsed['description'] ?? '',
                    $parsed['priority'] ?? 'medium',
                    $parsed['energy_level'] ?? 'medium',
                    $parsed['due_date'] ?? null,
                    (int)($parsed['estimated_minutes'] ?? 0),
                    $parsed['recurrence'] ?? 'none',
                    $maxPos + 1
                ]);
                $taskId = (int)$db->lastInsertId();

                if (!empty($parsed['tags']) && is_array($parsed['tags'])) {
                    require_once __DIR__ . '/tasks.php';
                    assignTagsToTask($db, $taskId, $parsed['tags']);
                }

                logActivity($db, $taskId, 'ai_quick_add', "Created via AI Smart Parser: '{$parsed['title']}'");
            }

            jsonResponse([
                'success' => true,
                'parsed' => $parsed,
                'category_id' => $matchedCategoryId,
                'auto_created' => $autoCreate,
                'task_id' => $taskId
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'breakdown':
        $taskId = (int)($input['task_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $desc = trim($input['description'] ?? '');

        if ($taskId > 0) {
            $task = $db->query("SELECT * FROM tasks WHERE id = {$taskId}")->fetch();
            if ($task) {
                $title = $task['title'];
                $desc = $task['description'];
            }
        }

        if (empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Task title is required for AI breakdown.'], 400);
        }

        $client = getAiClient($apiKey, $model);

        try {
            $breakdown = $client->breakdownTask([
                'title' => $title,
                'description' => $desc,
                'priority' => $task['priority'] ?? 'medium'
            ]);

            jsonResponse([
                'success' => true,
                'task_id' => $taskId,
                'summary' => $breakdown['summary'] ?? '',
                'subtasks' => $breakdown['subtasks'] ?? []
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'plan_day':
        $client = getAiClient($apiKey, $model);

        try {
            // Get all pending tasks
            $pendingStmt = $db->query("
                SELECT t.id, t.title, t.priority, t.due_date, t.estimated_minutes, t.energy_level, c.name AS category
                FROM tasks t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.status NOT IN ('completed', 'archived')
                ORDER BY CASE t.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.due_date ASC
                LIMIT 30
            ");
            $pending = $pendingStmt->fetchAll();

            // Get today's completed tasks
            $today = date('Y-m-d');
            $compStmt = $db->prepare("
                SELECT t.id, t.title, t.completed_at
                FROM tasks t
                WHERE t.status = 'completed' AND substr(t.completed_at, 1, 10) = ?
            ");
            $compStmt->execute([$today]);
            $completedToday = $compStmt->fetchAll();

            $plan = $client->planDay($pending, $completedToday, $userName);

            logActivity($db, null, 'ai_plan_day', "Generated AI Daily Plan");

            jsonResponse([
                'success' => true,
                'plan' => $plan,
                'generated_at' => date('Y-m-d H:i')
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'enhance':
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');

        if (empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Task title is required to polish.'], 400);
        }

        $client = getAiClient($apiKey, $model);

        try {
            $enhanced = $client->enhanceTask($title, $description);
            jsonResponse([
                'success' => true,
                'enhanced' => $enhanced
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'chat':
        $message = trim($input['message'] ?? '');
        if (empty($message)) {
            jsonResponse(['success' => false, 'message' => 'Message is required'], 400);
        }

        $client = getAiClient($apiKey, $model);

        try {
            // Fetch recent conversation history
            $histStmt = $db->query("SELECT role, message FROM ai_conversations ORDER BY id DESC LIMIT 10");
            $history = array_reverse($histStmt->fetchAll());

            // Build tasks context
            $tasksContext = $db->query("
                SELECT t.id, t.title, t.status, t.priority, t.due_date, t.estimated_minutes, t.spent_minutes, c.name AS category
                FROM tasks t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.status != 'archived'
                ORDER BY t.due_date ASC
                LIMIT 50
            ")->fetchAll();

            $reply = $client->copilotChat($message, $history, $tasksContext, $userName);

            // Save conversation turns
            $saveStmt = $db->prepare("INSERT INTO ai_conversations (role, message) VALUES (?, ?)");
            $saveStmt->execute(['user', $message]);
            $saveStmt->execute(['assistant', $reply]);

            jsonResponse([
                'success' => true,
                'reply' => $reply
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
        break;

    case 'chat_history':
        $histStmt = $db->query("SELECT id, role, message, created_at FROM ai_conversations ORDER BY id ASC LIMIT 50");
        jsonResponse([
            'success' => true,
            'history' => $histStmt->fetchAll()
        ]);
        break;

    case 'clear_chat':
        $db->exec("DELETE FROM ai_conversations");
        jsonResponse(['success' => true, 'message' => 'Chat history cleared.']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
