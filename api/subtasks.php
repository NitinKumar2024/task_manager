<?php
/**
 * Subtasks API Endpoint
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
    case 'list':
        $taskId = (int)($_GET['task_id'] ?? 0);
        if (!$taskId) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $stmt = $db->prepare("SELECT * FROM subtasks WHERE task_id = ? ORDER BY position ASC, id ASC");
        $stmt->execute([$taskId]);
        jsonResponse(['success' => true, 'subtasks' => $stmt->fetchAll()]);
        break;

    case 'create':
        $taskId = (int)($input['task_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $estimated = (int)($input['estimated_minutes'] ?? 0);

        if (!$taskId || empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Task ID and title required'], 400);
        }

        $maxPos = (int)$db->query("SELECT MAX(position) FROM subtasks WHERE task_id = {$taskId}")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO subtasks (task_id, title, estimated_minutes, position) VALUES (?, ?, ?, ?)");
        $stmt->execute([$taskId, $title, $estimated, $maxPos + 1]);

        logActivity($db, $taskId, 'subtask_created', "Added subtask: {$title}");

        jsonResponse([
            'success' => true,
            'subtask_id' => (int)$db->lastInsertId(),
            'message' => 'Subtask added'
        ]);
        break;

    case 'toggle':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Subtask ID required'], 400);
        }

        $sub = $db->query("SELECT * FROM subtasks WHERE id = {$id}")->fetch();
        if (!$sub) {
            jsonResponse(['success' => false, 'message' => 'Subtask not found'], 404);
        }

        $newCompleted = $sub['is_completed'] ? 0 : 1;
        $stmt = $db->prepare("UPDATE subtasks SET is_completed = ? WHERE id = ?");
        $stmt->execute([$newCompleted, $id]);

        logActivity($db, (int)$sub['task_id'], 'subtask_toggled', "Subtask '{$sub['title']}' marked as " . ($newCompleted ? 'completed' : 'incomplete'));

        // Return updated progress of the parent task
        $statsStmt = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed FROM subtasks WHERE task_id = ?");
        $statsStmt->execute([(int)$sub['task_id']]);
        $stats = $statsStmt->fetch();

        jsonResponse([
            'success' => true,
            'is_completed' => (bool)$newCompleted,
            'task_id' => (int)$sub['task_id'],
            'total' => (int)$stats['total'],
            'completed' => (int)$stats['completed']
        ]);
        break;

    case 'update':
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $estimated = (int)($input['estimated_minutes'] ?? 0);

        if (!$id || empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Valid ID and title required'], 400);
        }

        $stmt = $db->prepare("UPDATE subtasks SET title = ?, estimated_minutes = ? WHERE id = ?");
        $stmt->execute([$title, $estimated, $id]);

        jsonResponse(['success' => true, 'message' => 'Subtask updated']);
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Subtask ID required'], 400);
        }

        $sub = $db->query("SELECT task_id, title FROM subtasks WHERE id = {$id}")->fetch();
        if ($sub) {
            $stmt = $db->prepare("DELETE FROM subtasks WHERE id = ?");
            $stmt->execute([$id]);
            logActivity($db, (int)$sub['task_id'], 'subtask_deleted', "Deleted subtask '{$sub['title']}'");
        }

        jsonResponse(['success' => true, 'message' => 'Subtask removed']);
        break;

    case 'bulk_create':
        $taskId = (int)($input['task_id'] ?? 0);
        $subtasks = $input['subtasks'] ?? [];

        if (!$taskId || empty($subtasks) || !is_array($subtasks)) {
            jsonResponse(['success' => false, 'message' => 'Task ID and subtasks list required'], 400);
        }

        $maxPos = (int)$db->query("SELECT MAX(position) FROM subtasks WHERE task_id = {$taskId}")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO subtasks (task_id, title, estimated_minutes, position) VALUES (?, ?, ?, ?)");

        $count = 0;
        foreach ($subtasks as $st) {
            $title = is_string($st) ? trim($st) : trim($st['title'] ?? '');
            $estimated = is_array($st) ? (int)($st['estimated_minutes'] ?? 0) : 0;
            if (!empty($title)) {
                $maxPos++;
                $stmt->execute([$taskId, $title, $estimated, $maxPos]);
                $count++;
            }
        }

        logActivity($db, $taskId, 'ai_breakdown_applied', "Added {$count} subtasks via AI Breakdown");

        jsonResponse([
            'success' => true,
            'added_count' => $count,
            'message' => "Successfully added {$count} subtasks"
        ]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
