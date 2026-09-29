<?php
/**
 * Tasks API Endpoint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$db = getDb();
requireAuth($db);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if (empty($action) && $method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'list':
        $status = $_GET['status'] ?? 'active';
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $tag = trim($_GET['tag'] ?? '');
        $priority = trim($_GET['priority'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $dueFilter = trim($_GET['due'] ?? '');
        $sort = trim($_GET['sort'] ?? 'position');

        $where = [];
        $params = [];

        // Status filter
        if ($status === 'active') {
            $where[] = "t.status != 'completed' AND t.status != 'archived'";
        } elseif ($status === 'all') {
            $where[] = "t.status != 'archived'";
        } elseif (!empty($status)) {
            $where[] = "t.status = ?";
            $params[] = $status;
        }

        // Category filter
        if ($categoryId !== null) {
            $where[] = "t.category_id = ?";
            $params[] = $categoryId;
        }

        // Priority filter
        if (!empty($priority)) {
            $where[] = "t.priority = ?";
            $params[] = $priority;
        }

        // Search query
        if (!empty($search)) {
            $where[] = "(t.title LIKE ? OR t.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        // Due date filter
        $today = date('Y-m-d');
        if ($dueFilter === 'today') {
            $where[] = "substr(t.due_date, 1, 10) = ?";
            $params[] = $today;
        } elseif ($dueFilter === 'overdue') {
            $where[] = "substr(t.due_date, 1, 10) < ? AND t.status != 'completed'";
            $params[] = $today;
        } elseif ($dueFilter === 'upcoming') {
            $where[] = "substr(t.due_date, 1, 10) >= ?";
            $params[] = $today;
        }

        // Tag filter
        if (!empty($tag)) {
            $where[] = "t.id IN (SELECT tt.task_id FROM task_tags tt JOIN tags tg ON tt.tag_id = tg.id WHERE tg.name = ?)";
            $params[] = $tag;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Sorting
        $orderBy = "t.is_pinned DESC, ";
        switch ($sort) {
            case 'due_asc':
                $orderBy .= "CASE WHEN t.due_date IS NULL OR t.due_date = '' THEN 1 ELSE 0 END, t.due_date ASC, t.id DESC";
                break;
            case 'due_desc':
                $orderBy .= "t.due_date DESC, t.id DESC";
                break;
            case 'priority':
                $orderBy .= "CASE t.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END, t.due_date ASC";
                break;
            case 'created_desc':
                $orderBy .= "t.id DESC";
                break;
            case 'position':
            default:
                $orderBy .= "t.position ASC, t.id DESC";
                break;
        }

        $sql = "SELECT 
                    t.*,
                    c.name AS category_name,
                    c.color AS category_color,
                    c.icon AS category_icon,
                    (SELECT COUNT(*) FROM subtasks st WHERE st.task_id = t.id) AS subtask_total,
                    (SELECT COUNT(*) FROM subtasks st WHERE st.task_id = t.id AND st.is_completed = 1) AS subtask_completed,
                    (SELECT COALESCE(SUM(duration_minutes), 0) FROM time_logs tl WHERE tl.task_id = t.id) AS total_logged_minutes
                FROM tasks t
                LEFT JOIN categories c ON t.category_id = c.id
                {$whereClause}
                ORDER BY {$orderBy}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        // Fetch tags for these tasks
        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $inClause = implode(',', array_fill(0, count($taskIds), '?'));
            $tagStmt = $db->prepare("
                SELECT tt.task_id, tg.id, tg.name, tg.color 
                FROM task_tags tt 
                JOIN tags tg ON tt.tag_id = tg.id 
                WHERE tt.task_id IN ($inClause)
            ");
            $tagStmt->execute($taskIds);
            $tagsByTask = [];
            while ($row = $tagStmt->fetch()) {
                $tagsByTask[$row['task_id']][] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'color' => $row['color']
                ];
            }

            foreach ($tasks as &$task) {
                $task['tags'] = $tagsByTask[$task['id']] ?? [];
                $task['subtask_total'] = (int)$task['subtask_total'];
                $task['subtask_completed'] = (int)$task['subtask_completed'];
                $task['is_pinned'] = (bool)$task['is_pinned'];
                $task['estimated_minutes'] = (int)$task['estimated_minutes'];
                $task['spent_minutes'] = (int)$task['spent_minutes'] + (int)$task['total_logged_minutes'];
            }
        }

        jsonResponse([
            'success' => true,
            'count' => count($tasks),
            'tasks' => $tasks
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $stmt = $db->prepare("
            SELECT t.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
            FROM tasks t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $task = $stmt->fetch();

        if (!$task) {
            jsonResponse(['success' => false, 'message' => 'Task not found'], 404);
        }

        // Subtasks
        $subStmt = $db->prepare("SELECT * FROM subtasks WHERE task_id = ? ORDER BY position ASC, id ASC");
        $subStmt->execute([$id]);
        $task['subtasks'] = $subStmt->fetchAll();

        // Tags
        $tagStmt = $db->prepare("SELECT tg.id, tg.name, tg.color FROM task_tags tt JOIN tags tg ON tt.tag_id = tg.id WHERE tt.task_id = ?");
        $tagStmt->execute([$id]);
        $task['tags'] = $tagStmt->fetchAll();

        // Time logs
        $tlStmt = $db->prepare("SELECT * FROM time_logs WHERE task_id = ? ORDER BY logged_at DESC");
        $tlStmt->execute([$id]);
        $task['time_logs'] = $tlStmt->fetchAll();

        // Activity logs
        $actStmt = $db->prepare("SELECT * FROM activity_log WHERE task_id = ? ORDER BY created_at DESC LIMIT 20");
        $actStmt->execute([$id]);
        $task['activities'] = $actStmt->fetchAll();

        jsonResponse(['success' => true, 'task' => $task]);
        break;

    case 'create':
        $input = getJsonInput();
        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Task title is required'], 400);
        }

        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $description = trim($input['description'] ?? '');
        $status = in_array($input['status'] ?? '', ['inbox', 'planned', 'in_progress', 'review', 'completed', 'archived']) ? $input['status'] : 'inbox';
        $priority = in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'critical']) ? $input['priority'] : 'medium';
        $energyLevel = in_array($input['energy_level'] ?? '', ['low', 'medium', 'high']) ? $input['energy_level'] : 'medium';
        $dueDate = !empty($input['due_date']) ? trim($input['due_date']) : null;
        $startDate = !empty($input['start_date']) ? trim($input['start_date']) : null;
        $estimatedMinutes = (int)($input['estimated_minutes'] ?? 0);
        $recurrence = in_array($input['recurrence'] ?? '', ['none', 'daily', 'weekly', 'weekdays', 'monthly']) ? $input['recurrence'] : 'none';
        $isPinned = !empty($input['is_pinned']) ? 1 : 0;
        $aiNotes = trim($input['ai_notes'] ?? '');

        // Position: place at top or bottom
        $maxPos = (int)$db->query("SELECT MAX(position) FROM tasks")->fetchColumn();
        $position = $maxPos + 1;

        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("
            INSERT INTO tasks (
                category_id, title, description, status, priority, energy_level,
                due_date, start_date, completed_at, estimated_minutes, recurrence,
                position, is_pinned, ai_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $categoryId, $title, $description, $status, $priority, $energyLevel,
            $dueDate, $startDate, $completedAt, $estimatedMinutes, $recurrence,
            $position, $isPinned, $aiNotes
        ]);
        $taskId = (int)$db->lastInsertId();

        // Handle tags
        if (!empty($input['tags']) && is_array($input['tags'])) {
            assignTagsToTask($db, $taskId, $input['tags']);
        }

        // Handle subtasks if provided initially
        if (!empty($input['subtasks']) && is_array($input['subtasks'])) {
            $subStmt = $db->prepare("INSERT INTO subtasks (task_id, title, estimated_minutes, position) VALUES (?, ?, ?, ?)");
            foreach ($input['subtasks'] as $idx => $st) {
                $stTitle = is_string($st) ? trim($st) : trim($st['title'] ?? '');
                $stMins = is_array($st) ? (int)($st['estimated_minutes'] ?? 0) : 0;
                if (!empty($stTitle)) {
                    $subStmt->execute([$taskId, $stTitle, $stMins, $idx]);
                }
            }
        }

        logActivity($db, $taskId, 'created', "Task '{$title}' created");

        jsonResponse([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
        break;

    case 'update':
        $input = getJsonInput();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $title = trim($input['title'] ?? '');
        if (empty($title)) {
            jsonResponse(['success' => false, 'message' => 'Task title cannot be empty'], 400);
        }

        $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
        $description = trim($input['description'] ?? '');
        $status = in_array($input['status'] ?? '', ['inbox', 'planned', 'in_progress', 'review', 'completed', 'archived']) ? $input['status'] : 'inbox';
        $priority = in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'critical']) ? $input['priority'] : 'medium';
        $energyLevel = in_array($input['energy_level'] ?? '', ['low', 'medium', 'high']) ? $input['energy_level'] : 'medium';
        $dueDate = !empty($input['due_date']) ? trim($input['due_date']) : null;
        $startDate = !empty($input['start_date']) ? trim($input['start_date']) : null;
        $estimatedMinutes = (int)($input['estimated_minutes'] ?? 0);
        $recurrence = in_array($input['recurrence'] ?? '', ['none', 'daily', 'weekly', 'weekdays', 'monthly']) ? $input['recurrence'] : 'none';
        $isPinned = !empty($input['is_pinned']) ? 1 : 0;
        $aiNotes = trim($input['ai_notes'] ?? '');

        // Fetch current status to check completion timestamp
        $currentTask = $db->query("SELECT status, completed_at FROM tasks WHERE id = {$id}")->fetch();
        $completedAt = $currentTask['completed_at'];
        if ($status === 'completed' && $currentTask['status'] !== 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        } elseif ($status !== 'completed') {
            $completedAt = null;
        }

        $stmt = $db->prepare("
            UPDATE tasks SET 
                category_id = ?, title = ?, description = ?, status = ?, priority = ?, energy_level = ?,
                due_date = ?, start_date = ?, completed_at = ?, estimated_minutes = ?, recurrence = ?,
                is_pinned = ?, ai_notes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $categoryId, $title, $description, $status, $priority, $energyLevel,
            $dueDate, $startDate, $completedAt, $estimatedMinutes, $recurrence,
            $isPinned, $aiNotes, $id
        ]);

        if (isset($input['tags']) && is_array($input['tags'])) {
            assignTagsToTask($db, $id, $input['tags']);
        }

        logActivity($db, $id, 'updated', "Task '{$title}' updated");

        jsonResponse(['success' => true, 'message' => 'Task updated successfully']);
        break;

    case 'toggle_complete':
        $input = getJsonInput();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $task = $db->query("SELECT * FROM tasks WHERE id = {$id}")->fetch();
        if (!$task) {
            jsonResponse(['success' => false, 'message' => 'Task not found'], 404);
        }

        $now = date('Y-m-d H:i:s');
        $newStatus = ($task['status'] === 'completed') ? 'inbox' : 'completed';
        $completedAt = ($newStatus === 'completed') ? $now : null;

        $stmt = $db->prepare("UPDATE tasks SET status = ?, completed_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $completedAt, $id]);

        logActivity($db, $id, $newStatus === 'completed' ? 'completed' : 'uncompleted', "Task status set to {$newStatus}");

        // If recurring and newly completed, spawn next instance!
        $spawnedNext = false;
        if ($newStatus === 'completed' && $task['recurrence'] !== 'none' && !empty($task['due_date'])) {
            $spawnedNext = handleRecurringTask($db, $task);
        }

        jsonResponse([
            'success' => true,
            'new_status' => $newStatus,
            'is_completed' => ($newStatus === 'completed'),
            'spawned_next' => $spawnedNext
        ]);
        break;

    case 'reorder':
        $input = getJsonInput();
        $items = $input['items'] ?? []; // [{id: 1, position: 0, status: 'in_progress'}, ...]

        if (!empty($items) && is_array($items)) {
            $stmt = $db->prepare("UPDATE tasks SET position = ?, status = COALESCE(?, status), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            foreach ($items as $item) {
                $taskId = (int)($item['id'] ?? 0);
                $pos = (int)($item['position'] ?? 0);
                $st = !empty($item['status']) ? $item['status'] : null;
                if ($taskId > 0) {
                    $stmt->execute([$pos, $st, $taskId]);
                }
            }
        }

        jsonResponse(['success' => true]);
        break;

    case 'delete':
        $input = getJsonInput();
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Task ID required'], 400);
        }

        $title = $db->query("SELECT title FROM tasks WHERE id = {$id}")->fetchColumn() ?: "ID #{$id}";
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$id]);

        logActivity($db, null, 'deleted', "Task '{$title}' deleted");

        jsonResponse(['success' => true, 'message' => 'Task deleted successfully']);
        break;

    case 'batch':
        $input = getJsonInput();
        $taskIds = $input['task_ids'] ?? [];
        $batchAction = $input['batch_action'] ?? '';

        if (empty($taskIds) || !is_array($taskIds)) {
            jsonResponse(['success' => false, 'message' => 'No tasks selected'], 400);
        }

        $sanitizedIds = array_map('intval', $taskIds);
        $inClause = implode(',', $sanitizedIds);

        switch ($batchAction) {
            case 'complete':
                $now = date('Y-m-d H:i:s');
                $db->exec("UPDATE tasks SET status = 'completed', completed_at = '{$now}', updated_at = CURRENT_TIMESTAMP WHERE id IN ({$inClause})");
                logActivity($db, null, 'batch_complete', "Batch completed " . count($sanitizedIds) . " tasks");
                break;

            case 'delete':
                $db->exec("DELETE FROM tasks WHERE id IN ({$inClause})");
                logActivity($db, null, 'batch_delete', "Batch deleted " . count($sanitizedIds) . " tasks");
                break;

            case 'change_category':
                $newCatId = (int)($input['category_id'] ?? 0);
                $catVal = $newCatId > 0 ? $newCatId : 'NULL';
                $db->exec("UPDATE tasks SET category_id = {$catVal}, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$inClause})");
                break;

            case 'change_priority':
                $newPriority = $input['priority'] ?? 'medium';
                if (in_array($newPriority, ['low', 'medium', 'high', 'critical'])) {
                    $stmt = $db->prepare("UPDATE tasks SET priority = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$inClause})");
                    $stmt->execute([$newPriority]);
                }
                break;

            default:
                jsonResponse(['success' => false, 'message' => 'Invalid batch action'], 400);
        }

        jsonResponse(['success' => true, 'message' => 'Batch action executed']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Assign and sync tags with task
 */
function assignTagsToTask(PDO $db, int $taskId, array $tags): void {
    $db->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$taskId]);
    if (empty($tags)) return;

    $getTagStmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $insertTagStmt = $db->prepare("INSERT OR IGNORE INTO tags (name) VALUES (?)");
    $linkStmt = $db->prepare("INSERT OR IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");

    foreach ($tags as $tagItem) {
        $tagName = is_array($tagItem) ? trim($tagItem['name'] ?? '') : trim($tagItem);
        $tagName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $tagName));
        if (empty($tagName)) continue;

        $getTagStmt->execute([$tagName]);
        $tagId = $getTagStmt->fetchColumn();

        if (!$tagId) {
            $insertTagStmt->execute([$tagName]);
            $tagId = $db->lastInsertId();
            if (!$tagId) {
                $getTagStmt->execute([$tagName]);
                $tagId = $getTagStmt->fetchColumn();
            }
        }

        if ($tagId) {
            $linkStmt->execute([$taskId, $tagId]);
        }
    }
}

/**
 * Handle automatic generation of recurring tasks
 */
function handleRecurringTask(PDO $db, array $task): bool {
    try {
        $curDue = new DateTime($task['due_date']);
        switch ($task['recurrence']) {
            case 'daily':
                $curDue->modify('+1 day');
                break;
            case 'weekdays':
                do {
                    $curDue->modify('+1 day');
                } while ($curDue->format('N') >= 6); // skip Saturday (6) and Sunday (7)
                break;
            case 'weekly':
                $curDue->modify('+1 week');
                break;
            case 'monthly':
                $curDue->modify('+1 month');
                break;
            default:
                return false;
        }

        $newDueDate = $curDue->format(strlen($task['due_date']) > 10 ? 'Y-m-d H:i' : 'Y-m-d');

        $stmt = $db->prepare("
            INSERT INTO tasks (
                category_id, title, description, status, priority, energy_level,
                due_date, estimated_minutes, recurrence, is_pinned, ai_notes
            ) VALUES (?, ?, ?, 'planned', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $task['category_id'],
            $task['title'],
            $task['description'],
            $task['priority'],
            $task['energy_level'],
            $newDueDate,
            $task['estimated_minutes'],
            $task['recurrence'],
            $task['is_pinned'],
            $task['ai_notes']
        ]);
        $newTaskId = (int)$db->lastInsertId();

        // Copy tags
        $tags = $db->query("SELECT tag_id FROM task_tags WHERE task_id = {$task['id']}")->fetchAll(PDO::FETCH_COLUMN);
        $linkStmt = $db->prepare("INSERT INTO task_tags (task_id, tag_id) VALUES (?, ?)");
        foreach ($tags as $tId) {
            $linkStmt->execute([$newTaskId, $tId]);
        }

        logActivity($db, $newTaskId, 'recurring_spawn', "Auto-created recurring follow-up due on {$newDueDate}");
        return true;
    } catch (Exception $e) {
        return false;
    }
}
