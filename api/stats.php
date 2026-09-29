<?php
/**
 * Analytics and Productivity Metrics API Endpoint
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$db = getDb();
requireAuth($db);

$today = date('Y-m-d');

// Basic counts
$totalTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status != 'archived'")->fetchColumn();
$activeTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('completed', 'archived')")->fetchColumn();
$completedTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn();
$overdueTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('completed', 'archived') AND substr(due_date, 1, 10) < '{$today}'")->fetchColumn();
$todayDueTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status NOT IN ('completed', 'archived') AND substr(due_date, 1, 10) = '{$today}'")->fetchColumn();
$todayCompletedTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND substr(completed_at, 1, 10) = '{$today}'")->fetchColumn();

// Completion percentage
$completionRate = ($totalTasks > 0) ? round(($completedTasks / $totalTasks) * 100) : 0;

// Total time logged
$totalMinutesLogged = (int)$db->query("SELECT SUM(duration_minutes) FROM time_logs")->fetchColumn();
$todayMinutesLogged = (int)$db->query("SELECT SUM(duration_minutes) FROM time_logs WHERE substr(logged_at, 1, 10) = '{$today}'")->fetchColumn();

// Priority distribution
$priorityDist = $db->query("
    SELECT priority, COUNT(*) as count 
    FROM tasks 
    WHERE status NOT IN ('completed', 'archived') 
    GROUP BY priority
")->fetchAll();

// Category distribution
$categoryDist = $db->query("
    SELECT COALESCE(c.name, 'Uncategorized') as category, COALESCE(c.color, '#94a3b8') as color, COUNT(t.id) as count
    FROM tasks t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.status NOT IN ('completed', 'archived')
    GROUP BY t.category_id
")->fetchAll();

// Category time distribution
$timeByCategory = $db->query("
    SELECT COALESCE(c.name, 'Uncategorized') as category, COALESCE(c.color, '#94a3b8') as color, SUM(tl.duration_minutes) as minutes
    FROM time_logs tl
    JOIN tasks t ON tl.task_id = t.id
    LEFT JOIN categories c ON t.category_id = c.id
    GROUP BY t.category_id
")->fetchAll();

// Last 7 days completion trend
$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND substr(completed_at, 1, 10) = ?");
    $stmt->execute([$date]);
    $count = (int)$stmt->fetchColumn();

    $stmtTime = $db->prepare("SELECT COALESCE(SUM(duration_minutes), 0) FROM time_logs WHERE substr(logged_at, 1, 10) = ?");
    $stmtTime->execute([$date]);
    $mins = (int)$stmtTime->fetchColumn();

    $last7Days[] = [
        'date' => $date,
        'label' => date('D', strtotime($date)),
        'completed_tasks' => $count,
        'minutes_logged' => $mins
    ];
}

// Calculate streak
$streak = 0;
$checkDate = new DateTime();
while (true) {
    $dStr = $checkDate->format('Y-m-d');
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND substr(completed_at, 1, 10) = ?");
    $checkStmt->execute([$dStr]);
    $hadTask = ((int)$checkStmt->fetchColumn()) > 0;

    if ($hadTask) {
        $streak++;
        $checkDate->modify('-1 day');
    } else {
        // If checking today and no tasks yet, check if yesterday had tasks before breaking streak
        if ($dStr === $today && $streak === 0) {
            $checkDate->modify('-1 day');
            continue;
        }
        break;
    }
}

// Recent activities (audit trail)
$recentActivities = $db->query("
    SELECT a.*, t.title as task_title
    FROM activity_log a
    LEFT JOIN tasks t ON a.task_id = t.id
    ORDER BY a.created_at DESC
    LIMIT 15
")->fetchAll();

jsonResponse([
    'success' => true,
    'metrics' => [
        'total_tasks' => $totalTasks,
        'active_tasks' => $activeTasks,
        'completed_tasks' => $completedTasks,
        'overdue_tasks' => $overdueTasks,
        'today_due_tasks' => $todayDueTasks,
        'today_completed_tasks' => $todayCompletedTasks,
        'completion_rate' => $completionRate,
        'total_minutes_logged' => $totalMinutesLogged,
        'today_minutes_logged' => $todayMinutesLogged,
        'current_streak_days' => $streak
    ],
    'priority_distribution' => $priorityDist,
    'category_distribution' => $categoryDist,
    'time_by_category' => $timeByCategory,
    'last_7_days' => $last7Days,
    'recent_activities' => $recentActivities
]);
