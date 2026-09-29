<?php
/**
 * SQLite Database Connection & Auto-Migration
 */

require_once __DIR__ . '/config.php';

function getDb(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dbExists = file_exists(DB_PATH);
        
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);

        // Enable foreign key constraints and Write-Ahead Logging
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

        // Initialize schema if newly created or missing tables
        initDbSchema($pdo);
    }

    return $pdo;
}

function initDbSchema(PDO $db): void {
    // 1. Settings Table
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Categories Table
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT NOT NULL DEFAULT '#6366f1',
        icon TEXT DEFAULT 'folder',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Tags Table
    $db->exec("CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT '#94a3b8'
    )");

    // 4. Tasks Table
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
        title TEXT NOT NULL,
        description TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'inbox', /* inbox, planned, in_progress, review, completed, archived */
        priority TEXT NOT NULL DEFAULT 'medium', /* low, medium, high, critical */
        energy_level TEXT DEFAULT 'medium', /* low, medium, high */
        due_date TEXT NULL, /* YYYY-MM-DD or YYYY-MM-DD HH:MM */
        start_date TEXT NULL,
        completed_at DATETIME NULL,
        estimated_minutes INTEGER DEFAULT 0,
        spent_minutes INTEGER DEFAULT 0,
        recurrence TEXT DEFAULT 'none', /* none, daily, weekly, monthly */
        position INTEGER DEFAULT 0,
        is_pinned INTEGER DEFAULT 0,
        ai_notes TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Indexes for fast searching & filtering
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_priority ON tasks(priority)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_due_date ON tasks(due_date)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tasks_category ON tasks(category_id)");

    // 5. Task Tags Mapping Table
    $db->exec("CREATE TABLE IF NOT EXISTS task_tags (
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
        PRIMARY KEY (task_id, tag_id)
    )");

    // 6. Subtasks Table
    $db->exec("CREATE TABLE IF NOT EXISTS subtasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        title TEXT NOT NULL,
        is_completed INTEGER NOT NULL DEFAULT 0,
        estimated_minutes INTEGER DEFAULT 0,
        position INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_subtasks_task ON subtasks(task_id)");

    // 7. Time Tracking Logs Table
    $db->exec("CREATE TABLE IF NOT EXISTS time_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        duration_minutes INTEGER NOT NULL,
        note TEXT DEFAULT '',
        logged_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. Activity / Audit Log Table (Tracks everything)
    $db->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NULL REFERENCES tasks(id) ON DELETE CASCADE,
        action TEXT NOT NULL,
        details TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 9. AI Conversations Table (for task copilot assistant)
    $db->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role TEXT NOT NULL,
        message TEXT NOT NULL,
        context_type TEXT DEFAULT 'general',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed initial default categories if empty
    $catCount = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    if ($catCount == 0) {
        $stmt = $db->prepare("INSERT INTO categories (name, color, icon) VALUES (?, ?, ?)");
        $defaults = [
            ['Work', '#3b82f6', 'briefcase'],
            ['Personal', '#10b981', 'user'],
            ['Projects', '#8b5cf6', 'folder-git-2'],
            ['Learning', '#f59e0b', 'book-open'],
            ['Health & Fitness', '#ec4899', 'heart']
        ];
        foreach ($defaults as $cat) {
            $stmt->execute($cat);
        }
    }

    // Seed initial default tags if empty
    $tagCount = $db->query("SELECT COUNT(*) FROM tags")->fetchColumn();
    if ($tagCount == 0) {
        $stmt = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
        $defaultTags = [
            ['urgent', '#ef4444'],
            ['deep-work', '#6366f1'],
            ['quick-win', '#10b981'],
            ['meeting', '#f59e0b'],
            ['admin', '#64748b']
        ];
        foreach ($defaultTags as $tag) {
            $stmt->execute($tag);
        }
    }

    // Seed default settings if empty
    $defaultSettings = [
        'app_title' => 'NexusAI Task Master',
        'user_name' => 'Me',
        'gemini_model' => 'gemini-2.5-flash',
        'theme' => 'dark',
        'daily_goal_minutes' => '240',
        'timezone' => 'Asia/Kolkata'
    ];
    $checkSetting = $db->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
    $insertSetting = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    foreach ($defaultSettings as $key => $val) {
        $checkSetting->execute([$key]);
        if ($checkSetting->fetchColumn() == 0) {
            $insertSetting->execute([$key, $val]);
        }
    }
}

// Activity logging helper
function logActivity(PDO $db, ?int $taskId, string $action, string $details = ''): void {
    try {
        $stmt = $db->prepare("INSERT INTO activity_log (task_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$taskId, $action, $details]);
    } catch (Exception $e) {
        // Silently prevent logging errors from failing primary operations
    }
}

// Settings getter and setter
function getSetting(PDO $db, string $key, string $default = ''): string {
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetchColumn();
    return $res !== false ? (string)$res : $default;
}

function setSetting(PDO $db, string $key, string $value): void {
    $stmt = $db->prepare("INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                          ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$key, $value]);
}
