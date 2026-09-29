<?php
/**
 * Time Tracking API Endpoint
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
    case 'log':
        $taskId = (int)($input['task_id'] ?? 0);
        $duration = (int)($input['duration_minutes'] ?? 0);
        $note = trim($input['note'] ?? 'Work session');

        if (!$taskId || $duration <= 0) {
            jsonResponse(['success' => false, 'message' => 'Task ID and positive duration required'], 400);
        }

        $stmt = $db->prepare("INSERT INTO time_logs (task_id, duration_minutes, note) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $duration, $note]);

        $task = $db->query("SELECT title FROM tasks WHERE id = {$taskId}")->fetch();
        $taskTitle = $task['title'] ?? "ID #{$taskId}";

        logActivity($db, $taskId, 'time_logged', "Logged {$duration} minutes on '{$taskTitle}' ({$note})");

        // Calculate total spent
        $totalSpent = (int)$db->query("SELECT SUM(duration_minutes) FROM time_logs WHERE task_id = {$taskId}")->fetchColumn();

        jsonResponse([
            'success' => true,
            'message' => "Logged {$duration} minutes",
            'total_spent' => $totalSpent
        ]);
        break;

    case 'list':
        $taskId = (int)($_GET['task_id'] ?? 0);
        if (!$taskId) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM time_logs WHERE task_id = ? ORDER BY logged_at DESC");
        $stmt->execute([$taskId]);
        jsonResponse(['success' => true, 'time_logs' => $stmt->fetchAll()]);
        break;

    case 'recent':
        $stmt = $db->query("
            SELECT tl.*, t.title AS task_title, c.name AS category_name, c.color AS category_color
            FROM time_logs tl
            JOIN tasks t ON tl.task_id = t.id
            LEFT JOIN categories c ON t.category_id = c.id
            ORDER BY tl.logged_at DESC
            LIMIT 25
        ");
        jsonResponse(['success' => true, 'time_logs' => $stmt->fetchAll()]);
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Log ID required'], 400);
        }
        $db->prepare("DELETE FROM time_logs WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Time log removed']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
