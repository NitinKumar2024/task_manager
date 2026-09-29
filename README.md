# 🚀 NexusAI Task Master

An intelligent, AI-powered personal task manager crafted with **PHP 8**, **SQLite 3**, **HTML5/Tailwind CSS**, and Google's **Gemini AI API**.

Designed specifically for focus, privacy, and tracking everything seamlessly.

---

## ✨ Key Features

### 🔒 1. Master Password Lock & Security
- **Lock Screen on Launch**: Prompts for your master password before any tasks or personal data are revealed.
- **Onboarding Setup Wizard**: If launching for the first time, safely configure your master password, your name, and your free Gemini API key.
- **Session Protection**: Supports "Stay unlocked for 30 days" with secure tokens, or click the **Lock** button in the header to instantly lock your workspace anytime.
- **Bcrypt / Argon2 Hashing**: Passwords are securely hashed; database file is shielded in `data/` behind `.htaccess`.

### 🧠 2. Gemini AI Superpowers
- **⚡ Natural Language Smart Quick-Add**:
  - Type naturally in the header bar:
    > *"Prepare Q3 budget presentation by next Friday 4pm high priority #finance 90m"*
  - Gemini parses the title, priority, due date, category, duration, and tags into structured data and creates the task instantly.
- **🪄 AI Task Decomposition (Breakdown)**:
  - Big intimidating task? Click **AI Breakdown** on any task. Gemini analyzes the task and decomposes it into 3–7 actionable, chronological subtasks with realistic time estimates.
- **☀️ AI "Plan My Day" (Daily Briefing)**:
  - Click **Plan My Day** to get a tailored daily executive briefing: Top focus priorities, Morning Deep Work vs. Afternoon execution time-blocks, and strategic productivity coaching.
- **🤖 NexusAI Copilot Chat**:
  - Open the slide-out Copilot drawer anytime. Chat with your personal Chief of Staff who has live context of all your pending tasks, deadlines, and progress.
- **💎 Task Enhancer / Polisher**:
  - Click **AI Polish** inside any task to rewrite rough thoughts into crisp, outcome-oriented goals with a Definition of Done checklist.
- **Model Flexibility & Custom Models**:
  - Primary model: `gemini-3.8-flash`
  - Automated fallback: **`gemini-3.5-flash-lite`** (ultra-fast, reliable backup if primary model is unavailable or rate-limited)
  - **Add Any Model Directly In-App**: You can type and activate ANY custom, experimental, or fine-tuned Gemini model (e.g. `gemini-3.5-flash-lite`, `gemini-3.8-pro`, `gemini-exp-1206`, etc.) right from **Settings** &rarr; **Add Any Model**, and click **Test Model** to verify it instantly!

### 📊 3. Flexible Multi-View Organization
- **📋 Kanban Board**: Visual columns for *Inbox / To Do*, *In Progress*, *Review / Blocked*, and *Completed* with drag-and-drop.
- **📑 List View**: Clean, compact view with quick actions, subtask completion badges, tags, and inline completion toggles.
- **🎯 Eisenhower Matrix**: Automatically organizes tasks into 4 quadrants (*Do First*, *Schedule*, *Delegate / Quick Wins*, *Eliminate*) based on urgency and priority.
- **📅 Monthly Calendar**: Visual grid view of all upcoming task deadlines and schedules.
- **📈 Analytics & Velocity**: Tracks completion percentage, 7-day velocity chart, daily active streak, hours logged by category, and full audit logs.

### ⏱️ 4. Deep Tracking ("Track Everything")
- **⏱️ Integrated Pomodoro & Time Tracker**: 25-minute focus sessions with automatic logging into the task's history.
- **🔁 Recurring Tasks**: Automatic recurrence support (`Daily`, `Weekdays`, `Weekly`, `Monthly`). Completing a recurring task automatically schedules the next occurrence!
- **⚡ Energy Levels**: Categorize tasks by required cognitive energy (`Deep Work`, `Standard Focus`, `Quick Win`).
- **📁 Projects / Categories & Color Tags**: Organize life and work into custom categories with custom color palettes and tags.
- **💾 Full Backup & Restore**: One-click download of the raw SQLite `.db` file or a complete JSON backup.

---

## 🚀 How to Run

### Method 1: 1-Click Batch Launcher (Recommended)
Simply double-click or run:
```cmd
run.bat
```
This automatically finds your PHP installation (`C:\xampp\php\php.exe` or system PHP), starts the web server on `http://localhost:8080`, and opens your default browser!

---

### Method 2: Manual PHP Command Line
Open PowerShell or Command Prompt in `D:\Task Manager`:
```powershell
& "C:\xampp\php\php.exe" -S localhost:8080 -t .
```
Then visit:
```
http://localhost:8080
```

---

## 🔑 Getting Your Free Gemini API Key

1. Go to [Google AI Studio](https://aistudio.google.com/app/apikey).
2. Sign in with your Google Account and click **"Create API Key"**.
3. Copy your key and paste it either into the initial **Setup Wizard** or under **Settings** in the dashboard.
4. Click **Test Key** to verify connectivity!

---

## 📂 Project Architecture

```
d:\Task Manager/
├── data/
│   ├── .htaccess          # Web access protection
│   └── tasks.db           # SQLite 3 Database (auto-created)
├── api/
│   ├── auth.php           # Status, setup, login, logout, password change
│   ├── tasks.php          # CRUD tasks, filter, recurring logic, batch actions
│   ├── subtasks.php       # Checklist CRUD, toggles, bulk insertions
│   ├── categories.php     # Categories management & task counters
│   ├── tags.php           # Tag management & associations
│   ├── timelogs.php       # Time tracking & Pomodoro logging
│   ├── ai.php             # Gemini API bridge (parse, breakdown, plan day, copilot chat)
│   ├── settings.php       # User preferences, backup SQLite db, JSON export
│   └── stats.php          # Productivity metrics, streak calculator, velocity
├── includes/
│   ├── config.php         # Session configuration, paths, helpers
│   ├── db.php             # SQLite schema, migrations, connection
│   ├── auth_middleware.php# Authentication verification & security tokens
│   └── gemini.php         # Robust Gemini API client with cURL & SSL handling
├── assets/
│   ├── css/
│   │   └── style.css      # Dark theme, glassmorphism, animations, custom badges
│   └── js/
│       ├── app.js         # Core SPA orchestrator, lock screen, timers
│       ├── ai.js          # Gemini interactions, chat copilot, daily plan
│       └── views.js       # Kanban board, list, Eisenhower matrix, calendar, charts
├── index.php              # Main single-page application shell
├── run.bat                # 1-click Windows runner
└── README.md              # Documentation
```
