<?php
/**
 * Categories API Endpoint
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
        $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM tasks t WHERE t.category_id = c.id AND t.status != 'completed' AND t.status != 'archived') AS active_count,
                    (SELECT COUNT(*) FROM tasks t WHERE t.category_id = c.id AND t.status = 'completed') AS completed_count
                FROM categories c 
                ORDER BY c.name ASC";
        $categories = $db->query($sql)->fetchAll();
        jsonResponse(['success' => true, 'categories' => $categories]);
        break;

    case 'create':
        $name = trim($input['name'] ?? '');
        $color = trim($input['color'] ?? '#3b82f6');
        $icon = trim($input['icon'] ?? 'folder');

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Category name is required'], 400);
        }

        try {
            $stmt = $db->prepare("INSERT INTO categories (name, color, icon) VALUES (?, ?, ?)");
            $stmt->execute([$name, $color, $icon]);
            $newId = (int)$db->lastInsertId();
            logActivity($db, null, 'category_created', "Created category '{$name}'");
            jsonResponse(['success' => true, 'id' => $newId, 'message' => 'Category created']);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'A category with this name already exists'], 400);
        }
        break;

    case 'update':
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $color = trim($input['color'] ?? '#3b82f6');
        $icon = trim($input['icon'] ?? 'folder');

        if (!$id || empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Valid ID and name required'], 400);
        }

        try {
            $stmt = $db->prepare("UPDATE categories SET name = ?, color = ?, icon = ? WHERE id = ?");
            $stmt->execute([$name, $color, $icon, $id]);
            jsonResponse(['success' => true, 'message' => 'Category updated']);
        } catch (PDOException $e) {
            jsonResponse(['success' => false, 'message' => 'Category name already exists'], 400);
        }
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'Category ID required'], 400);
        }

        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => 'Category deleted']);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
