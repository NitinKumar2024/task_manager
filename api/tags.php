<?php
/**
 * Tags API Endpoint
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
        $sql = "SELECT tg.*, 
                    (SELECT COUNT(*) FROM task_tags tt JOIN tasks t ON tt.task_id = t.id WHERE tt.tag_id = tg.id AND t.status != 'completed') AS task_count
                FROM tags tg
                ORDER BY task_count DESC, tg.name ASC";
        $tags = $db->query($sql)->fetchAll();
        jsonResponse(['success' => true, 'tags' => $tags]);
        break;

    case 'create':
        $name = trim($input['name'] ?? '');
        $color = trim($input['color'] ?? '#64748b');

        $name = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $name));
        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Tag name is required (letters and numbers only)'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'name' => $name, 'color' => $color]);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'Tag already exists'], 400);
        }
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Tag ID required'], 400);
        }
        $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Tag deleted']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
