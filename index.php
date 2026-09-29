<?php
/**
 * NexusAI Task Master - Single Page Application Shell
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$db = getDb();
$isConfigured = isAppConfigured($db);
$isAuth = isAuthenticated();
$appTitle = getSetting($db, 'app_title', 'NexusAI Task Master');
$geminiModel = ($m = getSetting($db, 'gemini_model', 'gemini-3.8-flash')) === 'gemini-2.5-flash' ? 'gemini-3.8-flash' : $m;
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appTitle) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen overflow-x-hidden antialiased select-none">

    <!-- ========================================== -->
    <!-- 1. MASTER PASSWORD LOCK SCREEN             -->
    <!-- ========================================== -->
    <div id="lock-screen" class="<?= ($isConfigured && !$isAuth) ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-slate-950 p-4">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-indigo-950/40 via-slate-950 to-slate-950"></div>
        <div id="lock-card" class="relative z-10 w-full max-w-md glass-card rounded-2xl p-8 border border-slate-800 shadow-2xl">
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-xl shadow-indigo-600/30 mb-4">
                    <i data-lucide="lock" class="w-8 h-8"></i>
                </div>
                <h1 class="text-xl font-bold tracking-tight text-white"><?= htmlspecialchars($appTitle) ?></h1>
                <p class="text-xs text-slate-400 mt-1">Private & Secured Workspace</p>
            </div>

            <form onsubmit="App.handleLogin(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Master Password</label>
                    <div class="relative">
                        <input type="password" id="lock-password-input" required autofocus
                               placeholder="Enter master password..."
                               class="w-full bg-slate-900/90 border border-slate-700 focus:border-indigo-500 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none transition pr-10">
                        <button type="button" onclick="const p = document.getElementById('lock-password-input'); p.type = (p.type === 'password') ? 'text' : 'password';"
                                class="absolute right-3 top-3 text-slate-400 hover:text-slate-200">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="lock-remember-me" checked class="rounded border-slate-700 bg-slate-900 text-indigo-600">
                        <span>Stay unlocked for 30 days</span>
                    </label>
                </div>

                <button type="submit" id="lock-submit-btn"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2">
                    Unlock Workspace <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. INITIAL SETUP SCREEN (First launch)     -->
    <!-- ========================================== -->
    <div id="setup-screen" class="<?= (!$isConfigured) ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-slate-950 p-4">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-900/30 via-slate-950 to-slate-950"></div>
        <div class="relative z-10 w-full max-w-lg glass-card rounded-2xl p-8 border border-slate-800 shadow-2xl">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-800">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-lg">
                    <i data-lucide="sparkles" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white">Welcome to NexusAI Task Master</h2>
                    <p class="text-xs text-slate-400">Set your master password to secure your personal tasks</p>
                </div>
            </div>

            <form onsubmit="App.handleSetup(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Your Name / Handle</label>
                    <input type="text" id="setup-name" value="Nitin" required
                           class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded-xl px-4 py-2.5 text-sm text-white outline-none">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Master Password</label>
                        <input type="password" id="setup-password" required minlength="4" placeholder="Min 4 characters"
                               class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded-xl px-4 py-2.5 text-sm text-white outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Confirm Password</label>
                        <input type="password" id="setup-password-confirm" required minlength="4" placeholder="Repeat password"
                               class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded-xl px-4 py-2.5 text-sm text-white outline-none">
                    </div>
                </div>

                <div class="pt-2">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="key" class="w-3.5 h-3.5 text-indigo-400"></i> Gemini API Key (Optional)
                        </label>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-[11px] text-indigo-400 hover:underline">Get Free Key &rarr;</a>
                    </div>
                    <input type="text" id="setup-gemini-key" placeholder="AIzaSy... (You can also set this later in Settings)"
                           class="w-full bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded-xl px-4 py-2.5 text-sm text-white outline-none">
                    <p class="text-[11px] text-slate-500 mt-1">Enables AI task decomposition, smart parsing, daily planner, and conversational copilot.</p>
                </div>

                <button type="submit"
                        class="w-full mt-4 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Complete Setup & Launch
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 3. MAIN APPLICATION DASHBOARD SHELL        -->
    <!-- ========================================== -->
    <div id="app-shell" class="<?= ($isConfigured && $isAuth) ? '' : 'hidden' ?> flex h-screen overflow-hidden">

        <!-- ================= SIDEBAR ================= -->
        <aside class="w-64 bg-slate-950 border-r border-slate-800/80 flex flex-col shrink-0">
            <!-- App Brand & User Badge -->
            <div class="p-4 border-b border-slate-800/80 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-md">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span id="header-app-title" class="font-bold text-sm text-slate-100 truncate"><?= htmlspecialchars($appTitle) ?></span>
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span id="header-user-name" class="text-[11px] text-slate-400 truncate">Me</span>
                        </div>
                    </div>
                </div>
                <button onclick="App.lockApp()" title="Lock Screen" class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto p-3 space-y-6">
                <!-- Core Views -->
                <div class="space-y-1">
                    <div class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Tasks Overview</div>

                    <button onclick="App.switchTab('all')" id="nav-all"
                            class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs bg-indigo-600/20 text-indigo-400 font-semibold transition">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i>
                            <span>All Tasks</span>
                        </div>
                        <span id="badge-all" class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300">0</span>
                    </button>

                    <button onclick="App.switchTab('today')" id="nav-today"
                            class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="sun" class="w-4 h-4 text-amber-400"></i>
                            <span>Due Today</span>
                        </div>
                        <span id="badge-today" class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300">0</span>
                    </button>

                    <button onclick="App.switchTab('upcoming')" id="nav-upcoming"
                            class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="calendar" class="w-4 h-4 text-blue-400"></i>
                            <span>Upcoming</span>
                        </div>
                        <span id="badge-upcoming" class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300">0</span>
                    </button>

                    <button onclick="App.switchTab('overdue')" id="nav-overdue"
                            class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-red-400"></i>
                            <span>Overdue</span>
                        </div>
                        <span id="badge-overdue" class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-950 text-red-300">0</span>
                    </button>

                    <button onclick="App.switchTab('completed')" id="nav-completed"
                            class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
                            <span>Completed</span>
                        </div>
                    </button>
                </div>

                <!-- Projects / Categories -->
                <div>
                    <div class="flex items-center justify-between px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">
                        <span>Projects</span>
                        <button onclick="App.openNewCategoryModal()" title="Add Category" class="text-slate-400 hover:text-indigo-400">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                    <div id="sidebar-categories-list" class="space-y-1">
                        <!-- Loaded dynamically -->
                    </div>
                </div>

                <!-- Tags Cloud -->
                <div>
                    <div class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Tags</div>
                    <div id="sidebar-tags-cloud" class="flex flex-wrap gap-1.5 px-2">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Footer Quick Actions -->
            <div class="p-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                <button onclick="App.openSettingsModal()" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-200 transition">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Settings</span>
                </button>
                <button onclick="AI.toggleCopilot()" class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600/10 text-indigo-400 hover:bg-indigo-600/20 transition">
                    <i data-lucide="bot" class="w-4 h-4"></i>
                    <span class="font-medium">Copilot</span>
                </button>
            </div>
        </aside>

        <!-- ================= MAIN CONTENT ================= -->
        <main class="flex-1 flex flex-col min-w-0 bg-slate-950 overflow-hidden">

            <!-- Top Header & AI Quick-Add -->
            <header class="bg-slate-900/60 border-b border-slate-800/80 p-3.5 flex items-center justify-between gap-4 shrink-0">
                <!-- AI Smart Quick-Add Bar -->
                <div class="flex-1 max-w-2xl">
                    <div class="relative flex items-center">
                        <i data-lucide="sparkles" class="w-4 h-4 text-purple-400 absolute left-3 pointer-events-none"></i>
                        <input type="text" id="ai-quick-input"
                               onkeydown="if(event.key === 'Enter') AI.handleSmartQuickAdd(this)"
                               placeholder="AI Quick-Add: 'Finish quarterly review by Thursday 4pm high priority #finance 90m'..."
                               class="w-full bg-slate-900 border border-slate-800 focus:border-purple-500 rounded-xl pl-9 pr-24 py-2 text-xs text-slate-100 placeholder-slate-500 outline-none transition shadow-inner">
                        <button id="ai-quick-btn" onclick="AI.handleSmartQuickAdd(document.getElementById('ai-quick-input'))"
                                class="absolute right-1.5 px-2.5 py-1 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-[11px] font-semibold transition flex items-center gap-1">
                            <span>Smart Add</span>
                        </button>
                    </div>
                </div>

                <!-- Right Header Actions (Timer, Plan Day, New Task) -->
                <div class="flex items-center gap-3">
                    <!-- Pomodoro Timer Pill -->
                    <div class="flex items-center gap-2 bg-slate-900/80 border border-slate-800 rounded-xl px-3 py-1 text-xs">
                        <button id="timer-toggle-btn" onclick="App.toggleTimer()" class="text-indigo-400 hover:text-indigo-300">
                            <i data-lucide="play" class="w-3.5 h-3.5"></i>
                        </button>
                        <span id="timer-display" class="font-mono font-bold text-slate-200">25:00</span>
                        <span id="timer-task-label" class="text-[10px] text-slate-400 max-w-[90px] truncate">Focus</span>
                        <button onclick="App.resetTimer()" title="Reset Timer" class="text-slate-500 hover:text-slate-300 ml-1">
                            <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                        </button>
                    </div>

                    <!-- AI Plan My Day Button -->
                    <button onclick="AI.openDailyPlan()"
                            class="px-3 py-1.5 bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/40 hover:border-amber-500 text-amber-300 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                        <i data-lucide="sun" class="w-3.5 h-3.5 text-amber-400"></i>
                        <span>Plan My Day</span>
                    </button>

                    <!-- + New Task Button -->
                    <button onclick="App.openCreateModal()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition shadow-md shadow-indigo-600/20 flex items-center gap-1.5">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>New Task</span>
                    </button>

                    <!-- Active Model Pill (Click to switch/add model) -->
                    <button onclick="App.openSettingsModal()" title="Active Gemini model. Click to switch or add any model."
                            class="hidden lg:flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-slate-800 hover:border-indigo-500/80 text-slate-300 border border-slate-800 transition cursor-pointer">
                        <i data-lucide="cpu" class="w-3 h-3 text-indigo-400"></i>
                        <span id="header-model-badge"><?= htmlspecialchars($geminiModel) ?></span>
                        <i data-lucide="chevron-down" class="w-2.5 h-2.5 text-slate-500"></i>
                    </button>
                </div>
            </header>

            <!-- Secondary Toolbar: Layout switchers, search, filters -->
            <div class="px-6 py-3 border-b border-slate-800/60 bg-slate-950 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <!-- View Mode Tabs -->
                <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-slate-800">
                    <button data-view="board" onclick="App.switchView('board')"
                            class="view-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition">
                        <i data-lucide="kanban" class="w-3.5 h-3.5"></i>
                        <span>Board</span>
                    </button>
                    <button data-view="list" onclick="App.switchView('list')"
                            class="view-tab-btn px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5 transition">
                        <i data-lucide="list" class="w-3.5 h-3.5"></i>
                        <span>List</span>
                    </button>
                    <button data-view="matrix" onclick="App.switchView('matrix')"
                            class="view-tab-btn px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5 transition">
                        <i data-lucide="grid" class="w-3.5 h-3.5"></i>
                        <span>Eisenhower Matrix</span>
                    </button>
                    <button data-view="calendar" onclick="App.switchView('calendar')"
                            class="view-tab-btn px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5 transition">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                        <span>Calendar</span>
                    </button>
                    <button data-view="stats" onclick="App.switchView('stats')"
                            class="view-tab-btn px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5 transition">
                        <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i>
                        <span>Analytics</span>
                    </button>
                </div>

                <!-- Filters -->
                <div class="flex items-center gap-2.5">
                    <!-- Search Input -->
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none"></i>
                        <input type="text" id="global-search-input" placeholder="Search tasks..."
                               class="bg-slate-900 border border-slate-800 focus:border-indigo-500 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 outline-none w-44">
                    </div>

                    <!-- Category Filter -->
                    <select id="filter-category" class="bg-slate-900 border border-slate-800 text-slate-300 text-xs rounded-lg px-2.5 py-1.5 outline-none">
                        <option value="">All Categories</option>
                    </select>

                    <!-- Priority Filter -->
                    <select id="filter-priority" class="bg-slate-900 border border-slate-800 text-slate-300 text-xs rounded-lg px-2.5 py-1.5 outline-none">
                        <option value="">All Priorities</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>

                    <!-- Sort -->
                    <select id="filter-sort" class="bg-slate-900 border border-slate-800 text-slate-300 text-xs rounded-lg px-2.5 py-1.5 outline-none">
                        <option value="position">Custom Order</option>
                        <option value="due_asc">Due Date (Earliest)</option>
                        <option value="due_desc">Due Date (Latest)</option>
                        <option value="priority">Priority (High to Low)</option>
                        <option value="created_desc">Recently Created</option>
                    </select>
                </div>
            </div>

            <!-- View Dynamic Container -->
            <div id="view-container" class="flex-1 overflow-y-auto p-6">
                <!-- Dynamically loaded by views.js -->
            </div>
        </main>

        <!-- ================= AI COPILOT CHAT DRAWER ================= -->
        <aside id="copilot-drawer" class="fixed right-0 top-0 bottom-0 w-96 bg-slate-950/95 backdrop-blur-xl border-l border-slate-800 z-40 transform translate-x-full transition-transform duration-300 flex flex-col shadow-2xl">
            <!-- Copilot Header -->
            <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white shadow-md">
                        <i data-lucide="bot" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-100">NexusAI Copilot</h3>
                        <p class="text-[10px] text-slate-400">Chief of Staff & Task Strategist</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button onclick="AI.clearCopilotChat()" title="Clear Chat History" class="p-1.5 text-slate-400 hover:text-slate-200 rounded-lg">
                        <i data-lucide="trash" class="w-4 h-4"></i>
                    </button>
                    <button onclick="AI.toggleCopilot()" class="p-1.5 text-slate-400 hover:text-slate-200 rounded-lg">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Quick Prompt Suggestions -->
            <div class="p-3 bg-slate-900/30 border-b border-slate-800/80 flex flex-wrap gap-1.5">
                <button onclick="AI.sendCopilotMessage('What should I focus on right now?')" class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:border-indigo-500 transition">
                    🎯 What should I focus on now?
                </button>
                <button onclick="AI.sendCopilotMessage('Summarize my pending tasks and overdue deadlines')" class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:border-indigo-500 transition">
                    📋 Summarize my workload
                </button>
                <button onclick="AI.sendCopilotMessage('Give me a 5-step productivity schedule for today')" class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:border-indigo-500 transition">
                    ⚡ 5-Step Schedule
                </button>
            </div>

            <!-- Chat Messages Area -->
            <div id="copilot-messages" class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col">
                <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-2xl text-xs text-slate-300">
                    👋 <strong>Hello! I am your AI Copilot.</strong> I have direct visibility into all your tasks, deadlines, and categories. How can I assist you today?
                </div>
            </div>

            <!-- Chat Input Area -->
            <div class="p-3 border-t border-slate-800 bg-slate-950">
                <div class="relative flex items-center">
                    <input type="text" id="copilot-input"
                           onkeydown="if(event.key === 'Enter') AI.sendCopilotMessage()"
                           placeholder="Ask Copilot anything about your tasks..."
                           class="w-full bg-slate-900 border border-slate-800 focus:border-indigo-500 rounded-xl pl-3 pr-10 py-2.5 text-xs text-white placeholder-slate-500 outline-none">
                    <button onclick="AI.sendCopilotMessage()" class="absolute right-2 text-indigo-400 hover:text-indigo-300 p-1">
                        <i data-lucide="send" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- ========================================== -->
    <!-- 4. MODALS CONTAINER                        -->
    <!-- ========================================== -->

    <!-- Task Create / Edit Modal -->
    <div id="task-modal" class="app-modal hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
        <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4 shrink-0">
                <div class="flex items-center gap-2">
                    <span id="modal-title" class="font-bold text-base text-slate-100">Task Details</span>
                    <button id="polish-task-btn" onclick="AI.polishTaskInModal()" title="AI Polish with SMART criteria"
                            class="px-2.5 py-1 rounded-lg bg-purple-950/80 border border-purple-700 text-purple-300 text-xs font-medium hover:bg-purple-900 transition flex items-center gap-1.5 ml-2">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        <span>AI Polish</span>
                    </button>
                </div>
                <button onclick="document.getElementById('task-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-200 p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Task Title</label>
                    <input type="text" id="modal-task-title" required placeholder="What needs to be done?"
                           class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl px-4 py-2.5 text-sm text-white outline-none">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Category / Project</label>
                        <select id="modal-task-category" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                            <option value="">Select Project</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Priority</label>
                        <select id="modal-task-priority" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                            <option value="critical">🚨 Critical</option>
                            <option value="high">🔥 High</option>
                            <option value="medium" selected>⚡ Medium</option>
                            <option value="low">🌱 Low</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Energy Level</label>
                        <select id="modal-task-energy" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                            <option value="high">Deep Work (High Energy)</option>
                            <option value="medium" selected>Standard Focus</option>
                            <option value="low">Quick Win (Low Energy)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Due Date & Time</label>
                        <input type="datetime-local" id="modal-task-due"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Estimated Minutes</label>
                        <input type="number" id="modal-task-estimated" placeholder="e.g. 45"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Recurrence</label>
                        <select id="modal-task-recurrence" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                            <option value="none">One-time</option>
                            <option value="daily">Daily</option>
                            <option value="weekdays">Weekdays</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Status</label>
                    <select id="modal-task-status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                        <option value="inbox">Inbox / To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="review">Review / Blocked</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Description & Notes (Markdown supported)</label>
                    <textarea id="modal-task-desc" rows="3" placeholder="Context, links, checklists, acceptance criteria..."
                              class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl p-3 text-xs text-white outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Tags (Comma separated)</label>
                    <input type="text" id="modal-task-tags" placeholder="e.g. work, client, urgent"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                </div>

                <!-- Subtasks Checklist Section -->
                <div id="modal-subtasks-section" class="pt-3 border-t border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="check-square" class="w-4 h-4 text-indigo-400"></i> Subtasks / Checklist
                        </label>
                        <button onclick="AI.openTaskBreakdown(App.state.editingTaskId)" class="text-xs text-purple-400 hover:text-purple-300 flex items-center gap-1">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            <span>AI Decompose</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <input type="text" id="new-subtask-input" placeholder="Add a checklist item..."
                               onkeydown="if(event.key === 'Enter') App.addSubtaskFromModal()"
                               class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white outline-none">
                        <button onclick="App.addSubtaskFromModal()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-medium transition">
                            Add
                        </button>
                    </div>

                    <div id="modal-subtasks-list" class="space-y-1.5 max-h-40 overflow-y-auto">
                        <!-- Loaded dynamically -->
                    </div>
                </div>

                <!-- Time Tracking Section -->
                <div id="modal-timelog-section" class="pt-3 border-t border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i> Time Tracking & Logs
                        </label>
                        <span id="modal-total-time-badge" class="text-xs font-bold text-indigo-400">0m logged</span>
                    </div>

                    <div class="flex items-center gap-2 mb-3">
                        <input type="number" id="manual-time-minutes" placeholder="Minutes (e.g. 30)" class="w-28 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white outline-none">
                        <input type="text" id="manual-time-note" placeholder="Session note..." class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-white outline-none">
                        <button onclick="App.logManualTime()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-medium transition">
                            Log Time
                        </button>
                    </div>

                    <div id="modal-timelog-list" class="space-y-1.5 max-h-32 overflow-y-auto">
                        <!-- Loaded dynamically -->
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-800 mt-4 shrink-0">
                <button id="modal-delete-btn" onclick="App.deleteTask()" class="px-3 py-2 text-xs text-red-400 hover:bg-red-950/40 rounded-lg transition flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Delete Task</span>
                </button>
                <div class="flex items-center gap-2">
                    <button onclick="document.getElementById('task-modal').classList.add('hidden')" class="px-4 py-2 text-xs text-slate-400 hover:bg-slate-800 rounded-lg transition">
                        Cancel
                    </button>
                    <button onclick="App.saveTaskFromModal()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition shadow-md">
                        Save Task
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Plan & Breakdown Shared Modal -->
    <div id="ai-modal" class="app-modal hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
        <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4 shrink-0">
                <h3 id="ai-modal-title" class="font-bold text-base text-slate-100 flex items-center gap-2">AI Assistant</h3>
                <button onclick="document.getElementById('ai-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-200 p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="ai-modal-content" class="flex-1 overflow-y-auto pr-1">
                <!-- Content injected dynamically -->
            </div>
        </div>
    </div>

    <!-- Settings & Backup Modal -->
    <div id="settings-modal" class="app-modal hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4">
        <div class="w-full max-w-xl bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-4 shrink-0">
                <h3 class="font-bold text-base text-slate-100 flex items-center gap-2">
                    <i data-lucide="settings" class="w-5 h-5 text-indigo-400"></i> Settings & Security
                </h3>
                <button onclick="document.getElementById('settings-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-200 p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-5 pr-1 text-slate-200">
                <!-- General Section -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">General Profile</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Your Name</label>
                            <input type="text" id="settings-name" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">App Title</label>
                            <input type="text" id="settings-app-title" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                        </div>
                    </div>
                </div>

                <!-- Gemini AI Configuration -->
                <div class="space-y-3 pt-3 border-t border-slate-800">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="sparkles" class="w-4 h-4 text-purple-400"></i> Gemini AI Configuration
                        </h4>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-[11px] text-indigo-400 hover:underline">Get Free Gemini Key &rarr;</a>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Gemini API Key</label>
                        <div class="flex gap-2">
                            <input type="password" id="settings-gemini-key" placeholder="Paste your API key"
                                   class="flex-1 bg-slate-950 border border-slate-800 focus:border-purple-500 rounded-xl px-3 py-2 text-xs text-white outline-none">
                            <button type="button" id="test-key-btn" onclick="App.testGeminiInSettings()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition flex items-center gap-1.5 shrink-0">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                <span>Test Key</span>
                            </button>
                        </div>
                    </div>
                    <!-- Active Model Selector & Custom Model Addition -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs text-slate-400 font-medium">Active AI Model</label>
                            <span class="text-[10px] text-slate-500 flex items-center gap-1" title="If primary model fails, requests automatically fall back to gemini-3.5-flash-lite">
                                <i data-lucide="shield-check" class="w-3 h-3 text-emerald-400"></i> Fallback: gemini-3.5-flash-lite
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <select id="settings-gemini-model" onchange="App.onModelSelectChange(this.value)"
                                    class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white outline-none">
                                <!-- Populated dynamically from available_models -->
                            </select>
                            <button type="button" onclick="App.toggleCustomModelInput()"
                                    class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 border border-slate-700 rounded-xl text-xs font-medium transition flex items-center gap-1 shrink-0">
                                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                                <span>Add Any Model</span>
                            </button>
                        </div>
                    </div>

                    <!-- Custom Model Input Drawer -->
                    <div id="custom-model-box" class="hidden p-3.5 rounded-xl bg-slate-950 border border-indigo-500/40 space-y-2.5 transition animate-fade-in">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-indigo-300 flex items-center gap-1.5">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-400"></i> Add Any Custom Gemini Model
                            </span>
                            <button type="button" onclick="App.toggleCustomModelInput(false)" class="text-slate-500 hover:text-slate-300 p-0.5">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-tight">
                            Type any Gemini model ID (e.g. <span class="text-indigo-300 font-mono">gemini-3.5-flash-lite</span>, <span class="text-indigo-300 font-mono">gemini-3.8-flash</span>, <span class="text-indigo-300 font-mono">gemini-3.8-pro</span>, experimental, or tuned models).
                        </p>
                        <div class="flex gap-2">
                            <input type="text" id="custom-model-input" placeholder="e.g. gemini-3.5-flash-lite or custom model..."
                                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); App.addCustomModel(); }"
                                   class="flex-1 bg-slate-900 border border-slate-700 focus:border-indigo-500 rounded-lg px-3 py-2 text-xs text-white placeholder-slate-500 outline-none">
                            <button type="button" onclick="App.addCustomModel()"
                                    class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition shrink-0 flex items-center gap-1.5">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                <span>Add & Activate</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="space-y-3 pt-3 border-t border-slate-800">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Access Security</h4>
                    <button onclick="App.changeMasterPassword()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition flex items-center gap-2">
                        <i data-lucide="key-round" class="w-4 h-4 text-amber-400"></i>
                        <span>Change Master Password</span>
                    </button>
                </div>

                <!-- Data Backup & Restore -->
                <div class="space-y-3 pt-3 border-t border-slate-800">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Database & Backup</h4>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="App.exportDb()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition flex items-center gap-1.5">
                            <i data-lucide="database" class="w-4 h-4 text-emerald-400"></i>
                            <span>Download SQLite (.db)</span>
                        </button>
                        <button onclick="App.exportJson()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition flex items-center gap-1.5">
                            <i data-lucide="download" class="w-4 h-4 text-blue-400"></i>
                            <span>Export JSON Backup</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-800 mt-4 shrink-0">
                <button onclick="document.getElementById('settings-modal').classList.add('hidden')" class="px-4 py-2 text-xs text-slate-400 hover:bg-slate-800 rounded-lg transition">
                    Close
                </button>
                <button onclick="App.saveSettings()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition">
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- Application Scripts -->
    <script src="assets/js/views.js"></script>
    <script src="assets/js/ai.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
